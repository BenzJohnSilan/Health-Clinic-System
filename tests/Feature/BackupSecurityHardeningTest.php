<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupDatabaseImporter;
use App\Services\BackupFileService;
use App\Services\BackupRestoreService;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\RestoreGuard;
use App\Support\BackupFilesystem;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Phase 5 - Security & production hardening of Backup & Recovery.
 *
 * Each test either pins a problem found during the Phase 5 audit (and fixed),
 * or proves an area was audited and is sound. Everything runs on throw-away
 * folders; no real backup, upload or database is touched.
 */
class BackupSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $dirName;

    private string $dirPath;

    private string $tempName;

    private string $tempPath;

    private string $publicRoot;

    /** @var array<int,string> folders to remove afterwards */
    private array $scratch = [];

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
            'backup.directory'              => $this->dirName,
            'backup.restore.temp_directory' => $this->tempName,
            'filesystems.disks.public.root' => $this->publicRoot,
        ]);
        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        foreach ([$this->dirPath, $this->tempPath, $this->publicRoot, ...$this->scratch] as $path) {
            BackupFilesystem::deleteTree($path);
        }

        parent::tearDown();
    }

    // ================================================================ helpers

    private function makeUser(string $role, bool $verified = true): User
    {
        $user = User::create([
            'first_name' => ucfirst(strtolower($role)), 'last_name' => 'Tester', 'birthdate' => '1995-01-01',
            'gender' => 'Male', 'civil_status' => 'Single', 'address' => 'Sample St',
            'contact_number' => '0917' . random_int(1000000, 9999999),
            'username' => strtolower($role) . random_int(100000, 999999),
            'email' => strtolower($role) . random_int(1000, 9999) . '@test.com',
            'password' => bcrypt('password'), 'role' => $role, 'approval_status' => 'Approved',
        ]);

        $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return $user;
    }

    private function scratchDir(string $prefix): string
    {
        $dir = storage_path('app/' . $prefix . '-' . bin2hex(random_bytes(4)));
        mkdir($dir, 0755, true);
        $this->scratch[] = $dir;

        return $dir;
    }

    private function dump(): string
    {
        return "-- MySQL dump\n/*!40101 SET NAMES utf8mb4 */;\nDROP TABLE IF EXISTS `users`;\n"
            . "CREATE TABLE `users` (\n  `id` bigint unsigned NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n"
            . "INSERT INTO `users` VALUES (1);\n\n-- Dump completed on 2026-09-30  9:09:18\n";
    }

    private function makeArchive(string $filename, array $entries = [], array $files = ['avatars/a.jpg' => 'A'], ?string $symlink = null): string
    {
        $dirs = ['avatars' => 0, 'valid_ids' => 0, 'laboratory_results' => 0, 'signatures' => 0];
        foreach (array_keys($files) as $relative) {
            $dirs[explode('/', $relative)[0]]++;
        }
        $manifest = [
            'database' => ['driver' => 'mysql', 'dump' => 'database/database.sql', 'tables' => 1],
            'files'    => ['root' => 'files/storage/app/public', 'total' => count($files), 'directories' => $dirs],
        ];

        $path = $this->dirPath . '/' . $filename;
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database/database.sql', $this->dump());
        $zip->addFromString('manifest.json', json_encode($manifest));
        foreach ($files as $relative => $content) {
            $zip->addFromString('files/storage/app/public/' . $relative, $content);
        }
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        if ($symlink) {
            $zip->addFromString($symlink, 'target');
            $zip->setExternalAttributesName($symlink, ZipArchive::OPSYS_UNIX, 0120777 << 16);
        }
        $zip->close();

        return $path;
    }

    private function makeBackup(array $row = [], array $entries = [], ?string $symlink = null): BackupLog
    {
        $filename = $row['filename'] ?? sprintf('clinic_backup_2026-10-01_10-00-%02d.zip', ++$this->seq);
        $path     = $this->makeArchive($filename, $entries, ['avatars/a.jpg' => 'A'], $symlink);

        return BackupLog::create(array_merge([
            'filename' => $filename, 'type' => BackupLog::TYPE_MANUAL, 'size' => filesize($path),
            'status' => BackupLog::STATUS_SUCCESS, 'started_at' => now()->subMinutes(5), 'completed_at' => now()->subMinutes(4),
        ], $row));
    }

    /** @return array<string,string> */
    private function fingerprint(): array
    {
        $hashes = [];
        foreach (['.env.example', 'composer.json', 'routes/web.php', 'config/app.php', 'app/Services/BackupService.php', 'bootstrap/app.php'] as $file) {
            $hashes[$file] = md5_file(base_path($file));
        }
        if (is_file(base_path('.env'))) {
            $hashes['.env'] = md5_file(base_path('.env'));
        }

        return $hashes;
    }

    // ================================================================ 1. access control, server-side, every route

    /** @return array<string,array{string,string}> */
    public static function protectedRoutes(): array
    {
        return [
            'history'          => ['GET', 'index'],
            'backup now'       => ['POST', 'store'],
            'download'         => ['GET', 'download'],
            'restore page'     => ['GET', 'restore'],
            'restore action'   => ['POST', 'restore.run'],
        ];
    }

    private function url(string $route, BackupLog $backup): string
    {
        return in_array($route, ['index', 'store'], true)
            ? route('admin.backups.' . $route)
            : route('admin.backups.' . $route, $backup);
    }

    private function send(string $method, string $url, ?User $user)
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $method === 'GET' ? $request->get($url) : $request->post($url, ['confirmation' => 'RESTORE']);
    }

    private function nothingMayRun(): void
    {
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $this->mock(BackupRestoreService::class, function (MockInterface $m) {
            $m->shouldNotReceive('restore');
            $m->shouldNotReceive('inspect');
        });
    }

    #[DataProvider('protectedRoutes')]
    public function test_guests_are_turned_away_from_every_backup_route(string $method, string $route): void
    {
        $this->nothingMayRun();
        $backup = $this->makeBackup();

        $this->send($method, $this->url($route, $backup), null)->assertRedirect(route('login'));
    }

    #[DataProvider('protectedRoutes')]
    public function test_only_admins_get_past_the_server_for_every_backup_route(string $method, string $route): void
    {
        $this->nothingMayRun();
        $backup = $this->makeBackup();

        foreach (['Patient', 'Doctor', 'Staff'] as $role) {
            $this->send($method, $this->url($route, $backup), $this->makeUser($role))
                ->assertForbidden();
        }
    }

    public function test_an_admin_reaches_every_backup_route(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldReceive('create')->once()->andReturn($backup));

        $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk();
        $this->actingAs($admin)->post(route('admin.backups.store'))->assertRedirect(route('admin.backups.index'));
        $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertOk();
        $this->actingAs($admin)->get(route('admin.backups.restore', $backup))->assertOk();
        $this->actingAs($admin)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'nope'])->assertRedirect(route('admin.backups.restore', $backup));
    }

    public function test_no_other_route_exposes_backups(): void
    {
        $names = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'backup') || str_contains($route->uri(), 'restore'))
            ->each(function ($route) {
                $this->assertContains('role:Admin', $route->gatherMiddleware(), $route->uri() . ' must require the Admin role');
                $this->assertContains('auth', $route->gatherMiddleware(), $route->uri() . ' must require login');
            });

        $this->assertCount(5, $names, 'exactly the five known backup routes exist');
    }

    public function test_unknown_or_malformed_backup_ids_are_not_found_not_errors(): void
    {
        $admin = $this->makeUser('Admin');

        foreach (['999999', 'abc', '1e3', '-1', '0x10', '1;2'] as $id) {
            $this->actingAs($admin)->get('/admin/backups/' . $id . '/download')->assertNotFound();
            $this->actingAs($admin)->get('/admin/backups/' . $id . '/restore')->assertNotFound();
            $this->actingAs($admin)->post('/admin/backups/' . $id . '/restore', ['confirmation' => 'RESTORE'])->assertNotFound();
        }
    }

    // ================================================================ 3. storage is private and unreachable from the web

    public function test_backups_live_outside_the_public_web_folders_and_nothing_public_links_to_them(): void
    {
        $realDir = realpath($this->dirPath);

        foreach ([public_path(), storage_path('app/public')] as $public) {
            $this->assertStringStartsNotWith(realpath($public) . DIRECTORY_SEPARATOR, $realDir . DIRECTORY_SEPARATOR);
        }

        $default = config('backup.directory');
        config(['backup.directory' => 'backups']);
        $this->assertStringContainsString('storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private', (new RestoreGuard())->directory());
        config(['backup.directory' => $default]);

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS)) as $item) {
            if ($item->isLink()) {
                $this->assertStringNotContainsString('private', (string) readlink($item->getPathname()), 'a public symlink must never lead to the private disk');
            }
        }
    }

    #[DataProvider('directUrls')]
    public function test_a_backup_cannot_be_fetched_by_a_direct_url(string $pattern): void
    {
        $backup = $this->makeBackup();
        $url    = str_replace(['{dir}', '{file}'], [$this->dirName, $backup->filename], $pattern);

        foreach ([null, $this->makeUser('Admin'), $this->makeUser('Patient')] as $user) {
            $response = $user ? $this->actingAs($user)->get($url) : $this->get($url);

            $this->assertContains($response->getStatusCode(), [302, 401, 403, 404], "$url must not serve the file (got {$response->getStatusCode()})");
            $this->assertNotSame('application/zip', $response->headers->get('Content-Type'));
        }
    }

    public static function directUrls(): array
    {
        return [
            'through the disk-serving route'  => ['/storage/{dir}/{file}'],
            'the folder listing'              => ['/storage/{dir}/'],
            'the lock file'                   => ['/storage/{dir}/.backup.lock'],
            'a guessed private path'          => ['/storage/app/private/{dir}/{file}'],
            'a guessed root path'             => ['/{dir}/{file}'],
            'a guessed backups path'          => ['/backups/{file}'],
            'a path traversal attempt'        => ['/storage/{dir}/..%2F..%2F.env'],
        ];
    }

    public function test_the_backup_folder_and_the_restore_folder_both_refuse_web_access_on_apache(): void
    {
        $backup = $this->makeBackup();

        // The restore's working folder is created on first use.
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldReceive('create')->andReturn(
            $this->makeBackup(['type' => BackupLog::TYPE_PRE_RESTORE, 'filename' => 'clinic_backup_2026-10-01_11-00-00.zip'])
        ));
        $this->partialMock(BackupDatabaseImporter::class, fn (MockInterface $m) => $m->shouldReceive('import')->andReturn([]));
        app(BackupRestoreService::class)->restore($backup, null);

        BackupFilesystem::denyWebAccess($this->dirPath);

        foreach ([$this->dirPath, $this->tempPath] as $folder) {
            $this->assertFileExists($folder . '/.htaccess');
            $this->assertStringContainsString('Require all denied', file_get_contents($folder . '/.htaccess'));
        }
    }

    // ================================================================ 3/4. path traversal on names, ids and restore source

    public function test_the_filename_check_is_exact_and_does_not_accept_a_trailing_newline(): void
    {
        $files = app(BackupFileService::class);

        $this->assertTrue($files->isValidFilename('clinic_backup_2026-01-01_00-00-00.zip'));
        foreach (["clinic_backup_2026-01-01_00-00-00.zip\n", "clinic_backup_2026-01-01_00-00-00.zip\r\n", " clinic_backup_2026-01-01_00-00-00.zip", "clinic_backup_2026-01-01_00-00-00.zip "] as $name) {
            $this->assertFalse($files->isValidFilename($name), json_encode($name) . ' must be rejected');
        }
    }

    #[DataProvider('hostileFilenames')]
    public function test_a_record_with_a_hostile_filename_is_never_served_restored_or_deleted(string $filename): void
    {
        $outside = $this->scratchDir('testing-outside');
        file_put_contents($outside . '/precious.zip', 'DO NOT TOUCH');
        file_put_contents(storage_path('app/private/.env-sentinel'), 'SENTINEL');
        $this->scratch[] = storage_path('app/private/.env-sentinel');

        $backup = BackupLog::create([
            'filename' => $filename, 'type' => BackupLog::TYPE_MANUAL, 'size' => 10, 'status' => BackupLog::STATUS_SUCCESS,
            'started_at' => now()->subDay(), 'completed_at' => now()->subDay(),
        ]);
        $admin = $this->makeUser('Admin');

        $this->assertNull(app(BackupFileService::class)->resolve($backup));
        $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.backups.restore', $backup))->assertRedirect(route('admin.backups.index'))->assertSessionHas('error');

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $result = app(BackupRestoreService::class)->restore($backup, $admin->id);
        $this->assertFalse($result->success);
        $this->assertFalse($result->changed);

        config(['backup.retention.keep' => 1]);
        $this->makeBackup(['completed_at' => now()]);
        app(BackupRetentionService::class)->apply();

        $this->assertSame('DO NOT TOUCH', file_get_contents($outside . '/precious.zip'));
        $this->assertSame('SENTINEL', file_get_contents(storage_path('app/private/.env-sentinel')));
    }

    public static function hostileFilenames(): array
    {
        return [
            'parent folder'            => ['../precious.zip'],
            'deep traversal'           => ['../../../etc/passwd'],
            'traversal after a name'   => ['clinic_backup_2026-01-01_00-00-00.zip/../../.env'],
            'absolute linux path'      => ['/etc/passwd'],
            'windows drive path'       => ['C:\\Windows\\win.ini'],
            'windows drive, slashes'   => ['C:/Windows/win.ini'],
            'backslash traversal'      => ['..\\..\\precious.zip'],
            'unc path'                 => ['\\\\server\\share\\x.zip'],
            'sub folder'               => ['sub/clinic_backup_2026-01-01_00-00-00.zip'],
            'null byte'                => ["clinic_backup_2026-01-01_00-00-00.zip\0.php"],
            'null byte then traversal' => ["x.zip\0../../.env"],
            'no extension'             => ['clinic_backup_2026-01-01_00-00-00'],
            'wrong extension'          => ['clinic_backup_2026-01-01_00-00-00.php'],
            'trailing newline'         => ["clinic_backup_2026-01-01_00-00-00.zip\n"],
            'url-encoded traversal'    => ['..%2F..%2Fprecious.zip'],
        ];
    }

    // ================================================================ 4. hostile ZIPs, end to end through the browser routes

    #[DataProvider('hostileArchives')]
    public function test_a_hostile_archive_is_refused_by_the_restore_route_and_changes_nothing(array $entries, ?string $symlink): void
    {
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $importer    = $this->partialMock(BackupDatabaseImporter::class, fn (MockInterface $m) => $m->shouldNotReceive('import'));
        $fingerprint = $this->fingerprint();

        $backup = $this->makeBackup([], $entries, $symlink);
        $admin  = $this->makeUser('Admin');

        $this->actingAs($admin)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE'])
            ->assertRedirect(route('admin.backups.index'));

        $flash = session('error');
        $this->assertStringContainsString('Backup restore failed', $flash);
        $this->assertStringContainsString('Nothing was changed', $flash);
        $this->assertStringNotContainsString('MALICIOUS', $flash);

        $this->assertSame($fingerprint, $this->fingerprint(), '.env, config, routes and source are untouched');
        $this->assertSame([], glob($this->publicRoot . '/*') ?: [], 'no upload was written');
        $this->assertFileDoesNotExist(storage_path('app/private/.env'));
        $this->assertFileDoesNotExist(base_path('evil.txt'));
        $this->assertFileDoesNotExist($this->dirPath . '/' . RestoreGuard::MARKER);
    }

    public static function hostileArchives(): array
    {
        return [
            'parent traversal'      => [['../../.env' => 'MALICIOUS'], null],
            'traversal below files' => [['files/storage/app/public/../../../.env' => 'MALICIOUS'], null],
            'absolute linux path'   => [['/var/www/html/.env' => 'MALICIOUS'], null],
            'windows drive path'    => [['C:\\inetpub\\wwwroot\\evil.txt' => 'MALICIOUS'], null],
            'application source'    => [['files/app/Http/Kernel.php' => 'MALICIOUS'], null],
            'a web shell'           => [['files/storage/app/public/avatars/shell.php' => 'MALICIOUS'], null],
            'a hidden htaccess'     => [['files/storage/app/public/avatars/.htaccess' => 'MALICIOUS'], null],
            'a symlink entry'       => [[], 'files/storage/app/public/avatars/link'],
        ];
    }

    // ================================================================ 4/13. symlinks planted on the server never redirect a delete or a write

    public function test_a_symlink_named_like_a_restore_temp_folder_does_not_wipe_its_target(): void
    {
        $victim = $this->scratchDir('testing-victim');
        file_put_contents($victim . '/precious.txt', 'IMPORTANT');
        exec('touch -d "10 hours ago" ' . escapeshellarg($victim));

        @mkdir($this->tempPath, 0700, true);
        $link = $this->tempPath . '/restore-' . str_repeat('a', 16);
        if (!@symlink($victim, $link)) {
            $this->markTestSkipped('This environment cannot create symlinks.');
        }

        $method = new \ReflectionMethod(BackupRestoreService::class, 'cleanupStaleTemp');
        $method->setAccessible(true);
        $method->invoke(app(BackupRestoreService::class));

        $this->assertSame('IMPORTANT', file_get_contents($victim . '/precious.txt'), 'the folder the link pointed to is untouched');
        $this->assertFalse(is_link($link), 'the link itself was removed');

        // A link has no business being here at all, even one that points at a brand-new folder.
        $recent = $this->scratchDir('testing-victim');
        $link2  = $this->tempPath . '/restore-' . str_repeat('b', 16);
        symlink($recent, $link2);
        $method->invoke(app(BackupRestoreService::class));

        $this->assertFalse(is_link($link2), 'any link with a temp-folder name is removed, whatever the age of its target');
        $this->assertDirectoryExists($recent);
    }

    public function test_deleting_a_tree_never_follows_a_top_level_or_nested_symlink(): void
    {
        $victim = $this->scratchDir('testing-victim');
        file_put_contents($victim . '/precious.txt', 'IMPORTANT');

        $tree = $this->scratchDir('testing-tree');
        mkdir($tree . '/inner');
        file_put_contents($tree . '/inner/file.txt', 'x');
        if (!@symlink($victim, $tree . '/inner/link') || !@symlink($victim, $tree . '-link')) {
            $this->markTestSkipped('This environment cannot create symlinks.');
        }
        $this->scratch[] = $tree . '-link';

        BackupFilesystem::deleteTree($tree);
        BackupFilesystem::deleteTree($tree . '-link');

        $this->assertDirectoryDoesNotExist($tree);
        $this->assertFalse(is_link($tree . '-link'));
        $this->assertSame('IMPORTANT', file_get_contents($victim . '/precious.txt'));
    }

    public function test_a_symlinked_lock_file_is_replaced_not_followed(): void
    {
        $victim = $this->scratchDir('testing-victim');

        foreach (['.backup.lock', '.restore.lock'] as $lock) {
            if (!@symlink($victim . '/created-through-link.txt', $this->dirPath . '/' . $lock)) {
                $this->markTestSkipped('This environment cannot create symlinks.');
            }
        }

        $guard = app(RestoreGuard::class);
        $a = $guard->acquireBackupLock();
        $b = $guard->acquireRestoreLock();

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertFileDoesNotExist($victim . '/created-through-link.txt', 'nothing was created at the link target');
        $this->assertFalse(is_link($this->dirPath . '/.backup.lock'));
        $guard->release($a);
        $guard->release($b);
    }

    public function test_a_symlinked_restore_marker_is_never_written_through(): void
    {
        $victim = $this->scratchDir('testing-victim');
        file_put_contents($victim . '/target.txt', 'ORIGINAL');

        if (!@symlink($victim . '/target.txt', $this->dirPath . '/' . RestoreGuard::MARKER)) {
            $this->markTestSkipped('This environment cannot create symlinks.');
        }

        app(RestoreGuard::class)->markStarted();

        $this->assertSame('ORIGINAL', file_get_contents($victim . '/target.txt'));
        $this->assertFalse(is_link($this->dirPath . '/' . RestoreGuard::MARKER));
        $this->assertStringContainsString('"pid"', file_get_contents($this->dirPath . '/' . RestoreGuard::MARKER));
    }

    // ================================================================ 5/13. leftovers of a killed run (the database password file) are removed

    public function test_leftovers_of_a_killed_backup_are_removed_and_nothing_else_is(): void
    {
        $stale = $this->dirPath . '/.tmp-' . bin2hex(random_bytes(6));
        mkdir($stale, 0700);
        file_put_contents($stale . '/client.cnf', "[client]\npassword=\"leftover\"\n");
        file_put_contents($stale . '/database.sql', 'patient data');

        $keep = [
            $this->dirPath . '/clinic_backup_2026-10-01_10-00-00.zip' => 'real backup',
            $this->dirPath . '/.htaccess'                             => 'deny',
            $this->dirPath . '/.backup.lock'                          => '',
            $this->dirPath . '/.tmp-not-a-generated-name'             => 'someone else',
            $this->dirPath . '/notes.txt'                             => 'notes',
        ];
        foreach ($keep as $path => $content) {
            file_put_contents($path, $content);
        }

        $removed = BackupFilesystem::purgeStale($this->dirPath, '/^\.tmp-[0-9a-f]{12}$/');

        $this->assertSame(1, $removed);
        $this->assertDirectoryDoesNotExist($stale);
        foreach ($keep as $path => $content) {
            $this->assertFileExists($path, basename($path) . ' must survive');
        }
    }

    public function test_a_stale_temp_folder_that_is_a_symlink_is_unlinked_not_emptied(): void
    {
        $victim = $this->scratchDir('testing-victim');
        file_put_contents($victim . '/precious.txt', 'IMPORTANT');
        $link = $this->dirPath . '/.tmp-' . bin2hex(random_bytes(6));

        if (!@symlink($victim, $link)) {
            $this->markTestSkipped('This environment cannot create symlinks.');
        }

        BackupFilesystem::purgeStale($this->dirPath, '/^\.tmp-[0-9a-f]{12}$/');

        $this->assertFalse(is_link($link));
        $this->assertSame('IMPORTANT', file_get_contents($victim . '/precious.txt'));
    }

    public function test_every_backup_run_first_removes_the_leftovers_of_a_killed_one(): void
    {
        $stale = $this->dirPath . '/.tmp-' . bin2hex(random_bytes(6));
        mkdir($stale, 0700);
        file_put_contents($stale . '/client.cnf', "[client]\npassword=\"leftover\"\n");
        file_put_contents($stale . '/database.sql', 'patient data');

        $realBackup = $this->makeBackup();

        // The run itself fails later (SQLite is not backed up), but the clean-up happens right
        // after the lock is taken, before anything else.
        app(BackupService::class)->create(BackupLog::TYPE_MANUAL, null);

        $this->assertDirectoryDoesNotExist($stale, 'the leftover password file and dump were removed');
        $this->assertFileExists($this->dirPath . '/' . $realBackup->filename, 'real backups are never touched by that clean-up');
    }

    public function test_a_failed_backup_leaves_no_temp_files_and_frees_the_lock(): void
    {
        // The test database is SQLite, which BackupService refuses AFTER taking the lock:
        // exactly the "something goes wrong half way" path.
        $log = app(BackupService::class)->create(BackupLog::TYPE_MANUAL, null);

        $this->assertSame(BackupLog::STATUS_FAILED, $log->status);
        $this->assertSame([], glob($this->dirPath . '/.tmp-*') ?: []);
        $this->assertSame([], glob($this->dirPath . '/*.zip') ?: []);

        $guard = app(RestoreGuard::class);
        $lock  = $guard->acquireBackupLock();
        $this->assertNotNull($lock, 'the lock was released after the failure');
        $guard->release($lock);
    }

    // ================================================================ 7. locks: a crash cannot leave the system stuck

    public function test_locks_held_by_a_process_that_is_killed_do_not_block_the_system_afterwards(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Uses POSIX process signals.');
        }

        $code = '$d=getenv("LOCKDIR"); $a=fopen($d."/.backup.lock","c"); $b=fopen($d."/.restore.lock","c");'
            . 'flock($a,LOCK_EX); flock($b,LOCK_EX); file_put_contents($d."/.restore-in-progress","x");'
            . 'echo "READY\n"; fflush(STDOUT); sleep(60);';

        $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['LOCKDIR' => $this->dirPath]);
        $this->assertIsResource($process);
        $this->assertSame('READY', trim((string) fgets($pipes[1])));

        $guard = app(RestoreGuard::class);

        // While the "restore" is alive everything is blocked...
        $this->assertNull($guard->acquireBackupLock());
        $this->assertNull($guard->acquireRestoreLock());
        $this->assertTrue($guard->isRestoreRunning());
        $this->get('/login')->assertStatus(503);

        // ...and after it is killed (no clean-up code ever runs) nothing stays blocked.
        proc_terminate($process, 9);
        proc_close($process);
        usleep(300000);

        $this->assertFalse($guard->isRestoreRunning());
        $this->get('/login')->assertOk();
        foreach ([$guard->acquireBackupLock(), $guard->acquireRestoreLock()] as $lock) {
            $this->assertNotNull($lock, 'a stale lock file does not keep the lock');
            $guard->release($lock);
        }
    }

    // ================================================================ 6. safety backup gates everything (Phase 5 re-check through HTTP)

    public function test_when_the_safety_backup_fails_the_admin_is_told_and_nothing_is_replaced(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldReceive('create')->once()->andReturn(BackupLog::create([
            'filename' => 'clinic_backup_2026-10-01_11-00-00.zip', 'type' => BackupLog::TYPE_PRE_RESTORE, 'status' => BackupLog::STATUS_FAILED,
            'started_at' => now(), 'completed_at' => now(), 'error_message' => 'Backup failed.',
        ])));
        $this->partialMock(BackupDatabaseImporter::class, fn (MockInterface $m) => $m->shouldNotReceive('import'));

        $this->actingAs($admin)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE'])
            ->assertRedirect(route('admin.backups.index'));

        $this->assertStringContainsString('safety backup could not be created', session('error'));
        $this->assertStringNotContainsString('has been preserved', session('error'), 'a failed safety backup is not reported as preserved');
        $this->assertSame([], glob($this->publicRoot . '/*') ?: []);
    }

    // ================================================================ 8. nothing sensitive reaches the browser or the log

    public function test_the_admin_never_sees_the_database_host_name_user_or_server_folders(): void
    {
        $connection = 'connections.' . config('database.default');
        config([
            "database.$connection.host"     => 'db-internal.example.net',
            "database.$connection.database" => 'clinic_prod_db',
            "database.$connection.username" => 'clinic_prod_user',
        ]);

        $raw = "mysqldump failed (exit code 2): mysqldump: Got error: 2002: Can't connect to MySQL server on 'db-internal.example.net' (115) "
            . "Unknown database 'clinic_prod_db'; Access denied for user 'clinic_prod_user'@'10.0.0.5'; "
            . 'C:\\laragon\\bin\\mysql\\bin\\mysqldump.exe and ' . base_path('storage/app/private/backups/.tmp-abc/client.cnf');

        $shown = (new BackupLog(['error_message' => $raw]))->display_error;

        foreach (['db-internal.example.net', 'clinic_prod_db', 'clinic_prod_user', '10.0.0.5', 'laragon', 'client.cnf', base_path()] as $secret) {
            $this->assertStringNotContainsString($secret, $shown);
        }
        $this->assertStringContainsString('Got error: 2002', $shown, 'the error code stays, so the Admin can still diagnose');
    }

    public function test_failure_details_reach_the_log_without_credentials_and_never_the_browser(): void
    {
        $connection = 'connections.' . config('database.default');
        config([
            "database.$connection.host"     => 'db-internal.example.net',
            "database.$connection.username" => 'clinic_prod_user',
        ]);

        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldReceive('create')->andReturn(
            $this->makeBackup(['type' => BackupLog::TYPE_PRE_RESTORE, 'filename' => 'clinic_backup_2026-10-01_11-00-00.zip'])
        ));
        $this->partialMock(BackupDatabaseImporter::class, fn (MockInterface $m) => $m->shouldReceive('import')->andThrow(
            new RuntimeException("SQLSTATE[HY000] [1045] Access denied for user 'clinic_prod_user'@'db-internal.example.net' (using password: YES)")
        ));

        Log::spy();

        $this->actingAs($admin)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE'])
            ->assertRedirect(route('admin.backups.index'));

        foreach ([session('error'), (string) BackupLog::where('type', BackupLog::TYPE_RESTORE)->sole()->error_message] as $visible) {
            $this->assertStringNotContainsString('SQLSTATE', $visible);
            $this->assertStringNotContainsString('clinic_prod_user', $visible);
            $this->assertStringNotContainsString('db-internal', $visible);
            $this->assertStringNotContainsString('password', strtolower($visible));
        }

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context = []) {
            $logged = json_encode([$message, $context]);

            return str_contains($logged, 'Backup restore failed')
                && !str_contains($logged, 'clinic_prod_user')
                && !str_contains($logged, 'db-internal.example.net');
        })->atLeast()->once();
    }

    public function test_an_unexpected_error_while_inspecting_a_backup_never_becomes_a_500_page(): void
    {
        $backup = $this->makeBackup();

        $this->mock(BackupRestoreService::class, fn (MockInterface $m) => $m->shouldReceive('inspect')->andThrow(
            new RuntimeException('Stack trace with C:\\secret\\path and password=hunter2')
        ));

        $this->actingAs($this->makeUser('Admin'))->get(route('admin.backups.restore', $backup))
            ->assertRedirect(route('admin.backups.index'))
            ->assertSessionHas('error', 'This backup cannot be restored. The selected backup is invalid.');
    }

    public function test_the_database_password_file_is_owner_only_and_the_scrubber_hides_connection_details(): void
    {
        $method = new \ReflectionMethod(BackupService::class, 'writeClientOptionsFile');
        $method->setAccessible(true);
        $file = $this->dirPath . '/client.cnf';
        $method->invoke(app(BackupService::class), $file, ['username' => 'u', 'password' => 'S3cretPw', 'host' => '127.0.0.1', 'port' => 3306]);

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame('0600', substr(sprintf('%o', fileperms($file)), -4));
        }
        @unlink($file);

        $connection = 'connections.' . config('database.default');
        config(["database.$connection.host" => 'db-internal.example.net']);
        $this->assertStringNotContainsString('db-internal', BackupFilesystem::scrub("Connection to 'db-internal.example.net' refused"));
        $this->assertLessThanOrEqual(203, mb_strlen(BackupFilesystem::scrub(str_repeat('x', 5000))));
    }

    // ================================================================ 10. rate limits: abuse is limited, normal use is not

    public function test_restore_attempts_are_rate_limited_per_admin(): void
    {
        $this->mock(BackupRestoreService::class, fn (MockInterface $m) => $m->shouldNotReceive('restore'));
        $admin  = $this->makeUser('Admin');
        $other  = $this->makeUser('Admin');
        $backup = $this->makeBackup();
        $url    = route('admin.backups.restore.run', $backup);

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($admin)->post($url, ['confirmation' => 'wrong'])->assertRedirect();
        }
        $this->actingAs($admin)->post($url, ['confirmation' => 'wrong'])->assertStatus(429);
        $this->actingAs($admin)->post($url, ['confirmation' => 'RESTORE'])->assertStatus(429);

        // Limited per admin: a different admin is not affected.
        $this->actingAs($other)->post($url, ['confirmation' => 'wrong'])->assertRedirect();
    }

    public function test_backup_now_and_download_are_limited_but_history_is_not(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldReceive('create')->andReturn($backup));

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($admin)->post(route('admin.backups.store'))->assertRedirect(route('admin.backups.index'));
        }
        $this->actingAs($admin)->post(route('admin.backups.store'))->assertStatus(429);

        for ($i = 1; $i <= 20; $i++) {
            $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertStatus(429);

        for ($i = 1; $i <= 40; $i++) {
            $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk();
        }
    }

    // ================================================================ CSRF: state-changing requests need the token

    public function test_state_changing_backup_requests_need_a_csrf_token(): void
    {
        // Laravel switches CSRF checking off while testing; switch it back on for this test.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app->make('encrypter')) extends ValidateCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $this->mock(BackupRestoreService::class, fn (MockInterface $m) => $m->shouldNotReceive('restore'));

        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $this->actingAs($admin)->post(route('admin.backups.store'))->assertStatus(419);
        $this->actingAs($admin)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE'])->assertStatus(419);
        $this->actingAs($admin)->post(route('admin.backups.restore.run', $backup), ['confirmation' => 'RESTORE', '_token' => 'forged'])->assertStatus(419);
    }

    public function test_a_downloaded_backup_is_marked_private_and_uncacheable(): void
    {
        $backup = $this->makeBackup();

        $response = $this->actingAs($this->makeUser('Admin'))->get(route('admin.backups.download', $backup))->assertOk();

        $cache = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringNotContainsString('public', $cache, 'a backup must never be marked cacheable by shared caches');
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_reading_routes_change_no_backup_data_and_other_http_methods_are_refused(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $before = BackupLog::count();
        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldNotReceive('create'));
        $this->partialMock(BackupDatabaseImporter::class, fn (MockInterface $m) => $m->shouldNotReceive('import'));

        // Reading the history, downloading, and opening the confirmation page start nothing.
        $this->actingAs($admin)->get(route('admin.backups.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertOk();
        $this->actingAs($admin)->get(route('admin.backups.restore', $backup))->assertOk();
        $this->assertSame($before, BackupLog::count());

        // A restore can only be started by POST: no other verb reaches the controller.
        foreach (['put', 'patch', 'delete'] as $verb) {
            $this->actingAs($admin)->$verb(route('admin.backups.restore', $backup), ['confirmation' => 'RESTORE'])->assertStatus(405);
        }
        $this->actingAs($admin)->delete(route('admin.backups.download', $backup))->assertStatus(405);
        $this->assertSame($before, BackupLog::count());
    }

    // ================================================================ 13. retention only ever deals with real, successful, in-folder backups

    public function test_retention_never_touches_failed_or_safety_backups_or_files_outside_its_folder(): void
    {
        config(['backup.retention.keep' => 1]);

        $outside = $this->scratchDir('testing-outside');
        file_put_contents($outside . '/precious.zip', 'DO NOT TOUCH');

        $old = fn (int $hours) => ['started_at' => now()->subHours($hours), 'completed_at' => now()->subHours($hours)];

        $newest  = $this->makeBackup($old(1));
        $expired = $this->makeBackup($old(30));
        $failed  = $this->makeBackup($old(40) + ['status' => BackupLog::STATUS_FAILED, 'error_message' => 'x']);
        $safety  = $this->makeBackup($old(50) + ['type' => BackupLog::TYPE_PRE_RESTORE]);

        // A record whose file is a symlink to a file outside the folder.
        $linked = BackupLog::create($old(60) + [
            'filename' => 'clinic_backup_2026-09-01_01-01-01.zip', 'type' => BackupLog::TYPE_AUTOMATIC, 'size' => 12, 'status' => BackupLog::STATUS_SUCCESS,
        ]);
        if (!@symlink($outside . '/precious.zip', $this->dirPath . '/' . $linked->filename)) {
            $this->markTestSkipped('This environment cannot create symlinks.');
        }

        $result = app(BackupRetentionService::class)->apply();

        $this->assertSame(1, $result['deleted'], 'only the one expired real backup is removed');
        $this->assertFileDoesNotExist($this->dirPath . '/' . $expired->filename);
        $this->assertFileExists($this->dirPath . '/' . $newest->filename);
        $this->assertFileExists($this->dirPath . '/' . $failed->filename, 'a failed backup is not a retention candidate');
        $this->assertFileExists($this->dirPath . '/' . $safety->filename, 'the safety backup is never removed');
        $this->assertSame('DO NOT TOUCH', file_get_contents($outside . '/precious.zip'), 'nothing outside the backup folder is touched');
        $this->assertTrue(is_link($this->dirPath . '/' . $linked->filename), 'the symlink is left as found');
    }

    // ================================================================ 12. the scheduler is the one Hostinger cron will run

    public function test_the_only_scheduled_backup_is_the_daily_two_am_automatic_one(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains((string) $event->command, 'clinic:backup'));

        $this->assertCount(1, $events);

        $event = $events->first();
        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertStringContainsString('--type=automatic', $event->command);
        $this->assertTrue($event->withoutOverlapping, 'two runs can never overlap');
        $this->assertTrue($event->evenInMaintenanceMode);
        $this->assertSame('Asia/Manila', config('app.timezone'), '2:00 AM is the clinic\'s local time, whatever the server clock says');
    }

    // ================================================================ Windows: mixed separators and the rest of what the Admin must not see

    /** @return array<string,array{string}> */
    public static function windowsStylePaths(): array
    {
        return [
            'base path, backslash, then forward slashes (what storage_path() gives on Windows)' => ['{base}\\storage/app/private/backups/.tmp-abc/client.cnf'],
            'base path and a pure backslash tree'                                             => ['{base}\\storage\\app\\private\\backups\\.tmp-abc\\client.cnf'],
            'base path with forward slashes only'                                             => ['{base}/storage/app/private/backups/.tmp-abc/client.cnf'],
            'base path in forward-slash form, then backslashes'                               => ['{basefwd}\\storage\\app\\private\\restore-temp\\restore-0123456789abcdef\\database.sql'],
            'a drive path outside the project'                                                => ['C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe'],
            'a linux path'                                                                    => ['/usr/bin/mysqldump'],
        ];
    }

    #[DataProvider('windowsStylePaths')]
    public function test_no_server_path_survives_whatever_mix_of_separators_it_uses(string $path): void
    {
        $path = str_replace(['{base}', '{basefwd}'], [base_path(), str_replace('\\', '/', base_path())], $path);

        $shown = (new BackupLog(['error_message' => "mysqldump failed (exit code 2): mysqldump: Got error: 2002: cannot write $path while running"]))->display_error;

        foreach ([base_path(), str_replace('\\', '/', base_path()), 'client.cnf', '.tmp-abc', 'restore-temp', 'restore-0123456789abcdef', 'storage', 'laragon', 'mysqldump.exe', '/usr/bin'] as $secret) {
            $this->assertStringNotContainsString($secret, $shown, "'$secret' leaked in: $shown");
        }
        $this->assertStringContainsString('[server path]', $shown);
        $this->assertStringContainsString('Got error: 2002', $shown, 'the MySQL error code stays');
        $this->assertStringContainsString('while running', $shown, 'text after the path stays');
    }

    public function test_ip_addresses_and_internal_file_names_are_hidden_but_the_mysql_error_stays(): void
    {
        $raw = "mysqldump: Got error: 2002: Can't connect to MySQL server on '10.20.30.40:3306' (115); retried '192.168.1.5', '[::1]', 'fe80::1ff:fe23:4567:890a' and "
            . '2001:0db8:85a3:0000:0000:8a2e:0370:7334; could not write client.cnf in .tmp-a1b2c3d4e5f6 or restore-temp/restore-0123456789abcdef; '
            . 'lock files .backup.lock, .restore.lock and .restore-in-progress; at 12:30:45 on MySQL 8.0.30';

        $shown = (new BackupLog(['error_message' => $raw]))->display_error;

        foreach (['10.20.30.40', '192.168.1.5', '::1', 'fe80', '2001:0db8', 'client.cnf', '.tmp-a1b2c3d4e5f6', 'restore-temp', 'restore-0123456789abcdef', '.backup.lock', '.restore.lock', '.restore-in-progress'] as $secret) {
            $this->assertStringNotContainsString($secret, $shown, "'$secret' leaked in: $shown");
        }
        foreach (['Got error: 2002', "Can't connect to MySQL server", '12:30:45', '8.0.30'] as $kept) {
            $this->assertStringContainsString($kept, $shown, "'$kept' is safe and useful and must stay");
        }
    }

    public function test_backup_folder_paths_use_one_separator_style_on_windows_and_are_untouched_elsewhere(): void
    {
        // storage_path('app/private') on Windows is a MIXED path; the guard's folder must not be.
        $mixedRoot = 'C:\\laragon\\www\\clinic-record-system\\storage\\app/private';

        $this->assertSame(
            'C:\\laragon\\www\\clinic-record-system\\storage\\app\\private\\backups',
            BackupFilesystem::normalizePath($mixedRoot . '\\' . 'backups', '\\')
        );
        $this->assertSame($mixedRoot . '/backups', BackupFilesystem::normalizePath($mixedRoot . '/backups', '/'), 'Linux/macOS paths are never rewritten');

        $normalised = BackupFilesystem::normalizePath($mixedRoot . '\\' . 'backups', '\\');
        $this->assertStringContainsString('storage' . '\\' . 'app' . '\\' . 'private', $normalised);
        $this->assertStringNotContainsString('app/private', $normalised);

        // And the real guard still resolves inside the private root for a relative directory.
        config(['backup.directory' => 'backups']);
        $this->assertStringContainsString('storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'private', (new RestoreGuard())->directory());
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'backups', (new RestoreGuard())->directory());
    }
}
