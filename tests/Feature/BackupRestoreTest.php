<?php

namespace Tests\Feature;

use App\Exceptions\RestoreException;
use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupDatabaseImporter;
use App\Services\BackupFileService;
use App\Services\BackupRestoreService;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\RestoreGuard;
use App\Services\RestoreResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Backup & Recovery — Restore (Phase 4).
 *
 * Everything runs on throw-away folders (backup folder, restore temp folder,
 * and a temporary "public disk"), never on real backups or real uploads.
 * The one thing replaced by a stand-in is the SQL import itself, because the
 * test database is SQLite: the importer's checks are tested separately in
 * BackupDatabaseImporterTest, and real execution against MySQL/MariaDB was
 * run against a scratch database outside PHPUnit.
 */
class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    private string $dirName;

    private string $dirPath;

    private string $tempName;

    private string $tempPath;

    private string $publicRoot;

    /** @var array<int,string> order in which the stand-ins were called */
    private array $order = [];

    private ?bool $blockedWhileSafetyRan = null;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dirName = 'testing-backups-' . bin2hex(random_bytes(4));
        $this->dirPath = storage_path('app/private/' . $this->dirName);
        mkdir($this->dirPath, 0700, true);

        $this->tempName = 'testing-restore-' . bin2hex(random_bytes(4));
        $this->tempPath = storage_path('app/private/' . $this->tempName);

        $this->publicRoot = storage_path('app/testing-public-' . bin2hex(random_bytes(4)));
        mkdir($this->publicRoot, 0755, true);

        config([
            'backup.directory'                 => $this->dirName,
            'backup.restore.temp_directory'    => $this->tempName,
            'filesystems.disks.public.root'    => $this->publicRoot,
        ]);
        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dirPath);
        File::deleteDirectory($this->tempPath);
        File::deleteDirectory($this->publicRoot);

        parent::tearDown();
    }

    // ================================================================ helpers

    private function makeUser(string $role): User
    {
        $user = User::create([
            'first_name'      => ucfirst(strtolower($role)),
            'last_name'       => 'Tester',
            'birthdate'       => '1995-01-01',
            'gender'          => 'Male',
            'civil_status'    => 'Single',
            'address'         => 'Sample St',
            'contact_number'  => '0917' . random_int(1000000, 9999999),
            'username'        => strtolower($role) . random_int(100000, 999999),
            'email'           => strtolower($role) . random_int(1000, 9999) . '@test.com',
            'password'        => bcrypt('password'),
            'role'            => $role,
            'approval_status' => 'Approved',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function dump(array $tables): string
    {
        $sql = "-- MySQL dump 10.13\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n"
            . "/*!50503 SET NAMES utf8mb4 */;\n/*!40103 SET TIME_ZONE='+00:00' */;\n"
            . "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n\n";

        foreach ($tables as $t) {
            $sql .= "DROP TABLE IF EXISTS `$t`;\n"
                . "CREATE TABLE `$t` (\n  `id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n"
                . "LOCK TABLES `$t` WRITE;\nINSERT INTO `$t` VALUES (1),(2);\nUNLOCK TABLES;\n";
        }

        return $sql . "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n\n-- Dump completed on 2026-09-30  9:09:18\n";
    }

    /** The manifest an archive built by makeArchive() would carry. */
    private function baseManifest(array $files, int $tables): array
    {
        $dirs = ['avatars' => 0, 'valid_ids' => 0, 'laboratory_results' => 0, 'signatures' => 0];

        foreach (array_keys($files) as $relative) {
            $dirs[explode('/', $relative)[0]] = ($dirs[explode('/', $relative)[0]] ?? 0) + 1;
        }

        return [
            'application' => 'Laravel',
            'backup_type' => 'manual',
            'database'    => ['driver' => 'mysql', 'dump' => 'database/database.sql', 'tables' => $tables],
            'files'       => ['root' => 'files/storage/app/public', 'total' => count($files), 'directories' => $dirs],
        ];
    }

    /**
     * Builds a real archive. Options: files, tables, dump, manifest (array|string),
     * omit (['dump','manifest']), entries (raw extra name => content), symlink (name).
     */
    private function makeArchive(string $filename, array $o = []): string
    {
        $path   = $this->dirPath . '/' . $filename;
        $files  = $o['files'] ?? ['avatars/new.jpg' => 'NEW-AVATAR', 'valid_ids/id1.png' => 'NEW-ID'];
        $tables = $o['tables'] ?? ['users', 'patients'];
        $omit   = $o['omit'] ?? [];

        // Extra entries that sit under an upload folder are counted in the manifest, so a hostile
        // entry can only be rejected by the check that is SUPPOSED to reject it, never by a count mismatch.
        $counted = $files;
        foreach (array_keys($o['entries'] ?? []) as $entry) {
            $prefix = 'files/storage/app/public/';
            if (str_starts_with($entry, $prefix)) {
                $relative = substr($entry, strlen($prefix));
                $parts    = explode('/', $relative);
                if (count($parts) >= 2 && in_array($parts[0], ['avatars', 'valid_ids', 'laboratory_results', 'signatures'], true)) {
                    $counted[$relative] = 1;
                }
            }
        }

        $manifest = $o['manifest'] ?? $this->baseManifest($counted, count($tables));

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if (!in_array('dump', $omit, true)) {
            $zip->addFromString('database/database.sql', $o['dump'] ?? $this->dump($tables));
        }
        if (!in_array('manifest', $omit, true)) {
            $zip->addFromString('manifest.json', is_string($manifest) ? $manifest : json_encode($manifest));
        }
        foreach ($files as $relative => $content) {
            $zip->addFromString('files/storage/app/public/' . $relative, $content);
        }
        foreach (($o['entries'] ?? []) as $name => $content) {
            $zip->addFromString($name, $content);
        }
        if (!empty($o['store'])) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zip->setCompressionIndex($i, ZipArchive::CM_STORE);
            }
        }
        if (!empty($o['symlink'])) {
            $zip->addFromString($o['symlink'], 'target');
            $zip->setExternalAttributesName($o['symlink'], ZipArchive::OPSYS_UNIX, 0120777 << 16);
        }

        $zip->close();

        return $path;
    }

    private function nextName(): string
    {
        return sprintf('clinic_backup_2026-10-01_10-00-%02d.zip', ++$this->seq);
    }

    /** A successful backup row + real archive. */
    private function makeBackup(array $archive = [], array $row = []): BackupLog
    {
        $filename = $row['filename'] ?? $this->nextName();
        $path     = $this->makeArchive($filename, $archive);

        return BackupLog::create(array_merge([
            'filename'     => $filename,
            'type'         => BackupLog::TYPE_MANUAL,
            'size'         => filesize($path),
            'status'       => BackupLog::STATUS_SUCCESS,
            'started_at'   => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ], $row));
    }

    private function restoreService(): BackupRestoreService
    {
        return app(BackupRestoreService::class);
    }

    private function projectFingerprint(): array
    {
        $files = ['.env.example', 'composer.json', 'routes/web.php', 'app/Services/BackupService.php', 'app/Services/BackupRestoreService.php', 'config/app.php'];

        if (is_file(base_path('.env'))) {
            $files[] = '.env';
        }

        $hashes = [];
        foreach ($files as $file) {
            $hashes[$file] = md5_file(base_path($file));
        }

        return $hashes;
    }

    /** Safety backup stand-in: records order and returns a REAL pre-restore archive row. */
    private function fakeSafetyBackup(?int $userId, ?callable $after = null, bool $success = true): void
    {
        $this->mock(BackupService::class, function (MockInterface $m) use ($userId, $after, $success) {
            $m->shouldReceive('create')
                ->once()
                ->with(BackupLog::TYPE_PRE_RESTORE, $userId)
                ->andReturnUsing(function () use ($after, $success) {
                    $this->order[]               = 'safety-backup';
                    $this->blockedWhileSafetyRan = app(RestoreGuard::class)->isRestoreRunning();

                    if (!$success) {
                        return BackupLog::create([
                            'filename'      => 'clinic_backup_2026-10-01_11-00-00.zip',
                            'type'          => BackupLog::TYPE_PRE_RESTORE,
                            'status'        => BackupLog::STATUS_FAILED,
                            'started_at'    => now(),
                            'completed_at'  => now(),
                            'error_message' => 'Backup failed.',
                        ]);
                    }

                    $log = $this->makeBackup([], ['filename' => 'clinic_backup_2026-10-01_11-00-00.zip', 'type' => BackupLog::TYPE_PRE_RESTORE]);

                    if ($after) {
                        $after($log);
                    }

                    return $log;
                });
        });
    }

    /** SQL import stand-in (the real one needs MySQL). Returns the captured arguments. */
    private function fakeImporter(?callable $during = null, ?\Throwable $throw = null): \stdClass
    {
        $seen = new \stdClass();
        $seen->calls = 0;

        $this->partialMock(BackupDatabaseImporter::class, function (MockInterface $m) use ($seen, $during, $throw) {
            $m->shouldReceive('import')->andReturnUsing(function (string $dump, array $preserve) use ($seen, $during, $throw) {
                $this->order[]     = 'import';
                $seen->calls++;
                $seen->dumpExisted = is_file($dump);
                $seen->dumpText    = is_file($dump) ? file_get_contents($dump) : null;
                $seen->preserve    = $preserve;

                if ($during) {
                    $during();
                }
                if ($throw) {
                    throw $throw;
                }

                return [];
            });
        });

        return $seen;
    }

    private function tempFoldersLeft(): array
    {
        // Extraction folders only: the deny-web-access file that protects the base folder stays.
        return is_dir($this->tempPath)
            ? array_values(array_filter(scandir($this->tempPath), fn ($name) => str_starts_with($name, 'restore-')))
            : [];
    }

    private function assertNothingChangedOnDisk(): void
    {
        $this->assertSame([], glob($this->publicRoot . '/*') ?: [], 'no upload folder may be written');
        $this->assertSame([], $this->tempFoldersLeft());
        $this->assertFileDoesNotExist($this->dirPath . '/' . RestoreGuard::MARKER);
    }

    // ================================================================ 1-3 access control

    public function test_guests_are_sent_to_login_for_restore(): void
    {
        $backup = $this->makeBackup();

        $this->get(route('admin.backups.restore', $backup))->assertRedirect(route('login'));
        $this->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE'])->assertRedirect(route('login'));
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_open_or_submit_restore(string $role): void
    {
        $this->mock(BackupRestoreService::class, fn (MockInterface $m) => $m->shouldNotReceive('restore'));

        $backup = $this->makeBackup();
        $user   = $this->makeUser($role);

        $this->actingAs($user)->get(route('admin.backups.restore', $backup))->assertForbidden();
        $this->actingAs($user)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE'])->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return ['staff' => ['Staff'], 'doctor' => ['Doctor'], 'patient' => ['Patient']];
    }

    public function test_admin_sees_the_confirmation_page_with_everything_about_the_backup(): void
    {
        $backup = $this->makeBackup();

        $this->actingAs($this->makeUser('Admin'))
            ->get(route('admin.backups.restore', $backup))
            ->assertOk()
            ->assertSee('Restoring this backup will replace current application data.')
            ->assertSee($backup->filename)
            ->assertSee($backup->started_at->format('M d, Y h:i A'))
            ->assertSee('Manual')
            ->assertSee($backup->formatted_size)
            ->assertSee('Database tables')
            ->assertSeeInOrder(['Uploaded files', '2'])
            ->assertSee('Success')
            ->assertSee('Type')
            ->assertSee('RESTORE');
    }

    // ================================================================ 4-8 validation

    public function test_a_failed_backup_cannot_be_restored(): void
    {
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $importer = $this->fakeImporter();

        $failed = $this->makeBackup([], ['status' => BackupLog::STATUS_FAILED, 'error_message' => 'x']);
        $admin  = $this->makeUser('Admin');

        $this->actingAs($admin)->get(route('admin.backups.restore', $failed))
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('error');

        $result = $this->restoreService()->restore($failed, $admin->id);

        $this->assertFalse($result->success);
        $this->assertFalse($result->changed);
        $this->assertNull($result->safetyBackup);
        $this->assertStringContainsString('successful backup', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertNothingChangedOnDisk();
    }

    public function test_a_restore_record_is_not_a_backup_and_cannot_be_restored_or_downloaded(): void
    {
        $backup = $this->makeBackup();
        $record = BackupLog::create([
            'filename' => $backup->filename, 'type' => BackupLog::TYPE_RESTORE, 'status' => BackupLog::STATUS_SUCCESS,
            'started_at' => now(), 'completed_at' => now(),
        ]);
        $admin = $this->makeUser('Admin');

        $this->assertNull(app(BackupFileService::class)->resolve($record), 'a restore record never resolves to the archive');

        $this->actingAs($admin)->get(route('admin.backups.restore', $record))->assertRedirect(route('admin.backups.index'))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('admin.backups.download', $record))->assertNotFound();
    }

    public function test_a_pre_restore_safety_backup_can_itself_be_restored(): void
    {
        $safety = $this->makeBackup([], ['type' => BackupLog::TYPE_PRE_RESTORE]);

        $this->actingAs($this->makeUser('Admin'))
            ->get(route('admin.backups.restore', $safety))
            ->assertOk()
            ->assertSee('Pre-restore');
    }

    public function test_a_missing_backup_file_is_rejected_and_nothing_happens(): void
    {
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $importer = $this->fakeImporter();

        $backup = $this->makeBackup();
        unlink($this->dirPath . '/' . $backup->filename);

        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('missing from the server', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertSame(BackupLog::STATUS_FAILED, $result->log->status);
        $this->assertNothingChangedOnDisk();
    }

    public function test_a_corrupted_zip_is_rejected(): void
    {
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $importer = $this->fakeImporter();

        $backup = $this->makeBackup();
        file_put_contents($this->dirPath . '/' . $backup->filename, random_bytes(4096));

        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('damaged', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertNothingChangedOnDisk();
    }

    public function test_a_truncated_zip_is_rejected(): void
    {
        $backup = $this->makeBackup(['files' => ['avatars/a.jpg' => str_repeat('A', 5000)]]);
        $path   = $this->dirPath . '/' . $backup->filename;
        file_put_contents($path, substr(file_get_contents($path), 0, (int) (filesize($path) * 0.6)));

        $this->expectException(RestoreException::class);

        $this->restoreService()->inspect($backup);
    }

    #[DataProvider('badManifests')]
    public function test_an_invalid_manifest_is_rejected(\Closure $mutate): void
    {
        $files    = ['avatars/new.jpg' => 'x', 'valid_ids/id1.png' => 'y'];
        $manifest = $mutate($this->baseManifest($files, 2));

        $backup = $this->makeBackup(['files' => $files, 'manifest' => $manifest]);

        $this->expectException(RestoreException::class);
        $this->expectExceptionMessageMatches('/manifest/i');

        $this->restoreService()->inspect($backup);
    }

    public static function badManifests(): array
    {
        return [
            'not json'                  => [fn () => 'this is not json {'],
            'empty object'              => [fn () => '{}'],
            'a json list'               => [fn () => '[1,2,3]'],
            'dump path is different'    => [function (array $m) { $m['database']['dump'] = '../../etc/passwd'; return $m; }],
            'zero tables'               => [function (array $m) { $m['database']['tables'] = 0; return $m; }],
            'tables is not a number'    => [function (array $m) { $m['database']['tables'] = 'many'; return $m; }],
            'files root is different'   => [function (array $m) { $m['files']['root'] = 'files'; return $m; }],
            'file total is wrong'       => [function (array $m) { $m['files']['total'] = 99; return $m; }],
            'per-folder count is wrong' => [function (array $m) { $m['files']['directories']['avatars'] = 7; return $m; }],
            'folder not allowed'        => [function (array $m) { $m['files']['directories']['config'] = 0; return $m; }],
            'no files section'          => [function (array $m) { unset($m['files']); return $m; }],
            'no database section'       => [function (array $m) { unset($m['database']); return $m; }],
        ];
    }

    public function test_a_missing_manifest_is_rejected(): void
    {
        $backup = $this->makeBackup(['omit' => ['manifest']]);

        $this->expectException(RestoreException::class);
        $this->expectExceptionMessage('manifest');

        $this->restoreService()->inspect($backup);
    }

    public function test_a_missing_database_dump_is_rejected(): void
    {
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $importer = $this->fakeImporter();

        $backup = $this->makeBackup(['omit' => ['dump']]);
        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('database dump', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertNothingChangedOnDisk();
    }

    public function test_a_dump_with_dangerous_sql_is_rejected_before_anything_is_replaced(): void
    {
        $importer = $this->fakeImporter();
        $this->fakeSafetyBackup(null);

        $bad    = $this->dump(['users', 'patients']) . "DROP DATABASE clinic;\n";
        // put the extra line before the completion marker so only the allowlist can catch it
        $bad    = str_replace("-- Dump completed", "DROP DATABASE clinic;\n-- Dump completed", $this->dump(['users', 'patients']));
        $backup = $this->makeBackup(['dump' => $bad]);

        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success);
        $this->assertFalse($result->changed, 'rejected during preparation: nothing was replaced');
        $this->assertSame(0, $importer->calls);
        $this->assertSame([], glob($this->publicRoot . '/*') ?: []);
        $this->assertSame([], $this->tempFoldersLeft(), 'temp folder cleaned');
    }

    public function test_a_dump_that_does_not_match_the_manifest_table_count_is_rejected(): void
    {
        $importer = $this->fakeImporter();
        $this->fakeSafetyBackup(null);

        $files    = ['avatars/new.jpg' => 'x'];
        $manifest = $this->baseManifest($files, 5); // dump really has 2 tables
        $backup   = $this->makeBackup(['files' => $files, 'manifest' => $manifest]);

        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('does not match the backup manifest', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertSame([], glob($this->publicRoot . '/*') ?: []);
    }

    // ================================================================ 9-10 path safety

    #[DataProvider('hostileEntries')]
    public function test_hostile_archive_entries_are_rejected_by_the_intended_check(string $name, string $expectedMessage): void
    {
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $importer    = $this->fakeImporter();
        $fingerprint = $this->projectFingerprint();

        $backup = $this->makeBackup(['entries' => [$name => 'MALICIOUS']]);
        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success, "'$name' must be rejected");
        $this->assertStringContainsString($expectedMessage, (string) $result->message, "'$name' was rejected, but not by the check meant to catch it");
        $this->assertFalse($result->changed);
        $this->assertSame(0, $importer->calls);
        $this->assertNothingChangedOnDisk();
        $this->assertSame($fingerprint, $this->projectFingerprint());
        foreach ([storage_path('app/private/.env'), storage_path('app/.env'), storage_path('.env'), base_path('evil.txt'), public_path('evil.txt')] as $path) {
            $this->assertFileDoesNotExist($path);
        }
    }

    public static function hostileEntries(): array
    {
        $unsafe = 'unsafe file path';
        $outside = 'not part of a backup';
        $place = 'location restore does not allow';

        return [
            'parent traversal to .env'          => ['../../.env', $unsafe],
            'deep traversal'                    => ['../../../storage/logs/laravel.log', $unsafe],
            'traversal below the files root'    => ['files/storage/app/public/../../../.env', $unsafe],
            'traversal inside an upload folder' => ['files/storage/app/public/avatars/../../../.env', $unsafe],
            'traversal in the middle'           => ['files/storage/app/public/avatars/a/../../../../evil.txt', $unsafe],
            'absolute linux path'               => ['/etc/passwd', $unsafe],
            'absolute path to the project'      => ['/var/www/html/.env', $unsafe],
            'absolute windows path'             => ['C:\\Windows\\System32\\evil.dll', $unsafe],
            'windows path with slashes'         => ['C:/inetpub/evil.txt', $unsafe],
            'backslash traversal'               => ['files\\storage\\app\\public\\..\\..\\..\\.env', $unsafe],
            'unc path'                          => ['\\\\server\\share\\evil.txt', $unsafe],
            'colon / alternate data stream'     => ['files/storage/app/public/avatars/a.jpg:evil.php', $unsafe],
            'double slash'                      => ['files/storage/app/public//avatars/x.jpg', $unsafe],
            'dot segment'                       => ['files/storage/app/public/./avatars/x.jpg', $unsafe],
            'a file outside files/'             => ['app/Models/User.php', $outside],
            'the env file at the top'           => ['.env', $outside],
            'an unknown top-level file'         => ['extra.txt', $outside],
            'source code path under files'      => ['files/app/Http/Kernel.php', $outside],
            'files directly in the public root' => ['files/storage/app/public/.env', $place],
            'a file with no upload folder'      => ['files/storage/app/public/evil.txt', $place],
            'a folder that is not an upload folder' => ['files/storage/app/public/config/app.php', $place],
            'a hidden file in an upload folder' => ['files/storage/app/public/avatars/.htaccess', 'hidden file'],
            'a hidden folder'                   => ['files/storage/app/public/avatars/.git/config', 'hidden file'],
            'a php file'                        => ['files/storage/app/public/avatars/shell.php', 'file type'],
            'a php file in upper case'          => ['files/storage/app/public/avatars/SHELL.PHP', 'file type'],
            'a phtml file'                      => ['files/storage/app/public/valid_ids/x.phtml', 'file type'],
            'a php file hiding behind .jpg'     => ['files/storage/app/public/avatars/photo.php.jpg', 'file type'],
            'a phar file'                       => ['files/storage/app/public/signatures/x.phar', 'file type'],
            'an .ini file'                      => ['files/storage/app/public/avatars/php.ini', 'file type'],
        ];
    }

    public function test_a_symlink_entry_is_rejected(): void
    {
        $importer = $this->fakeImporter();

        $backup = $this->makeBackup(['symlink' => 'files/storage/app/public/avatars/link']);
        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('link', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertNothingChangedOnDisk();
    }

    public function test_an_archive_that_would_unpack_to_more_than_the_limit_is_rejected(): void
    {
        config(['backup.restore.max_uncompressed_mb' => 1]);

        $backup = $this->makeBackup(['files' => ['avatars/huge.bin' => str_repeat("\0", 3 * 1048576)]]);

        $this->expectException(RestoreException::class);
        $this->expectExceptionMessage('larger than restore allows');

        $this->restoreService()->inspect($backup);
    }

    public function test_a_file_that_fails_its_integrity_check_stops_the_restore_before_any_change(): void
    {
        $importer = $this->fakeImporter();
        $this->fakeSafetyBackup(null);

        // Stored uncompressed, so one flipped byte inside the file's data is a genuine corruption
        // that only the checksum can catch (the ZIP's headers stay perfectly readable).
        $backup = $this->makeBackup(['store' => true, 'files' => ['avatars/a.jpg' => str_repeat('ABCD', 500)]]);
        $path   = $this->dirPath . '/' . $backup->filename;

        $bytes = file_get_contents($path);
        $pos   = strpos($bytes, str_repeat('ABCD', 20));
        $this->assertNotFalse($pos, 'the file data must be readable inside the stored archive');
        $bytes[$pos + 5] = 'Z';
        file_put_contents($path, $bytes);

        $result = $this->restoreService()->restore($backup, null);

        $this->assertFalse($result->success);
        $this->assertFalse($result->changed, 'caught while preparing, before the database or any upload was touched');
        $this->assertStringContainsString('integrity check', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertSame(['safety-backup'], $this->order, 'found after the safety backup, before the destructive phase');
        $this->assertSame([], glob($this->publicRoot . '/*') ?: []);
        $this->assertSame([], $this->tempFoldersLeft());
    }

    // ================================================================ 11 confirmation

    #[DataProvider('wrongConfirmations')]
    public function test_restore_needs_the_word_typed_exactly(?string $typed): void
    {
        $this->mock(BackupRestoreService::class, fn (MockInterface $m) => $m->shouldNotReceive('restore'));

        $backup = $this->makeBackup();
        $data   = $typed === null ? [] : ['confirmation' => $typed];

        $this->actingAs($this->makeUser('Admin'))
            ->post(route('admin.backups.restore.run', $backup), $data)
            ->assertRedirect(route('admin.backups.restore', $backup))
            ->assertSessionHas('error');
    }

    public static function wrongConfirmations(): array
    {
        return [
            'nothing'      => [null],
            'empty'        => [''],
            'lower case'   => ['restore'],
            'mixed case'   => ['Restore'],
            'typo'         => ['RESTOR'],
            'extra text'   => ['RESTORE NOW'],
            'yes'          => ['yes'],
            'the filename' => ['clinic_backup_2026-10-01_10-00-01.zip'],
        ];
    }

    public function test_the_exact_word_starts_the_restore_and_shows_the_success_message(): void
    {
        $backup = $this->makeBackup();
        $admin  = $this->makeUser('Admin');
        $safety = BackupLog::create(['filename' => 'clinic_backup_x.zip', 'type' => 'pre-restore', 'status' => 'success', 'started_at' => now()]);

        $this->mock(BackupRestoreService::class, function (MockInterface $m) use ($backup, $admin, $safety) {
            $m->shouldReceive('restore')->once()
                ->with(Mockery::on(fn ($b) => $b->id === $backup->id), $admin->id)
                ->andReturn(new RestoreResult(true, $safety, $safety, true));
        });

        $this->actingAs($admin)
            ->post(route('admin.backups.restore.run', $backup), ['confirmation' => ' RESTORE '])
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('success', 'Backup restored successfully.');
    }

    public function test_failure_messages_are_safe_and_honest(): void
    {
        $backup = $this->makeBackup();
        $admin  = $this->makeUser('Admin');
        $log    = BackupLog::create(['filename' => 'x.zip', 'type' => 'restore', 'status' => 'failed', 'started_at' => now()]);
        $safety = BackupLog::create(['filename' => 'clinic_backup_s.zip', 'type' => 'pre-restore', 'status' => 'success', 'started_at' => now()]);

        $cases = [
            'partly restored'   => [new RestoreResult(false, $log, $safety, true, 'SQLSTATE[HY000] password=hunter2 in /var/www/app.php'), ['pre-restore safety backup has been preserved', 'partly restored']],
            'nothing changed'   => [new RestoreResult(false, $log, $safety, false, 'A backup started while preparing'), ['pre-restore safety backup has been preserved', 'Nothing was changed']],
            'stopped before it' => [new RestoreResult(false, $log, null, false, 'The backup file is missing from the server.'), ['Backup restore failed.', 'missing from the server', 'Nothing was changed']],
        ];

        foreach ($cases as $name => [$result, $expected]) {
            $this->mock(BackupRestoreService::class, fn (MockInterface $m) => $m->shouldReceive('restore')->once()->andReturn($result));

            $response = $this->actingAs($admin)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE']);
            $response->assertRedirect(route('admin.backups.index'));

            $flash = session('error');
            foreach ($expected as $needle) {
                $this->assertStringContainsString($needle, $flash, $name);
            }
            // The controller only ever prints the service's OWN safe message when there was no safety backup;
            // once a safety backup exists it prints fixed text, never the reason.
            if ($name !== 'stopped before it') {
                $this->assertStringNotContainsString('hunter2', $flash);
                $this->assertStringNotContainsString('/var/www', $flash);
            }
        }
    }

    // ================================================================ 12-13, 16 safety backup + success flow

    public function test_a_successful_restore_does_everything_in_the_right_order(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup(['files' => [
            'avatars/new.jpg'     => 'FROM-BACKUP',
            'avatars/old.jpg'     => 'RESTORED-OLD',
            'valid_ids/id1.png'   => 'ID-FROM-BACKUP',
            'signatures/sig/a.png' => 'SIG-NESTED',
        ]]);

        // Current state: one file the backup will overwrite, one it doesn't know about.
        mkdir($this->publicRoot . '/avatars', 0755, true);
        file_put_contents($this->publicRoot . '/avatars/new.jpg', 'CURRENT-VERSION');
        file_put_contents($this->publicRoot . '/avatars/added-after-backup.jpg', 'KEEP-ME');

        $this->fakeSafetyBackup($admin->id);
        $seen = $this->fakeImporter();

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertFalse(!$result->changed);

        // 12: the safety backup is made FIRST, while users are already blocked, then the database is replaced.
        $this->assertSame(['safety-backup', 'import'], $this->order);
        $this->assertTrue($this->blockedWhileSafetyRan, 'users are blocked before the safety backup, so it matches what is replaced');

        // The dump handed to the importer really came out of the archive; protected tables are passed through.
        $this->assertTrue($seen->dumpExisted);
        $this->assertStringContainsString('CREATE TABLE `users`', $seen->dumpText);
        foreach (['backup_logs', 'sessions', 'jobs', 'cache'] as $table) {
            $this->assertContains($table, $seen->preserve);
        }

        // Files: overwritten, restored, nested folder created, unrelated file untouched.
        $this->assertSame('FROM-BACKUP', file_get_contents($this->publicRoot . '/avatars/new.jpg'));
        $this->assertSame('RESTORED-OLD', file_get_contents($this->publicRoot . '/avatars/old.jpg'));
        $this->assertSame('ID-FROM-BACKUP', file_get_contents($this->publicRoot . '/valid_ids/id1.png'));
        $this->assertSame('SIG-NESTED', file_get_contents($this->publicRoot . '/signatures/sig/a.png'));
        $this->assertSame('KEEP-ME', file_get_contents($this->publicRoot . '/avatars/added-after-backup.jpg'));
        $this->assertSame([], glob($this->publicRoot . '/avatars/.restore-*'), 'no temp files left next to uploads');

        // 19: logged. The record names the selected backup and the safety backup.
        $record = BackupLog::where('type', BackupLog::TYPE_RESTORE)->sole();
        $this->assertSame(BackupLog::STATUS_SUCCESS, $record->status);
        $this->assertSame($backup->filename, $record->filename);
        $this->assertSame($admin->id, $record->created_by);
        $this->assertNotNull($record->completed_at);
        $this->assertNull($record->error_message);
        $this->assertStringContainsString('clinic_backup_2026-10-01_11-00-00.zip', $record->details);

        // Nothing is deleted: selected backup + safety backup remain, both on disk and in the log.
        $this->assertFileExists($this->dirPath . '/' . $backup->filename);
        $this->assertFileExists($this->dirPath . '/clinic_backup_2026-10-01_11-00-00.zip');
        $safety = BackupLog::where('type', BackupLog::TYPE_PRE_RESTORE)->sole();
        $this->assertStringContainsString('Safety backup made before restoring ' . $backup->filename, $safety->details);

        // 16: temp cleaned; locks and marker released.
        $this->assertSame([], $this->tempFoldersLeft());
        $this->assertFileDoesNotExist($this->dirPath . '/' . RestoreGuard::MARKER);
        $guard = app(RestoreGuard::class);
        $this->assertFalse($guard->isRestoreRunning());
        foreach ([$guard->acquireRestoreLock(), $guard->acquireBackupLock()] as $lock) {
            $this->assertNotNull($lock, 'lock is free again');
            $guard->release($lock);
        }
    }

    public function test_restore_stops_when_the_safety_backup_fails(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();
        $this->fakeSafetyBackup($admin->id, null, false);
        $importer = $this->fakeImporter();

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertFalse($result->success);
        $this->assertFalse($result->changed, 'nothing was replaced');
        $this->assertStringContainsString('safety backup could not be created', $result->message);
        $this->assertNull($result->safetyBackup, 'a failed safety backup must never be reported as a preserved one');
        $this->assertNull(BackupLog::where('type', BackupLog::TYPE_RESTORE)->sole()->details, 'the restore record must not point at a safety backup that does not exist');
        $this->assertSame(0, $importer->calls, 'the database must not be touched');
        $this->assertSame(['safety-backup'], $this->order);
        $this->assertNothingChangedOnDisk();

        // The selected backup is untouched; the failure is on record.
        $this->assertFileExists($this->dirPath . '/' . $backup->filename);
        $this->assertSame(BackupLog::STATUS_SUCCESS, $backup->fresh()->status);
        $record = BackupLog::where('type', BackupLog::TYPE_RESTORE)->sole();
        $this->assertSame(BackupLog::STATUS_FAILED, $record->status);
        $this->assertSame($backup->filename, $record->filename);
    }

    public function test_restore_stops_when_the_safety_backup_cannot_be_verified(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        // "Successful" row but the archive is not there -> cannot be verified.
        $this->mock(BackupService::class, function (MockInterface $m) {
            $m->shouldReceive('create')->once()->andReturnUsing(fn () => BackupLog::create([
                'filename' => 'clinic_backup_2026-10-01_11-00-00.zip', 'type' => BackupLog::TYPE_PRE_RESTORE,
                'status' => BackupLog::STATUS_SUCCESS, 'started_at' => now(), 'completed_at' => now(),
            ]));
        });
        $importer = $this->fakeImporter();

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertFalse($result->success);
        $this->assertFalse($result->changed);
        $this->assertStringContainsString('could not be verified', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertNothingChangedOnDisk();
    }

    // ================================================================ 14-15 locks + blocking

    public function test_a_second_restore_cannot_start_while_one_is_running(): void
    {
        $guard = app(RestoreGuard::class);
        $held  = $guard->acquireRestoreLock();
        $this->assertNotNull($held);

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $importer = $this->fakeImporter();

        $result = $this->restoreService()->restore($this->makeBackup(), null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Another restore is already running', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertSame([], glob($this->publicRoot . '/*') ?: []);

        // The running restore's marker/lock were not disturbed by the refused attempt.
        $this->assertNotNull($held);
        $guard->release($held);
    }

    public function test_restore_a_and_restore_b_cannot_overlap(): void
    {
        $admin = $this->makeUser('Admin');
        $a     = $this->makeBackup();
        $b     = $this->makeBackup();

        $this->fakeSafetyBackup($admin->id); // ->once(): a second safety backup would fail the test

        $nested = null;
        $this->fakeImporter(function () use (&$nested, $b, $admin) {
            // While restore A is replacing data, restore B tries to start.
            $nested = $this->restoreService()->restore($b, $admin->id);
        });

        $result = $this->restoreService()->restore($a, $admin->id);

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertNotNull($nested);
        $this->assertFalse($nested->success);
        $this->assertStringContainsString('Another restore is already running', $nested->message);
        $this->assertFalse($nested->changed);
    }

    public function test_a_backup_cannot_run_while_a_restore_is_replacing_data(): void
    {
        $admin = $this->makeUser('Admin');
        $this->fakeSafetyBackup($admin->id);

        $backupDuringRestore = null;
        $this->fakeImporter(function () use (&$backupDuringRestore) {
            // A REAL BackupService (not the stand-in) tries to run mid-restore.
            $backupDuringRestore = (new BackupService())->create(BackupLog::TYPE_MANUAL, null);
        });

        $result = $this->restoreService()->restore($this->makeBackup(), $admin->id);

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertNotNull($backupDuringRestore);
        $this->assertSame(BackupLog::STATUS_FAILED, $backupDuringRestore->status);
        $this->assertStringContainsString('Another backup is already running', (string) $backupDuringRestore->error_message);
    }

    public function test_restore_will_not_replace_data_if_a_backup_starts_after_the_safety_backup(): void
    {
        $admin = $this->makeUser('Admin');
        $held  = null;

        $this->fakeSafetyBackup($admin->id, function () use (&$held) {
            // Another backup grabs the backup lock right after the safety backup finished.
            $held = app(RestoreGuard::class)->acquireBackupLock();
        });
        $importer = $this->fakeImporter();

        $result = $this->restoreService()->restore($this->makeBackup(), $admin->id);

        app(RestoreGuard::class)->release($held);

        $this->assertNotNull($held);
        $this->assertFalse($result->success);
        $this->assertFalse($result->changed, 'aborted before the destructive phase');
        $this->assertStringContainsString('A backup started', $result->message);
        $this->assertSame(0, $importer->calls);
        $this->assertSame([], glob($this->publicRoot . '/*') ?: []);
        $this->assertSame([], $this->tempFoldersLeft());
        $this->assertNotNull($result->safetyBackup, 'the safety backup is kept');
    }

    public function test_the_system_is_blocked_for_users_while_a_restore_runs_and_opens_again_afterwards(): void
    {
        $admin = $this->makeUser('Admin');
        $this->fakeSafetyBackup($admin->id);

        $statusDuring = [];
        $this->fakeImporter(function () use (&$statusDuring) {
            $statusDuring['login']   = $this->get('/login')->getStatusCode();
            $statusDuring['api']     = $this->getJson('/anything')->getStatusCode();
            $page                    = $this->get('/login');
            $statusDuring['retry']   = $page->headers->get('Retry-After');
            $statusDuring['explains'] = str_contains($page->getContent(), 'being restored from a backup');
            $statusDuring['json']    = $this->getJson('/anything')->json('message');
        });

        $this->assertTrue($this->restoreService()->restore($this->makeBackup(), $admin->id)->success);

        $this->assertSame(503, $statusDuring['login']);
        $this->assertSame(503, $statusDuring['api']);
        $this->assertSame('60', $statusDuring['retry']);
        $this->assertTrue($statusDuring['explains'], 'people are told WHY the system is unavailable, not just "Service Unavailable"');
        $this->assertStringContainsString('being restored', $statusDuring['json']);

        $this->get('/login')->assertOk();
    }

    public function test_a_marker_left_by_a_crashed_restore_does_not_block_the_system(): void
    {
        file_put_contents($this->dirPath . '/' . RestoreGuard::MARKER, '{"pid":1}');

        $this->assertFalse(app(RestoreGuard::class)->isRestoreRunning(), 'no process holds the lock');
        $this->get('/login')->assertOk();
    }

    // ================================================================ 16, 20 failure handling

    public function test_a_database_failure_is_logged_safely_and_leaves_everything_preserved(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $this->fakeSafetyBackup($admin->id);
        $this->fakeImporter(null, new RuntimeException("SQLSTATE[HY000] [1045] Access denied for user 'clinic'@'localhost' (using password: YES) /var/www/secret/path.php"));

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertFalse($result->success);
        $this->assertTrue($result->changed, 'the destructive phase had started');
        $this->assertNotNull($result->safetyBackup);

        $record = BackupLog::where('type', BackupLog::TYPE_RESTORE)->sole();
        $this->assertSame(BackupLog::STATUS_FAILED, $record->status);
        $this->assertSame($backup->filename, $record->filename);
        $this->assertNotNull($record->completed_at);
        $this->assertStringContainsString('clinic_backup_2026-10-01_11-00-00.zip', $record->details);

        // No credentials, SQL, or paths in anything a user can see.
        foreach ([$result->message, (string) $record->error_message, (string) $record->display_error, (string) $record->details] as $shown) {
            $this->assertStringNotContainsString('Access denied', $shown);
            $this->assertStringNotContainsString('password', strtolower($shown));
            $this->assertStringNotContainsString('/var/www', $shown);
            $this->assertStringNotContainsString('SQLSTATE', $shown);
        }

        // Preserved: selected backup + safety backup (file and row). Cleaned: temp. Released: locks + marker.
        $this->assertFileExists($this->dirPath . '/' . $backup->filename);
        $this->assertFileExists($this->dirPath . '/clinic_backup_2026-10-01_11-00-00.zip');
        $this->assertSame(1, BackupLog::where('type', BackupLog::TYPE_PRE_RESTORE)->where('status', 'success')->count());
        $this->assertSame([], $this->tempFoldersLeft());
        $this->assertFileDoesNotExist($this->dirPath . '/' . RestoreGuard::MARKER);
        $this->assertFalse(app(RestoreGuard::class)->isRestoreRunning());
        $lock = app(RestoreGuard::class)->acquireBackupLock();
        $this->assertNotNull($lock);
        app(RestoreGuard::class)->release($lock);
    }

    public function test_a_failed_restore_never_attempts_a_second_restore_or_rollback(): void
    {
        $admin = $this->makeUser('Admin');

        $this->fakeSafetyBackup($admin->id); // once() -> a second safety backup attempt would fail the test
        $importer = $this->fakeImporter(null, new RuntimeException('boom'));

        $this->restoreService()->restore($this->makeBackup(), $admin->id);

        $this->assertSame(1, $importer->calls, 'the import is attempted exactly once: no automatic retry, no automatic rollback');
    }

    public function test_verification_failure_is_never_reported_as_success(): void
    {
        $admin = $this->makeUser('Admin');

        // The dump promises a table the database will not have (the import stand-in creates nothing).
        $backup = $this->makeBackup(['tables' => ['users', 'patients', 'ghost_table_that_was_not_created']]);

        $this->fakeSafetyBackup($admin->id);
        $this->fakeImporter();

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertFalse($result->success);
        $this->assertTrue($result->changed);
        $this->assertStringContainsString('missing tables', $result->message);
        $this->assertSame(BackupLog::STATUS_FAILED, BackupLog::where('type', BackupLog::TYPE_RESTORE)->sole()->status);
        $this->assertSame([], $this->tempFoldersLeft());
    }

    // ================================================================ 17-18 nothing outside uploads is ever touched

    public function test_env_and_application_source_are_never_touched_by_a_restore(): void
    {
        $admin       = $this->makeUser('Admin');
        $fingerprint = $this->projectFingerprint();

        $this->fakeSafetyBackup($admin->id);
        $this->fakeImporter();

        $result = $this->restoreService()->restore($this->makeBackup(), $admin->id);

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertSame($fingerprint, $this->projectFingerprint(), '.env, config, routes and source are byte-identical');

        // Only the four configured upload folders were written.
        $written = array_map('basename', glob($this->publicRoot . '/*'));
        sort($written);
        $this->assertSame(['avatars', 'laboratory_results', 'signatures', 'valid_ids'], $written);
    }

    // ================================================================ 21-24 earlier phases + retention

    public function test_retention_never_deletes_a_pre_restore_safety_backup_or_a_restore_record(): void
    {
        config(['backup.retention.keep' => 2]);

        $auto = [];
        foreach ([10, 9, 8, 7, 6] as $i => $minutesAgo) {
            $auto[] = $this->makeBackup([], [
                'type' => $i % 2 ? BackupLog::TYPE_MANUAL : BackupLog::TYPE_AUTOMATIC,
                'completed_at' => now()->subHours($minutesAgo), 'started_at' => now()->subHours($minutesAgo),
            ]);
        }
        // Old safety backups: older than everything, would be first to go if they were counted.
        $safeties = [];
        foreach ([40, 30] as $hoursAgo) {
            $safeties[] = $this->makeBackup([], [
                'type' => BackupLog::TYPE_PRE_RESTORE,
                'completed_at' => now()->subHours($hoursAgo), 'started_at' => now()->subHours($hoursAgo),
            ]);
        }
        $record = BackupLog::create([
            'filename' => $auto[0]->filename, 'type' => BackupLog::TYPE_RESTORE, 'status' => BackupLog::STATUS_SUCCESS,
            'started_at' => now()->subHours(50), 'completed_at' => now()->subHours(50),
        ]);

        $result = app(BackupRetentionService::class)->apply();

        $this->assertSame(3, $result['deleted'], 'keeps the newest 2 manual/automatic backups, deletes the 3 older ones');

        foreach ($safeties as $safety) {
            $this->assertFileExists($this->dirPath . '/' . $safety->filename, 'safety backup file kept');
            $this->assertNotNull($safety->fresh(), 'safety backup row kept');
        }
        $this->assertNotNull($record->fresh(), 'restore record kept');

        // The restore record shares a filename with a manual/automatic backup: deleting THAT backup must not depend on it.
        $this->assertFileDoesNotExist($this->dirPath . '/' . $auto[0]->filename);
    }

    public function test_retention_waits_while_a_restore_is_running(): void
    {
        config(['backup.retention.keep' => 1]);

        $this->makeBackup([], ['completed_at' => now()->subHours(3), 'started_at' => now()->subHours(3)]);
        $this->makeBackup([], ['completed_at' => now()->subHours(2), 'started_at' => now()->subHours(2)]);

        $guard = app(RestoreGuard::class);
        $lock  = $guard->acquireRestoreLock();
        $guard->markStarted();

        $result = app(BackupRetentionService::class)->apply();

        $guard->clearMarker();
        $guard->release($lock);

        $this->assertSame(0, $result['deleted']);
        $this->assertStringContainsString('restore is running', (string) $result['skipped']);
        $this->assertSame(2, BackupLog::count());
    }

    public function test_the_history_page_shows_restore_only_where_it_belongs_and_labels_the_new_types(): void
    {
        $admin = $this->makeUser('Admin');

        $ok       = $this->makeBackup([], ['type' => BackupLog::TYPE_MANUAL]);
        $safety   = $this->makeBackup([], ['type' => BackupLog::TYPE_PRE_RESTORE, 'details' => 'Safety backup made before restoring something.zip']);
        $failed   = $this->makeBackup([], ['status' => BackupLog::STATUS_FAILED, 'error_message' => 'x']);
        $missing  = $this->makeBackup([], ['filename' => 'clinic_backup_2026-10-01_12-00-00.zip']);
        unlink($this->dirPath . '/' . $missing->filename);
        $record = BackupLog::create([
            'filename' => $ok->filename, 'type' => BackupLog::TYPE_RESTORE, 'status' => BackupLog::STATUS_SUCCESS,
            'started_at' => now(), 'completed_at' => now(), 'details' => 'Safety backup: ' . $safety->filename,
        ]);

        $html = $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.backups.restore', $ok), $html);
        $this->assertStringContainsString(route('admin.backups.restore', $safety), $html);
        $this->assertStringNotContainsString(route('admin.backups.restore', $failed), $html, 'no Restore for a failed backup');
        $this->assertStringNotContainsString(route('admin.backups.restore', $missing), $html, 'no Restore when the file is gone');
        $this->assertStringNotContainsString(route('admin.backups.restore', $record), $html, 'no Restore for a restore record');
        $this->assertStringNotContainsString(route('admin.backups.download', $record), $html);

        $this->assertStringContainsString('Pre-restore', $html);
        $this->assertStringContainsString('bk-badge--restore', $html);
        $this->assertStringContainsString('Restored backup', $html);
        $this->assertStringContainsString('Safety backup made before restoring something.zip', $html);
    }

    public function test_restore_records_do_not_inflate_the_backup_counters(): void
    {
        $admin = $this->makeUser('Admin');
        $this->makeBackup();
        BackupLog::create(['filename' => 'x.zip', 'type' => 'restore', 'status' => 'success', 'started_at' => now(), 'completed_at' => now()->addMinute()]);

        $this->actingAs($admin)->get(route('admin.backups.index'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $s) => $s['total'] === 1);
    }

    public function test_phase_2_download_still_works_for_a_backup_and_for_a_safety_backup(): void
    {
        $admin  = $this->makeUser('Admin');
        $manual = $this->makeBackup();
        $safety = $this->makeBackup([], ['type' => BackupLog::TYPE_PRE_RESTORE]);

        $this->actingAs($admin)->get(route('admin.backups.download', $manual))->assertOk()->assertDownload($manual->filename);
        $this->actingAs($admin)->get(route('admin.backups.download', $safety))->assertOk()->assertDownload($safety->filename);
    }

    public function test_phase_1_command_and_phase_3_automatic_type_are_unchanged(): void
    {
        foreach ([BackupLog::TYPE_MANUAL, BackupLog::TYPE_AUTOMATIC] as $type) {
            $this->mock(BackupService::class, function (MockInterface $m) use ($type) {
                // The command calls create($type) with the type only, then summary().
                $m->shouldReceive('create')->once()->with($type)
                    ->andReturnUsing(fn () => $this->makeBackup([], ['type' => $type]));
                $m->shouldReceive('summary')->andReturn(['tables' => 39, 'files' => 395, 'directories' => [], 'location' => 'x']);
            });

            $this->artisan('clinic:backup', ['--type' => $type])->assertSuccessful();
        }
    }

    public function test_the_backup_history_is_always_preserved_even_if_config_forgets_it(): void
    {
        config(['backup.restore.preserve_tables' => []]);

        $admin = $this->makeUser('Admin');
        $this->fakeSafetyBackup($admin->id);
        $seen = $this->fakeImporter();

        $this->assertTrue($this->restoreService()->restore($this->makeBackup(), $admin->id)->success);
        $this->assertContains('backup_logs', $seen->preserve);
    }

    public function test_restored_files_are_checked_against_the_backup_after_the_copy(): void
    {
        $verify = new \ReflectionMethod(BackupRestoreService::class, 'verify');
        $verify->setAccessible(true);

        foreach (['avatars', 'valid_ids', 'laboratory_results', 'signatures'] as $dir) {
            mkdir($this->publicRoot . '/' . $dir, 0755, true);
        }
        file_put_contents($this->publicRoot . '/avatars/a.jpg', 'WRONG-CONTENT');

        $expected = ['relative' => 'avatars/a.jpg', 'size' => 13, 'crc' => crc32('RIGHT-CONTENT')];

        // Same size, different bytes: only the checksum can notice.
        $this->expectException(RestoreException::class);
        $this->expectExceptionMessage('did not match the backup');

        $verify->invoke($this->restoreService(), [], [$expected]);
    }

    public function test_a_file_that_is_missing_after_the_copy_fails_verification(): void
    {
        $verify = new \ReflectionMethod(BackupRestoreService::class, 'verify');
        $verify->setAccessible(true);

        foreach (['avatars', 'valid_ids', 'laboratory_results', 'signatures'] as $dir) {
            mkdir($this->publicRoot . '/' . $dir, 0755, true);
        }

        $this->expectException(RestoreException::class);

        $verify->invoke($this->restoreService(), [], [['relative' => 'avatars/gone.jpg', 'size' => 1, 'crc' => 1]]);
    }

    // ---- the live upload folders themselves: a symlink planted on the server must never redirect a restore ----

    private function outsideFolder(): string
    {
        $outside = storage_path('app/testing-outside-' . bin2hex(random_bytes(4)));
        mkdir($outside, 0755, true);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($outside));

        return $outside;
    }

    private function symlinkOrSkip(string $target, string $link): void
    {
        if (!@symlink($target, $link)) {
            $this->markTestSkipped('This environment cannot create symlinks.');
        }
    }

    public function test_a_symlinked_destination_folder_cannot_redirect_files_outside_the_upload_folder(): void
    {
        $admin   = $this->makeUser('Admin');
        $outside = $this->outsideFolder();
        mkdir($this->publicRoot . '/avatars', 0755, true);
        $this->symlinkOrSkip($outside, $this->publicRoot . '/avatars/sub');

        $backup = $this->makeBackup(['files' => ['avatars/sub/planted.jpg' => 'PLANTED']]);
        $this->fakeSafetyBackup($admin->id);
        $this->fakeImporter();

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('outside its upload folder', $result->message);
        $this->assertSame([], array_diff(scandir($outside), ['.', '..']), 'nothing may be written outside the upload folders');
        $this->assertSame([], $this->tempFoldersLeft());
    }

    public function test_an_existing_file_that_is_a_symlink_is_never_written_through(): void
    {
        $admin   = $this->makeUser('Admin');
        $outside = $this->outsideFolder();
        file_put_contents($outside . '/precious.txt', 'DO NOT TOUCH');
        mkdir($this->publicRoot . '/avatars', 0755, true);
        $this->symlinkOrSkip($outside . '/precious.txt', $this->publicRoot . '/avatars/new.jpg');

        $backup = $this->makeBackup(['files' => ['avatars/new.jpg' => 'OVERWRITE']]);
        $this->fakeSafetyBackup($admin->id);
        $this->fakeImporter();

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('through a link', $result->message);
        $this->assertSame('DO NOT TOUCH', file_get_contents($outside . '/precious.txt'));
        $this->assertTrue(is_link($this->publicRoot . '/avatars/new.jpg'), 'the link itself is left as found');
    }

    public function test_an_upload_folder_that_is_itself_a_symlink_is_refused(): void
    {
        $admin   = $this->makeUser('Admin');
        $outside = $this->outsideFolder();
        $this->symlinkOrSkip($outside, $this->publicRoot . '/avatars');

        $backup = $this->makeBackup(['files' => ['avatars/new.jpg' => 'X']]);
        $this->fakeSafetyBackup($admin->id);
        $this->fakeImporter();

        $result = $this->restoreService()->restore($backup, $admin->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not safe to restore into', $result->message);
        $this->assertSame([], array_diff(scandir($outside), ['.', '..']));
    }
}
