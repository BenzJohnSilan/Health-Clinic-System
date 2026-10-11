<?php

namespace App\Services;

use App\Exceptions\RestoreException;
use App\Models\BackupLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\BackupFilesystem;
use Throwable;
use ZipArchive;

/**
 * Restores a backup created by BackupService (Phase 4).
 *
 * The archive format is exactly the existing one:
 *   database/database.sql                 the mysqldump
 *   files/storage/app/public/<dir>/...    persistent uploads
 *   manifest.json                         what the archive says it contains
 *
 * The order is deliberate, and everything that can fail safely fails BEFORE
 * any data is replaced:
 *
 *   1  validate the selected backup (record, file, real path, ZIP, manifest, entries)
 *   2  take the restore lock            -> only one restore at a time
 *   3  block the system for users       -> the safety backup then equals what gets replaced
 *   4  check DB permissions + disk space
 *   5  SAFETY BACKUP (BackupService, type "pre-restore") and verify it -> stop if it fails
 *   6  unpack the backup into a private temp folder, checking every entry and CRC
 *   7  scan the whole SQL dump (executes nothing)
 *   8  take the backup lock             -> no backup can run during 9-11
 *   ---- from here on data is replaced ----
 *   9  database   10 uploaded files   11 verify
 *   12 log, clean up temp files, release locks, un-block the system
 *
 * There is NO automatic rollback: a half-way rollback could make things worse.
 * If step 9-11 fails, the pre-restore backup from step 5 is the way back.
 */
class BackupRestoreService
{
    private const DUMP_ENTRY = 'database/database.sql';

    private const MANIFEST_ENTRY = 'manifest.json';

    private const FILES_ROOT = 'files/storage/app/public';

    private const MAX_ENTRIES = 100000;

    private const MAX_MANIFEST_BYTES = 65536;

    /** Never restored into a public uploads folder, whatever the archive says. */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'dll', 'so',
        'asp', 'aspx', 'jsp', 'htaccess', 'htpasswd', 'ini', 'env',
    ];

    public function __construct(
        private BackupService $backups,
        private BackupFileService $files,
        private BackupDatabaseImporter $importer,
        private RestoreGuard $guard,
    ) {
    }

    // =====================================================================
    // 1. Validation (also used by the confirmation page)
    // =====================================================================

    /**
     * Checks a backup record and its archive WITHOUT changing anything.
     * Throws RestoreException (safe message) when it must not be restored.
     *
     * @return array<string,mixed> facts about the archive (tables, file list, ...)
     */
    public function inspect(BackupLog $backup): array
    {
        return $this->inspectArchive($this->pathFor($backup));
    }

    /**
     * Tables in the live database (for the "more tables than the backup" hint).
     */
    public function liveTableCount(): int
    {
        return count($this->liveTables());
    }

    // =====================================================================
    // Restore
    // =====================================================================

    public function restore(BackupLog $backup, ?int $userId = null): RestoreResult
    {
        $startedAt   = now();
        $safety      = null;
        $op          = null;
        $changed     = false;
        $message     = null;
        $tmp         = null;
        $restoreLock = null;
        $backupLock  = null;
        $marked      = false;

        Log::info('Backup restore requested.', ['backup_log_id' => $backup->id, 'user_id' => $userId]);

        try {
            // 1 — validate the selected backup
            $path = $this->pathFor($backup);
            $info = $this->inspectArchive($path);

            // 2 — one restore at a time
            $restoreLock = $this->guard->acquireRestoreLock();

            if ($restoreLock === null) {
                throw new RestoreException('Another restore is already running. Try again when it has finished.');
            }

            // 3 — nobody works with the data from now on
            $this->guard->markStarted();
            $marked = true;
            $this->cleanupStaleTemp();

            // 4 — can we even do it?
            $this->importer->preflight();
            $this->assertDiskSpace($info['uncompressed']);

            // 5 — SAFETY BACKUP. Reuses BackupService; if it fails, we stop.
            $safety = $this->backups->create(BackupLog::TYPE_PRE_RESTORE, $userId);

            if (!$safety->isSuccessful()) {
                // A FAILED safety backup is not a safety net: never report it as one.
                $safety = null;

                throw new RestoreException('The safety backup could not be created, so nothing was restored.');
            }

            try {
                $this->inspect($safety);
            } catch (RestoreException $e) {
                throw new RestoreException('The safety backup could not be verified, so nothing was restored.', 0, $e);
            }

            $safety->update(['details' => 'Safety backup made before restoring ' . $backup->filename]);

            // The safety backup may have taken a while: the chosen backup must still be intact.
            $path = $this->pathFor($backup);
            $info = $this->inspectArchive($path);

            // 6 — unpack privately, checking every entry and its checksum
            $tmp       = $this->makeTempDirectory();
            $extracted = $this->extract($path, $info, $tmp);

            // 7 — read the whole dump; nothing is executed yet
            $preserve = $this->preserved();
            $scan     = $this->importer->scan($extracted['dump'], $preserve);

            if (count($scan['tables']) !== $info['tables']) {
                throw new RestoreException('The database dump does not match the backup manifest.');
            }

            // 8 — no backup may start while data is being replaced
            $backupLock = $this->guard->acquireBackupLock();

            if ($backupLock === null) {
                throw new RestoreException('A backup started while the restore was being prepared, so nothing was restored. Try again.');
            }

            $op = BackupLog::create([
                'filename'   => $backup->filename,
                'type'       => BackupLog::TYPE_RESTORE,
                'status'     => BackupLog::STATUS_RUNNING,
                'started_at' => $startedAt,
                'created_by' => $userId,
                'details'    => 'Safety backup: ' . $safety->filename,
            ]);

            // ---- data is replaced from here on ----
            $changed = true;

            // 9 — database
            $this->importer->import($extracted['dump'], $preserve);

            // 10 — uploaded files
            $this->restoreFiles($extracted['files']);

            // 11 — prove it worked
            $this->verify($scan['restorable'], $extracted['files']);

            $this->clearCaches();
            $this->normalizeBackupLogs();

            $op->update([
                'status'        => BackupLog::STATUS_SUCCESS,
                'completed_at'  => now(),
                'error_message' => null,
            ]);

            Log::info('Backup restore completed.', [
                'backup_log_id' => $backup->id,
                'safety_backup' => $safety->filename,
                'tables'        => count($scan['restorable']),
                'files'         => count($extracted['files']),
            ]);
        } catch (RestoreException $e) {
            $message = $e->getMessage();
            $this->logFailure($backup, $e);
        } catch (Throwable $e) {
            $message = 'An unexpected error stopped the restore.';
            $this->logFailure($backup, $e);
        } finally {
            if ($tmp !== null) {
                BackupFilesystem::deleteTree($tmp);
            }

            $this->guard->release($backupLock);

            if ($marked) {
                $this->guard->clearMarker();
            }

            $this->guard->release($restoreLock);
        }

        if ($message === null) {
            return new RestoreResult(true, $op, $safety, true);
        }

        $log = $this->recordFailure($op, $backup, $safety, $userId, $startedAt, $message);

        return new RestoreResult(false, $log, $safety, $changed, $message);
    }

    // =====================================================================
    // Archive inspection (steps 1 and the safety-backup check)
    // =====================================================================

    private function pathFor(BackupLog $backup): string
    {
        if (!$backup->isBackup()) {
            throw new RestoreException('Only a backup can be restored.');
        }
        if (!$backup->isSuccessful()) {
            throw new RestoreException('Only a successful backup can be restored.');
        }

        // Record must be successful, filename exact, real path inside the private folder, not a link.
        $path = $this->files->resolve($backup);

        if ($path === null) {
            throw new RestoreException('The backup file is missing from the server or cannot be used safely.');
        }

        return $path;
    }

    /**
     * @return array{manifest:array, tables:int, files:array<int,array>, file_count:int, dump_size:int, dump_crc:int, uncompressed:int}
     */
    private function inspectArchive(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RestoreException('The backup archive is damaged and cannot be opened.');
        }

        try {
            $count = $zip->numFiles;

            if ($count < 2 || $count > self::MAX_ENTRIES) {
                throw new RestoreException('The backup archive does not have the expected contents.');
            }

            $allowedDirs = $this->allowedDirectories();
            $root        = self::FILES_ROOT . '/';
            $limit       = max(1, (int) config('backup.restore.max_uncompressed_mb', 4096)) * 1048576;
            $files       = [];
            $perDir      = [];
            $total       = 0;
            $hasManifest = false;
            $dumpSize    = null;
            $dumpCrc     = 0;

            for ($i = 0; $i < $count; $i++) {
                $stat = $zip->statIndex($i);

                if ($stat === false) {
                    throw new RestoreException('The backup archive is damaged and cannot be read.');
                }

                $name = (string) $stat['name'];
                $this->assertSafeEntryName($name);

                if ($this->isSymlink($zip, $i)) {
                    throw new RestoreException('The backup archive contains a link, which is not allowed.');
                }

                $total += (int) $stat['size'];

                if ($total > $limit) {
                    throw new RestoreException('The backup archive is larger than restore allows.');
                }

                $isDir = str_ends_with($name, '/');

                if ($name === self::MANIFEST_ENTRY) {
                    $hasManifest = true;

                    continue;
                }
                if ($name === self::DUMP_ENTRY) {
                    $dumpSize = (int) $stat['size'];
                    $dumpCrc  = (int) $stat['crc'];

                    continue;
                }
                if ($isDir) {
                    if ($this->isAllowedDirectoryEntry($name, $allowedDirs)) {
                        continue;
                    }

                    throw new RestoreException('The backup archive has content that is not part of a backup.');
                }
                if (!str_starts_with($name, $root)) {
                    throw new RestoreException('The backup archive has content that is not part of a backup.');
                }

                $relative = substr($name, strlen($root));
                $parts    = explode('/', $relative);

                if (count($parts) < 2 || !in_array($parts[0], $allowedDirs, true)) {
                    throw new RestoreException('The backup archive has files in a location restore does not allow.');
                }

                foreach ($parts as $segment) {
                    if (str_starts_with($segment, '.')) {
                        throw new RestoreException('The backup archive contains a hidden file, which is not allowed.');
                    }
                }

                $fileName = strtolower(end($parts));
                foreach (array_slice(explode('.', $fileName), 1) as $extension) {
                    if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
                        throw new RestoreException('The backup archive contains a file type that is not allowed.');
                    }
                }

                $files[] = [
                    'name'     => $name,
                    'relative' => $relative,
                    'dir'      => $parts[0],
                    'size'     => (int) $stat['size'],
                    'crc'      => (int) $stat['crc'],
                ];
                $perDir[$parts[0]] = ($perDir[$parts[0]] ?? 0) + 1;
            }

            if (!$hasManifest) {
                throw new RestoreException('The backup does not contain its manifest.');
            }
            if ($dumpSize === null || $dumpSize <= 0) {
                throw new RestoreException('The backup does not contain a database dump.');
            }

            $manifest = $this->readManifest($zip, $allowedDirs, count($files), $perDir);
        } finally {
            $zip->close();
        }

        return [
            'manifest'     => $manifest,
            'tables'       => $manifest['database']['tables'],
            'files'        => $files,
            'file_count'   => count($files),
            'dump_size'    => $dumpSize,
            'dump_crc'     => $dumpCrc,
            'uncompressed' => $total,
        ];
    }

    /**
     * @param  array<int,string>  $allowedDirs
     * @param  array<string,int>  $perDir
     */
    private function readManifest(ZipArchive $zip, array $allowedDirs, int $fileCount, array $perDir): array
    {
        $stat = $zip->statName(self::MANIFEST_ENTRY);

        if ($stat === false || ($stat['size'] ?? 0) > self::MAX_MANIFEST_BYTES) {
            throw new RestoreException('The backup manifest is not valid.');
        }

        $manifest = json_decode((string) $zip->getFromName(self::MANIFEST_ENTRY), true);

        $database = is_array($manifest) ? ($manifest['database'] ?? null) : null;
        $filesInfo = is_array($manifest) ? ($manifest['files'] ?? null) : null;

        $valid = is_array($database) && is_array($filesInfo)
            && ($database['dump'] ?? null) === self::DUMP_ENTRY
            && is_int($database['tables'] ?? null) && $database['tables'] > 0
            && ($filesInfo['root'] ?? null) === self::FILES_ROOT
            && is_int($filesInfo['total'] ?? null) && $filesInfo['total'] >= 0
            && is_array($filesInfo['directories'] ?? null);

        if (!$valid) {
            throw new RestoreException('The backup manifest is not valid.');
        }

        foreach ($filesInfo['directories'] as $dir => $n) {
            if (!in_array($dir, $allowedDirs, true) || !is_int($n)) {
                throw new RestoreException('The backup manifest is not valid.');
            }
            if (($perDir[$dir] ?? 0) !== $n) {
                throw new RestoreException('The backup manifest does not match the files in the archive.');
            }
        }

        foreach ($perDir as $dir => $n) {
            if (!array_key_exists($dir, $filesInfo['directories'])) {
                throw new RestoreException('The backup manifest does not match the files in the archive.');
            }
        }

        if ($filesInfo['total'] !== $fileCount) {
            throw new RestoreException('The backup manifest does not match the files in the archive.');
        }

        return $manifest;
    }

    /**
     * Paths inside a ZIP are never trusted.
     */
    private function assertSafeEntryName(string $name): void
    {
        $unsafe = $name === ''
            || strlen($name) > 400
            || preg_match('/[\x00-\x1f\x7f]/', $name) === 1
            || str_contains($name, '\\')
            || str_contains($name, ':')          // C:\..., and NTFS alternate streams
            || str_contains($name, '//')
            || str_starts_with($name, '/');

        if (!$unsafe) {
            foreach (explode('/', rtrim($name, '/')) as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') {
                    $unsafe = true;

                    break;
                }
            }
        }

        if ($unsafe) {
            throw new RestoreException('The backup archive contains an unsafe file path, so it was rejected.');
        }
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = null;
        $attr  = null;

        if (!$zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return false;
        }

        return $opsys === ZipArchive::OPSYS_UNIX && ((($attr >> 16) & 0170000) === 0120000);
    }

    /**
     * @param  array<int,string>  $allowedDirs
     */
    private function isAllowedDirectoryEntry(string $name, array $allowedDirs): bool
    {
        $dir = rtrim($name, '/');

        if (in_array($dir, ['database', 'files', 'files/storage', 'files/storage/app', self::FILES_ROOT], true)) {
            return true;
        }

        if (str_starts_with($dir . '/', self::FILES_ROOT . '/')) {
            $first = explode('/', substr($dir, strlen(self::FILES_ROOT) + 1))[0];

            return in_array($first, $allowedDirs, true);
        }

        return false;
    }

    /**
     * The upload folders that may be restored come from CONFIG, never from the archive.
     *
     * @return array<int,string>
     */
    private function allowedDirectories(): array
    {
        return array_values(array_filter(
            array_map(fn ($d) => trim((string) $d, '/\\'), (array) config('backup.files.directories', [])),
            fn ($d) => $d !== ''
        ));
    }

    /**
     * @return array<int,string>
     */
    private function preserved(): array
    {
        $tables = array_map('strval', (array) config('backup.restore.preserve_tables', []));

        // The backup history must survive no matter how config is edited.
        return array_values(array_unique(array_merge(['backup_logs'], $tables)));
    }

    // =====================================================================
    // Temp folder + safe extraction (steps 6)
    // =====================================================================

    private function tempBase(): string
    {
        $disk = config('backup.disk', 'local');
        $root = rtrim((string) config("filesystems.disks.$disk.root"), '/\\');

        return BackupFilesystem::normalizePath($root . DIRECTORY_SEPARATOR . trim((string) config('backup.restore.temp_directory', 'restore-temp'), '/\\'));
    }

    private function makeTempDirectory(): string
    {
        $base = $this->tempBase();

        if (!is_dir($base) && !@mkdir($base, 0700, true) && !is_dir($base)) {
            throw new RestoreException('A temporary working folder could not be created, so nothing was restored.');
        }

        // The unpacked dump is plain text: keep the web server away from it even if the
        // project is ever deployed with storage/ inside the web root.
        BackupFilesystem::denyWebAccess($base);

        $realBase = realpath($base);

        foreach ([public_path(), storage_path('app/public')] as $publicPath) {
            $public = realpath($publicPath);

            if ($realBase === false || ($public !== false && str_starts_with($realBase . DIRECTORY_SEPARATOR, $public . DIRECTORY_SEPARATOR))) {
                throw new RestoreException('The temporary working folder is not private, so nothing was restored.');
            }
        }

        $dir = $realBase . DIRECTORY_SEPARATOR . 'restore-' . bin2hex(random_bytes(8));

        if (!@mkdir($dir, 0700) && !is_dir($dir)) {
            throw new RestoreException('A temporary working folder could not be created, so nothing was restored.');
        }

        return $dir;
    }

    /**
     * A crashed restore can leave a temp folder behind; remove old ones. Only
     * called while holding the restore lock, so it cannot touch a live one.
     */
    private function cleanupStaleTemp(): void
    {
        $base = $this->tempBase();

        if (!is_dir($base)) {
            return;
        }

        foreach (new \DirectoryIterator($base) as $item) {
            // isDir() follows links, so a link named like a temp folder must be
            // excluded explicitly: it is removed as a link, its target is never touched.
            if ($item->isDot() || !preg_match('/^restore-[0-9a-f]{16}$/D', $item->getFilename())) {
                continue;
            }

            if ($item->isLink()) {
                BackupFilesystem::deleteTree($item->getPathname());
            } elseif ($item->isDir() && $item->getMTime() < time() - 6 * 3600) {
                BackupFilesystem::deleteTree($item->getPathname());
            }
        }
    }

    private function assertDiskSpace(int $uncompressed): void
    {
        $base = dirname($this->tempBase());
        $free = is_dir($base) ? @disk_free_space($base) : false;

        if ($free !== false && $free < ($uncompressed * 2) + 52428800) {
            throw new RestoreException('There is not enough free disk space to restore this backup safely.');
        }
    }

    /**
     * Unpacks the dump and every file into $tmp. Each entry name was already
     * validated; each target is re-checked to sit inside $tmp, is created with
     * "x" mode (never through an existing file or link), and is verified by
     * size and CRC before it is trusted.
     *
     * @return array{dump:string, files:array<int,array>}
     */
    private function extract(string $path, array $info, string $tmp): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RestoreException('The backup archive is damaged and cannot be opened.');
        }

        try {
            $dump = $tmp . DIRECTORY_SEPARATOR . 'database.sql';
            $this->copyEntry($zip, self::DUMP_ENTRY, $dump, $info['dump_size'], $info['dump_crc']);

            $filesRoot = $tmp . DIRECTORY_SEPARATOR . 'files';

            if (!@mkdir($filesRoot, 0700) && !is_dir($filesRoot)) {
                throw new RestoreException('A temporary working folder could not be created, so nothing was restored.');
            }

            $realRoot  = realpath($filesRoot);
            $extracted = [];

            foreach ($info['files'] as $file) {
                $target = $realRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['relative']);
                $folder = dirname($target);

                if (!is_dir($folder) && !@mkdir($folder, 0700, true) && !is_dir($folder)) {
                    throw new RestoreException('A temporary working folder could not be created, so nothing was restored.');
                }

                $realFolder = realpath($folder);

                if ($realFolder === false || !str_starts_with($realFolder . DIRECTORY_SEPARATOR, $realRoot . DIRECTORY_SEPARATOR)) {
                    throw new RestoreException('The backup archive contains an unsafe file path, so it was rejected.');
                }

                $this->copyEntry($zip, $file['name'], $target, $file['size'], $file['crc']);
                $extracted[] = $file + ['source' => $target];
            }
        } finally {
            $zip->close();
        }

        return ['dump' => $dump, 'files' => $extracted];
    }

    private function copyEntry(ZipArchive $zip, string $entry, string $target, int $expectedSize, int $expectedCrc): void
    {
        $in = $zip->getStream($entry);

        if ($in === false) {
            throw new RestoreException('The backup archive is damaged and cannot be read.');
        }

        $out = @fopen($target, 'xb');

        if ($out === false) {
            fclose($in);

            throw new RestoreException('A temporary file could not be written, so nothing was restored.');
        }

        $bytes = 0;

        try {
            while (!feof($in)) {
                $chunk = fread($in, 1048576);

                if ($chunk === false) {
                    throw new RestoreException('The backup archive is damaged and cannot be read.');
                }

                $bytes += strlen($chunk);

                if ($bytes > $expectedSize) {
                    throw new RestoreException('The backup archive is damaged: a file is larger than declared.');
                }

                fwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        if ($bytes !== $expectedSize || hexdec((string) hash_file('crc32b', $target)) !== ($expectedCrc & 0xFFFFFFFF)) {
            throw new RestoreException('The backup archive is damaged: a file failed its integrity check.');
        }
    }

    // =====================================================================
    // File restore (step 10)
    // =====================================================================

    /**
     * Copies the unpacked files over the live upload folders.
     *
     * - Only the folders listed in config('backup.files.directories') can be written.
     * - Every destination is resolved and must stay inside its approved folder.
     * - Nothing is ever written through a symlink, and each file is written to a
     *   temp name and renamed into place, so a file is never left half-written.
     * - Files that exist now but are not in the backup are left alone (they are
     *   in the safety backup). Nothing is deleted.
     *
     * @param  array<int,array>  $files
     */
    private function restoreFiles(array $files): void
    {
        $base     = rtrim(Storage::disk(config('backup.files.disk', 'public'))->path(''), '/\\');
        $approved = [];

        foreach ($this->allowedDirectories() as $dir) {
            $folder = $base . DIRECTORY_SEPARATOR . $dir;

            if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
                throw new RestoreException('An upload folder could not be created during the restore.');
            }

            $real = realpath($folder);

            if ($real === false || is_link($folder)) {
                throw new RestoreException('An upload folder is not safe to restore into.');
            }

            $approved[$dir] = $real;
        }

        foreach ($files as $file) {
            $root = $approved[$file['dir']] ?? null;

            if ($root === null) {
                throw new RestoreException('The backup has files in a location restore does not allow.');
            }

            $rest        = substr($file['relative'], strlen($file['dir']) + 1);
            $destination = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rest);
            $folder      = dirname($destination);

            if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
                throw new RestoreException('An upload folder could not be created during the restore.');
            }

            $realFolder = realpath($folder);

            if ($realFolder === false || !str_starts_with($realFolder . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
                throw new RestoreException('A file would have been restored outside its upload folder, so the restore was stopped.');
            }

            if (is_link($destination)) {
                throw new RestoreException('A file would have been restored through a link, so the restore was stopped.');
            }

            $temp = $realFolder . DIRECTORY_SEPARATOR . '.restore-' . bin2hex(random_bytes(6)) . '.tmp';

            if (!@copy($file['source'], $temp)) {
                @unlink($temp);

                throw new RestoreException('An uploaded file could not be restored.');
            }

            @chmod($temp, 0644);

            if (!@rename($temp, $destination)) {
                @unlink($temp);

                throw new RestoreException('An uploaded file could not be restored.');
            }
        }
    }

    // =====================================================================
    // Verification (step 11)
    // =====================================================================

    /**
     * @param  array<int,string>  $restorableTables
     * @param  array<int,array>   $files
     */
    private function verify(array $restorableTables, array $files): void
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            throw new RestoreException('The database could not be checked after the restore.', 0, $e);
        }

        $live    = $this->liveTables();
        $missing = array_values(array_filter($restorableTables, fn (string $t) => !isset($live[strtolower($t)])));

        if ($missing !== []) {
            Log::error('Backup restore: tables missing after restore.', ['tables' => $missing]);

            throw new RestoreException('The restored database is missing tables that the backup contained.');
        }

        foreach ($restorableTables as $table) {
            try {
                DB::table($table)->limit(1)->get();
            } catch (Throwable $e) {
                throw new RestoreException('A restored table could not be read.', 0, $e);
            }
        }

        $base = rtrim(Storage::disk(config('backup.files.disk', 'public'))->path(''), '/\\');

        foreach ($this->allowedDirectories() as $dir) {
            if (!is_dir($base . DIRECTORY_SEPARATOR . $dir)) {
                throw new RestoreException('An upload folder is missing after the restore.');
            }
        }

        foreach ($files as $file) {
            $restored = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['relative']);

            if (!is_file($restored)
                || filesize($restored) !== $file['size']
                || hexdec((string) hash_file('crc32b', $restored)) !== ($file['crc'] & 0xFFFFFFFF)) {
                throw new RestoreException('A restored file did not match the backup.');
            }
        }
    }

    /**
     * @return array<string,bool> lower-case table name => true
     */
    private function liveTables(): array
    {
        $connection = config('database.default');
        $config     = config("database.connections.$connection");
        $schema     = in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true) ? ($config['database'] ?? null) : null;

        $names = [];

        // On MySQL, getTables() with no schema lists EVERY database on the server.
        foreach (Schema::connection($connection)->getTables($schema) as $row) {
            $names[strtolower((string) $row['name'])] = true;
        }

        return $names;
    }

    private function clearCaches(): void
    {
        try {
            Artisan::call('cache:clear');
        } catch (Throwable $e) {
            Log::warning('Backup restore: the cache could not be cleared.', ['error' => Str::limit($e->getMessage(), 150, '...')]);
        }
    }

    /**
     * backup_logs keeps its rows during a restore, but the restored `users`
     * table may not contain the admins those rows point at.
     */
    private function normalizeBackupLogs(): void
    {
        BackupLog::whereNotNull('created_by')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users')->whereColumn('users.id', 'backup_logs.created_by'))
            ->update(['created_by' => null]);
    }

    // =====================================================================
    // Logging
    // =====================================================================

    private function logFailure(BackupLog $backup, Throwable $e): void
    {
        $previous = $e->getPrevious();

        Log::error('Backup restore failed.', [
            'backup_log_id' => $backup->id,
            'exception'     => get_class($e),
            'message'       => BackupFilesystem::scrub($e->getMessage()),
            'previous'      => $previous ? BackupFilesystem::scrub($previous->getMessage()) : null,
        ]);
    }

    /**
     * Writes the "failed" restore record. The database may have been left in a
     * bad state, so this tries hard but can never throw.
     */
    private function recordFailure(?BackupLog $op, BackupLog $backup, ?BackupLog $safety, ?int $userId, Carbon $startedAt, string $message): BackupLog
    {
        $result = [
            'status'        => BackupLog::STATUS_FAILED,
            'completed_at'  => now(),
            'error_message' => Str::limit($message, 1500, '...'),
        ];

        $base = [
            'filename'   => $backup->filename,
            'type'       => BackupLog::TYPE_RESTORE,
            'started_at' => $startedAt,
            'details'    => $safety ? 'Safety backup: ' . $safety->filename : null,
        ];

        try {
            if ($op !== null) {
                $op->update($result);

                return $op;
            }

            return BackupLog::create($base + $result + ['created_by' => $userId]);
        } catch (Throwable $first) {
            try {
                // e.g. the users table is not there right now: log it without the user.
                return BackupLog::create($base + $result + ['created_by' => null]);
            } catch (Throwable $second) {
                Log::error('Backup restore: could not record the failure.', ['error' => Str::limit($second->getMessage(), 150, '...')]);

                return new BackupLog($base + $result);
            }
        }
    }
}
