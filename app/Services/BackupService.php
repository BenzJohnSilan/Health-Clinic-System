<?php

namespace App\Services;

use App\Models\BackupLog;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;
use ZipArchive;

/**
 * Creates and records ClinicRMS backups (Phase 1: create + record only).
 *
 * A backup is a single timestamped ZIP stored in protected storage
 * (storage/app/private/backups — never under public/):
 *
 *   database/database.sql                   full mysqldump of every table
 *   files/storage/app/public/<folder>/...   persistent uploads (see config/backup.php)
 *   manifest.json                           what the archive contains
 *
 * Safety properties:
 *  - Read-only against the live database (mysqldump only reads).
 *  - Credentials are handed to mysqldump through a temporary 0600 options
 *    file, so they never appear in the process list, logs or filenames.
 *  - The archive is built in a private temp folder and only moved into
 *    place after it passed an integrity check, so a half-written ZIP never
 *    shows up as a backup.
 *  - A file lock prevents two backups from running at the same time.
 *  - Temp files are always removed, on success and on failure.
 *
 * There is intentionally NO restore logic here yet.
 */
class BackupService
{
    private const DATABASE_ENTRY   = 'database/database.sql';
    private const MANIFEST_ENTRY   = 'manifest.json';
    private const FILES_ENTRY_ROOT = 'files/storage/app/public';

    // Re-open the archive every N files so we never hold too many file
    // handles open at once (hosting providers often cap them at ~1024).
    private const ADD_BATCH_SIZE = 200;

    /** @var resource|null */
    private $lockHandle = null;

    private array $summary = [];

    private ?string $secret = null;

    // The connection + database that are both dumped AND verified. They are
    // resolved once in databaseConfig() so the two can never disagree.
    private ?string $connectionName = null;

    private ?string $databaseName = null;

    /**
     * Run a full backup. Never throws for backup problems: the outcome is
     * recorded on (and returned as) the BackupLog — check ->status and
     * ->error_message. (Only failing to record the attempt at all, e.g.
     * the app database being unreachable, propagates as an exception.)
     */
    public function create(string $type = BackupLog::TYPE_MANUAL, ?int $createdBy = null): BackupLog
    {
        $this->summary        = [];
        $this->secret         = null;
        $this->connectionName = null;
        $this->databaseName   = null;

        $filename = $this->uniqueFilename();

        $log = BackupLog::create([
            'filename'   => $filename,
            'type'       => $type,
            'status'     => BackupLog::STATUS_RUNNING,
            'started_at' => now(),
            'created_by' => $createdBy,
        ]);

        $tmpDir    = null;
        $finalPath = null;
        $moved     = false;

        try {
            $backupDir = $this->prepareBackupDirectory();
            $this->acquireLock($backupDir);
            $this->failStaleRuns($log);

            $this->assertZipAvailable();
            $connection = $this->databaseConfig();
            $binary     = $this->locateMysqldump();

            $tmpDir = $this->makeTempDirectory($backupDir);

            $dumpPath = $this->dumpDatabase($binary, $connection, $tmpDir);
            $tables   = $this->validateDump($dumpPath);

            $tmpArchive = $tmpDir . DIRECTORY_SEPARATOR . $filename;
            $built      = $this->buildArchive($tmpArchive, $dumpPath, $type, $tables);
            $this->verifyArchive($tmpArchive, $built['entries']);

            // Move into place last (same filesystem => effectively atomic).
            $finalPath = $backupDir . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($finalPath)) {
                throw new RuntimeException('A backup file with this name already exists.');
            }
            if (!@rename($tmpArchive, $finalPath)) {
                throw new RuntimeException('Could not move the finished archive into the backup folder.');
            }
            $moved = true;
            @chmod($finalPath, 0640);

            clearstatcache(true, $finalPath);
            if (!is_file($finalPath) || !is_readable($finalPath)) {
                throw new RuntimeException('The backup archive is missing or unreadable after creation.');
            }
            $size = filesize($finalPath);
            if (!$size) {
                throw new RuntimeException('The backup archive is empty.');
            }

            $log->update([
                'status'        => BackupLog::STATUS_SUCCESS,
                'size'          => $size,
                'completed_at'  => now(),
                'error_message' => null,
            ]);

            $this->summary = [
                'tables'      => count($tables),
                'files'       => $built['files'],
                'directories' => $built['directories'],
                'location'    => $this->displayPath($finalPath),
            ];
        } catch (Throwable $e) {
            $message = $this->sanitize($e->getMessage());

            // Do not leave a file behind that we reported as failed.
            if ($moved && $finalPath && is_file($finalPath)) {
                @unlink($finalPath);
            }

            try {
                $log->update([
                    'status'        => BackupLog::STATUS_FAILED,
                    'size'          => null,
                    'completed_at'  => now(),
                    'error_message' => Str::limit($message, 1500, '...'),
                ]);
            } catch (Throwable $inner) {
                Log::error('Clinic backup: could not record failure.', ['error' => $this->sanitize($inner->getMessage())]);
            }

            Log::error('Clinic backup failed.', [
                'backup_log_id' => $log->id,
                'exception'     => get_class($e),
                'error'         => $message,
            ]);
        } finally {
            if ($tmpDir) {
                File::deleteDirectory($tmpDir);
            }
            $this->releaseLock();
        }

        return $log->fresh() ?? $log;
    }

    /**
     * Details of the last successful run (tables, files, location) for
     * display by the caller. Empty after a failed run.
     */
    public function summary(): array
    {
        return $this->summary;
    }

    // ---------------------------------------------------------------------
    // Storage location & protection
    // ---------------------------------------------------------------------

    private function uniqueFilename(): string
    {
        $disk = config('backup.disk', 'local');
        $dir  = trim(config('backup.directory', 'backups'), '/\\');
        $base = 'clinic_backup_' . now()->format('Y-m-d_H-i-s');

        $name = $base . '.zip';
        $i    = 2;

        while (
            Storage::disk($disk)->exists($dir . '/' . $name)
            || BackupLog::where('filename', $name)->exists()
        ) {
            $name = $base . '_' . $i++ . '.zip';
        }

        return $name;
    }

    private function prepareBackupDirectory(): string
    {
        $disk = config('backup.disk', 'local');

        if (config("filesystems.disks.$disk.driver") !== 'local') {
            throw new RuntimeException("Backup disk [$disk] must use the local driver.");
        }

        $dir = Storage::disk($disk)->path(trim(config('backup.directory', 'backups'), '/\\'));

        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create the backup folder. Check write permissions on storage/app/private.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('The backup folder is not writable.');
        }

        $this->assertNotPubliclyAccessible($dir);

        // Defense in depth for Apache/LiteSpeed (e.g. Hostinger): even if the
        // project is ever deployed with storage/ inside the web root, the web
        // server will refuse to serve anything from this folder.
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, implode("\n", [
                '# ClinicRMS backups are private. Never serve files from this folder.',
                '<IfModule mod_authz_core.c>',
                '    Require all denied',
                '</IfModule>',
                '<IfModule !mod_authz_core.c>',
                '    Order allow,deny',
                '    Deny from all',
                '</IfModule>',
                '',
            ]));
        }

        return $dir;
    }

    private function assertNotPubliclyAccessible(string $dir): void
    {
        $real = realpath($dir);
        if ($real === false) {
            throw new RuntimeException('Could not resolve the backup folder path.');
        }

        foreach ([public_path(), storage_path('app/public')] as $publicPath) {
            $public = realpath($publicPath);
            if ($public === false) {
                continue;
            }
            if ($real === $public || str_starts_with($real . DIRECTORY_SEPARATOR, $public . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Refusing to store backups inside a publicly accessible folder.');
            }
        }
    }

    private function makeTempDirectory(string $backupDir): string
    {
        $tmp = $backupDir . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(6));

        if (!@mkdir($tmp, 0700, true) && !is_dir($tmp)) {
            throw new RuntimeException('Could not create a temporary working folder for the backup.');
        }

        return $tmp;
    }

    // ---------------------------------------------------------------------
    // Locking (one backup at a time)
    // ---------------------------------------------------------------------

    private function acquireLock(string $backupDir): void
    {
        $handle = @fopen($backupDir . DIRECTORY_SEPARATOR . '.backup.lock', 'c');

        if ($handle === false) {
            throw new RuntimeException('Could not create the backup lock file.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another backup is already running. Try again when it has finished.');
        }

        $this->lockHandle = $handle;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }
        $this->lockHandle = null;
    }

    /**
     * Holding the lock proves nobody else is running, so any other row still
     * marked "running" belongs to a process that died (crash, timeout, kill).
     */
    private function failStaleRuns(BackupLog $current): void
    {
        BackupLog::where('status', BackupLog::STATUS_RUNNING)
            ->where('id', '!=', $current->id)
            ->update([
                'status'        => BackupLog::STATUS_FAILED,
                'completed_at'  => now(),
                'error_message' => 'Interrupted: the backup process ended unexpectedly.',
            ]);
    }

    // ---------------------------------------------------------------------
    // Preflight
    // ---------------------------------------------------------------------

    private function assertZipAvailable(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP "zip" extension is not enabled. Enable it in the PHP settings and try again.');
        }
    }

    private function databaseConfig(): array
    {
        $name   = config('database.default');
        $config = config("database.connections.$name");

        if (!is_array($config) || !in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backups require a MySQL/MariaDB connection (current driver: ' . ($config['driver'] ?? 'unknown') . ').');
        }
        if (empty($config['database'])) {
            throw new RuntimeException('No database name is configured for the backup.');
        }

        $this->secret         = ($config['password'] ?? '') !== '' ? (string) $config['password'] : null;
        $this->connectionName = $name;
        $this->databaseName   = (string) $config['database'];

        return $config;
    }

    private function locateMysqldump(): string
    {
        $configured = config('backup.mysqldump_path');

        if (!empty($configured)) {
            if (!is_file($configured) || !is_executable($configured)) {
                throw new RuntimeException('BACKUP_MYSQLDUMP_PATH is set but does not point to an executable file.');
            }
            return $configured;
        }

        $extraDirs = array_merge(
            glob('C:/laragon/bin/mysql/*/bin', GLOB_ONLYDIR) ?: [],
            glob('C:/laragon/bin/mariadb/*/bin', GLOB_ONLYDIR) ?: [],
            ['/usr/bin', '/usr/local/bin', '/usr/local/mysql/bin', '/opt/homebrew/bin', '/opt/lampp/bin']
        );

        $finder = new ExecutableFinder();
        foreach (['mysqldump', 'mariadb-dump'] as $candidate) {
            $found = $finder->find($candidate, null, $extraDirs);
            if ($found) {
                return $found;
            }
        }

        throw new RuntimeException(
            'mysqldump was not found on this server. Install the MySQL client tools, or set BACKUP_MYSQLDUMP_PATH in .env to the full path of mysqldump.'
        );
    }

    // ---------------------------------------------------------------------
    // Database dump
    // ---------------------------------------------------------------------

    /**
     * Environment that mysqldump needs on Windows.
     *
     * Under `php artisan serve` the web process is started with most environment
     * variables removed, including SystemRoot. Without it Windows cannot start its
     * network layer for the child program, and mysqldump fails with
     * "Can't create TCP/IP socket (10106)" - only from the web page, while the same
     * backup from the command line works. Passing SystemRoot explicitly fixes that.
     * Nothing is added on Linux/macOS (Hostinger), and no secrets are involved.
     *
     * @return array<string,string>
     */
    private function systemEnvironment(?bool $windows = null): array
    {
        if (!($windows ?? PHP_OS_FAMILY === 'Windows')) {
            return [];
        }

        $root = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: getenv('windir') ?: 'C:\\Windows';

        return ['SystemRoot' => $root, 'SYSTEMROOT' => $root, 'windir' => $root];
    }

    private function dumpDatabase(string $binary, array $connection, string $tmpDir): string
    {
        $optionsFile = $tmpDir . DIRECTORY_SEPARATOR . 'client.cnf';
        $dumpPath    = $tmpDir . DIRECTORY_SEPARATOR . 'database.sql';

        $this->writeClientOptionsFile($optionsFile, $connection);

        // --defaults-extra-file must be the first option.
        $command = [
            $binary,
            '--defaults-extra-file=' . $optionsFile,
            '--single-transaction',   // consistent snapshot without locking the live tables
            '--quick',                // stream rows instead of buffering whole tables
            '--no-tablespaces',       // avoids needing the PROCESS privilege (shared hosting)
            '--default-character-set=utf8mb4',
            '--result-file=' . $dumpPath,
            $connection['database'],
        ];

        $result = Process::timeout((int) config('backup.dump_timeout', 600))
            ->env($this->systemEnvironment())
            ->run($command);

        // The options file held the password: remove it immediately.
        @unlink($optionsFile);

        if ($result->failed()) {
            $detail = trim($result->errorOutput()) ?: trim($result->output()) ?: 'no error output';
            throw new RuntimeException('mysqldump failed (exit code ' . $result->exitCode() . '): ' . $detail);
        }

        return $dumpPath;
    }

    private function writeClientOptionsFile(string $path, array $connection): void
    {
        $quote = fn ($value) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value) . '"';

        $lines = ['[client]'];

        if (!empty($connection['username'])) {
            $lines[] = 'user=' . $quote($connection['username']);
        }
        if (($connection['password'] ?? '') !== '') {
            $lines[] = 'password=' . $quote($connection['password']);
        }
        if (!empty($connection['unix_socket'])) {
            $lines[] = 'socket=' . $quote($connection['unix_socket']);
        } else {
            $lines[] = 'host=' . $quote($connection['host'] ?? '127.0.0.1');
            $lines[] = 'port=' . (int) ($connection['port'] ?? 3306);
        }

        $previous = umask(0077); // file is created owner-only
        $written  = @file_put_contents($path, implode("\n", $lines) . "\n");
        umask($previous);

        if ($written === false) {
            throw new RuntimeException('Could not write the temporary database options file.');
        }
        @chmod($path, 0600);
    }

    /**
     * Confirm the dump finished and contains every table currently in the
     * configured ClinicRMS database (tables are discovered live — nothing is
     * hardcoded).
     *
     * @return array<int,string> the live tables that were verified as present in the dump
     */
    private function validateDump(string $dumpPath): array
    {
        if (!is_file($dumpPath) || filesize($dumpPath) === 0) {
            throw new RuntimeException('The database dump is missing or empty.');
        }

        // mysqldump ends a complete dump with "-- Dump completed on ...".
        $size = filesize($dumpPath);
        $fh   = fopen($dumpPath, 'rb');
        fseek($fh, max(0, $size - 4096));
        $tail = (string) stream_get_contents($fh);
        fclose($fh);

        if (!str_contains($tail, 'Dump completed')) {
            throw new RuntimeException('The database dump appears to be incomplete (no completion marker).');
        }

        $dumped = [];
        $fh     = fopen($dumpPath, 'rb');
        while (($line = fgets($fh)) !== false) {
            if (str_starts_with($line, 'CREATE TABLE') && preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`/', $line, $m)) {
                $dumped[$m[1]] = true;
            }
        }
        fclose($fh);

        $live = $this->liveTableNames();

        if (!$live) {
            throw new RuntimeException('The database has no tables to back up.');
        }

        $missing = array_values(array_diff($live, array_keys($dumped)));

        if ($missing) {
            throw new RuntimeException('The database dump is missing table(s): ' . implode(', ', $missing));
        }

        return $live;
    }

    /**
     * Names of the tables that exist in the ONE database being backed up.
     *
     * Important: on MySQL/MariaDB, Schema::getTables() with no argument does
     * NOT mean "the current database" — Laravel 12 then lists the tables of
     * EVERY database on the server (everything except mysql, sys, etc.). On a
     * Laragon/shared server that pulls in other projects' tables and makes
     * the same name appear more than once (e.g. `reports` in two databases).
     * So the configured database is always passed explicitly.
     *
     * @return array<int,string>
     */
    private function liveTableNames(): array
    {
        $rows = Schema::connection($this->connectionName)->getTables($this->databaseName);

        // Belt and braces: never accept a table from another database.
        $rows = array_filter($rows, fn ($row) => strcasecmp((string) ($row['schema'] ?? $this->databaseName), $this->databaseName) === 0);

        $names = array_column($rows, 'name');

        // Table names are unique inside one database. A duplicate here means
        // the lookup is no longer scoped to a single database, so the result
        // cannot be trusted — fail loudly instead of hiding it.
        if (count($names) !== count(array_unique($names))) {
            throw new RuntimeException('Could not verify the dump: the table list is not limited to a single database.');
        }

        return array_values($names);
    }

    // ---------------------------------------------------------------------
    // Archive
    // ---------------------------------------------------------------------

    /**
     * @return array{entries:int, files:int, directories:array<string,int>}
     */
    private function buildArchive(string $archivePath, string $dumpPath, string $type, array $tables): array
    {
        $zip = new ZipArchive();
        if (($code = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) !== true) {
            throw new RuntimeException("Could not create the archive (ZipArchive error $code).");
        }

        if (!$zip->addFile($dumpPath, self::DATABASE_ENTRY)) {
            throw new RuntimeException('Could not add the database dump to the archive.');
        }

        $disk        = config('backup.files.disk', 'public');
        $root        = rtrim(Storage::disk($disk)->path(''), '/\\');
        $directories = [];
        $totalFiles  = 0;
        $pending     = 0;

        foreach (config('backup.files.directories', []) as $folder) {
            $folder = trim($folder, '/\\');
            $source = $root . DIRECTORY_SEPARATOR . $folder;
            $count  = 0;

            if (is_dir($source)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    // Real files only: skip symlinks and hidden files (.gitignore etc.).
                    if ($file->isLink() || !$file->isFile() || str_starts_with($file->getFilename(), '.')) {
                        continue;
                    }

                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                    $entry    = self::FILES_ENTRY_ROOT . '/' . $folder . '/' . $relative;

                    if (!$zip->addFile($file->getPathname(), $entry)) {
                        throw new RuntimeException("Could not add [$folder/$relative] to the archive.");
                    }

                    $count++;
                    $totalFiles++;

                    if (++$pending >= self::ADD_BATCH_SIZE) {
                        $this->flushArchive($zip, $archivePath);
                        $pending = 0;
                    }
                }
            }

            $directories[$folder] = $count;
        }

        $manifest = [
            'application'     => config('app.name'),
            'backup_type'     => $type,
            'created_at'      => now()->toIso8601String(),
            'laravel_version' => app()->version(),
            'php_version'     => PHP_VERSION,
            'database'        => [
                'driver' => config('database.connections.' . config('database.default') . '.driver'),
                'dump'   => self::DATABASE_ENTRY,
                'tables' => count($tables),
            ],
            'files' => [
                'root'        => self::FILES_ENTRY_ROOT,
                'total'       => $totalFiles,
                'directories' => $directories,
            ],
        ];
        $zip->addFromString(self::MANIFEST_ENTRY, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (!$zip->close()) {
            throw new RuntimeException('Failed to write the archive (a source file may have changed or disk space ran out).');
        }

        return [
            'entries'     => 1 + $totalFiles + 1, // dump + uploads + manifest
            'files'       => $totalFiles,
            'directories' => $directories,
        ];
    }

    /**
     * Write queued files to disk and re-open the archive to release handles.
     */
    private function flushArchive(ZipArchive $zip, string $archivePath): void
    {
        if (!$zip->close()) {
            throw new RuntimeException('Failed to write the archive (a source file may have changed or disk space ran out).');
        }
        if (($code = $zip->open($archivePath)) !== true) {
            throw new RuntimeException("Could not re-open the archive (ZipArchive error $code).");
        }
    }

    private function verifyArchive(string $path, int $expectedEntries): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The archive was not created or is not readable.');
        }
        if (!filesize($path)) {
            throw new RuntimeException('The archive is empty.');
        }

        $zip = new ZipArchive();
        if (($code = $zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS)) !== true) {
            throw new RuntimeException("The archive failed its integrity check (ZipArchive error $code).");
        }

        try {
            $dump = $zip->statName(self::DATABASE_ENTRY);

            if ($dump === false || ($dump['size'] ?? 0) <= 0) {
                throw new RuntimeException('The archive does not contain a database dump.');
            }
            if ($zip->locateName(self::MANIFEST_ENTRY) === false) {
                throw new RuntimeException('The archive does not contain its manifest.');
            }
            if ($zip->numFiles !== $expectedEntries) {
                throw new RuntimeException("The archive has {$zip->numFiles} entries but $expectedEntries were expected.");
            }
        } finally {
            $zip->close();
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Strip anything sensitive before a message is stored or logged.
     */
    private function sanitize(string $message): string
    {
        if ($this->secret !== null && $this->secret !== '') {
            $message = str_replace($this->secret, '********', $message);
        }

        return trim($message);
    }

    private function displayPath(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/') . '/';

        return str_replace($base, '', str_replace('\\', '/', $path));
    }
}