<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\User;
use App\Models\UserLog;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Admin → Backup & Recovery (Phase 2): access control, history, Backup Now,
 * and download security.
 *
 * These tests never touch the real backups folder or run mysqldump: the
 * backup folder is a throw-away directory, and BackupService is faked where a
 * backup would be created. (Creating a real backup is covered by
 * `php artisan clinic:backup`.)
 */
class AdminBackupPageTest extends TestCase
{
    use RefreshDatabase;

    private string $dirName;

    private string $dirPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Private, throw-away backup folder on the same (non-public) local disk.
        $this->dirName = 'testing-backups-' . bin2hex(random_bytes(4));
        $this->dirPath = storage_path('app/private/' . $this->dirName);
        mkdir($this->dirPath, 0700, true);

        config(['backup.directory' => $this->dirName]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dirPath);

        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

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

        // Not mass-assignable on User, and the routes require a verified email.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** A real (tiny) archive on disk + its successful backup_logs row. */
    private function makeBackup(array $overrides = [], bool $withFile = true): BackupLog
    {
        $filename = $overrides['filename'] ?? 'clinic_backup_2026-09-30_07-57-58.zip';

        if ($withFile) {
            $zip = new ZipArchive();
            $zip->open($this->dirPath . '/' . $filename, ZipArchive::CREATE);
            $zip->addFromString('database/database.sql', "-- Dump completed\n");
            $zip->addFromString('manifest.json', json_encode([
                'database' => ['tables' => 39],
                'files'    => ['total' => 395],
            ]));
            $zip->close();
        }

        return BackupLog::create(array_merge([
            'filename'     => $filename,
            'type'         => BackupLog::TYPE_MANUAL,
            'size'         => $withFile ? filesize($this->dirPath . '/' . $filename) : 1234,
            'status'       => BackupLog::STATUS_SUCCESS,
            'started_at'   => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
        ], $overrides));
    }

    // ------------------------------------------------------- access control

    public function test_guest_is_sent_to_login(): void
    {
        $backup = $this->makeBackup();

        $this->get('/admin/backups')->assertRedirect('/login');
        $this->post('/admin/backups')->assertRedirect('/login');
        $this->get("/admin/backups/{$backup->id}/download")->assertRedirect('/login');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_blocked_everywhere(string $role): void
    {
        $user   = $this->makeUser($role);
        $backup = $this->makeBackup();

        $this->actingAs($user)->get('/admin/backups')->assertForbidden();
        $this->actingAs($user)->post('/admin/backups')->assertForbidden();

        $download = $this->actingAs($user)->get("/admin/backups/{$backup->id}/download");
        $download->assertForbidden();
        $this->assertStringNotContainsString('PK', substr((string) $download->getContent(), 0, 2));

        $this->assertSame(1, BackupLog::count(), 'A blocked request must not create a backup record.');
    }

    public static function nonAdminRoles(): array
    {
        return [['Staff'], ['Doctor'], ['Patient']];
    }

    // -------------------------------------------------------------- history

    public function test_admin_sees_empty_state_when_there_are_no_backups(): void
    {
        $this->actingAs($this->makeUser('Admin'))
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('No backups yet.')
            ->assertSee('Create your first backup to protect your ClinicRMS data.')
            ->assertDontSee('<table', false);
    }

    public function test_admin_sees_history_summary_and_download_link(): void
    {
        $backup = $this->makeBackup();

        $this->actingAs($this->makeUser('Admin'))
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('Backup &amp; Recovery', false)
            ->assertSee('Database + Files')
            ->assertSee('39 tables, 395 uploaded files')
            ->assertSee('Success')
            ->assertSee('Manual')
            ->assertSee(route('admin.backups.download', $backup), false)
            // Phase 4: a successful backup can also be restored (via its confirmation page).
            ->assertSee(route('admin.backups.restore', $backup), false);
    }

    public function test_failed_backup_has_no_download_and_hides_sensitive_details(): void
    {
        $failed = $this->makeBackup([
            'filename'      => 'clinic_backup_2026-09-30_07-49-00.zip',
            'status'        => BackupLog::STATUS_FAILED,
            'size'          => null,
            'error_message' => "mysqldump failed: Access denied for user 'clinic'@'localhost' at /var/www/clinic/storage/app/private/backups/.tmp-x/client.cnf",
        ], false);

        $this->actingAs($this->makeUser('Admin'))
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('Failed')
            ->assertSee('View details')
            ->assertDontSee(route('admin.backups.download', $failed), false)
            ->assertDontSee("'clinic'@'localhost'", false)
            ->assertDontSee('/var/www/clinic');
    }

    public function test_display_error_hides_user_host_and_paths_but_keeps_the_reason(): void
    {
        $log = new BackupLog([
            'error_message' => "mysqldump failed (exit code 2): Access denied for user 'root'@'127.0.0.1' (using password: ********); see C:\\laragon\\www\\clinic\\storage\\x and /usr/bin/mysqldump",
        ]);

        $shown = $log->display_error;

        $this->assertStringContainsString('Access denied', $shown);
        $this->assertStringContainsString('exit code 2', $shown);
        $this->assertStringNotContainsString("'root'@'127.0.0.1'", $shown);
        $this->assertStringNotContainsString('laragon', $shown);
        $this->assertStringNotContainsString('/usr/bin', $shown);
        $this->assertStringNotContainsString('[server path][server path]', $shown);
    }

    public function test_history_is_paginated(): void
    {
        foreach (range(1, 13) as $i) {
            $this->makeBackup(
                ['filename' => sprintf('clinic_backup_2026-09-%02d_10-00-00.zip', $i), 'started_at' => now()->subDays(14 - $i)],
                false
            );
        }

        $this->actingAs($this->makeUser('Admin'))
            ->get('/admin/backups')
            ->assertOk()
            ->assertSee('Showing')
            ->assertSee('1–10')
            ->assertSee('of')
            ->assertSee('13');
    }

    // ---------------------------------------------------------- Backup Now

    public function test_backup_now_creates_a_manual_backup_through_the_existing_service(): void
    {
        $admin = $this->makeUser('Admin');
        $log   = new BackupLog([
            'filename' => 'clinic_backup_2026-09-30_08-00-00.zip',
            'type'     => 'manual',
            'status'   => BackupLog::STATUS_SUCCESS,
            'size'     => 2048,
        ]);
        $log->id = 99;

        $this->mock(BackupService::class, function (MockInterface $mock) use ($admin, $log) {
            $mock->shouldReceive('create')->once()->with(BackupLog::TYPE_MANUAL, $admin->id)->andReturn($log);
        });

        $this->actingAs($admin)
            ->post('/admin/backups')
            ->assertRedirect('/admin/backups')
            ->assertSessionHas('success', 'Backup completed successfully.');

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $admin->id,
            'action'  => 'Created Backup',
            'module'  => 'Backup & Recovery',
        ]);
    }

    public function test_failed_backup_shows_a_safe_message_only(): void
    {
        $admin = $this->makeUser('Admin');
        $log   = new BackupLog([
            'filename'      => 'clinic_backup_2026-09-30_08-00-00.zip',
            'status'        => BackupLog::STATUS_FAILED,
            'error_message' => 'mysqldump failed: SECRET-DETAIL',
        ]);
        $log->id = 98;

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldReceive('create')->once()->andReturn($log));

        $this->actingAs($admin)
            ->post('/admin/backups')
            ->assertRedirect('/admin/backups')
            ->assertSessionHas('error', 'Backup failed. Please check the backup logs for details.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('user_logs', ['user_id' => $admin->id, 'action' => 'Backup Failed']);
    }

    public function test_unexpected_exception_never_reaches_the_browser(): void
    {
        $admin = $this->makeUser('Admin');

        $this->mock(BackupService::class, fn (MockInterface $m) => $m->shouldReceive('create')->once()->andThrow(new RuntimeException('SQLSTATE[HY000] secret connection detail')));

        $response = $this->actingAs($admin)->post('/admin/backups');

        $response->assertRedirect('/admin/backups')
            ->assertSessionHas('error', 'Backup failed. Please check the backup logs for details.');

        $this->assertStringNotContainsString('secret connection detail', json_encode(session()->all()));
    }

    // ------------------------------------------------------------- download

    public function test_admin_downloads_the_exact_archive(): void
    {
        $admin  = $this->makeUser('Admin');
        $backup = $this->makeBackup();

        $response = $this->actingAs($admin)->get("/admin/backups/{$backup->id}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('attachment; filename=' . $backup->filename, $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(
            md5_file($this->dirPath . '/' . $backup->filename),
            md5(file_get_contents($response->baseResponse->getFile()->getPathname())),
        );

        $this->assertDatabaseHas('user_logs', [
            'user_id' => $admin->id,
            'action'  => 'Downloaded Backup',
            'details' => $backup->filename,
        ]);
    }

    public function test_only_successful_backups_can_be_downloaded(): void
    {
        $admin = $this->makeUser('Admin');

        foreach ([BackupLog::STATUS_FAILED, BackupLog::STATUS_RUNNING] as $i => $status) {
            $backup = $this->makeBackup([
                'filename' => sprintf('clinic_backup_2026-08-0%d_00-00-00.zip', $i + 1),
                'status'   => $status,
            ]);

            $this->actingAs($admin)->get("/admin/backups/{$backup->id}/download")->assertNotFound();
        }
    }

    #[DataProvider('hostileFilenames')]
    public function test_a_tampered_record_can_never_serve_an_arbitrary_file(string $filename): void
    {
        // Something valuable outside the backup folder that must never be served.
        file_put_contents($this->dirPath . '/../secret-outside.txt', 'TOP-SECRET');

        $backup = $this->makeBackup(['filename' => $filename], false);

        $response = $this->actingAs($this->makeUser('Admin'))->get("/admin/backups/{$backup->id}/download");

        $response->assertNotFound();
        $this->assertStringNotContainsString('TOP-SECRET', (string) $response->getContent());

        @unlink($this->dirPath . '/../secret-outside.txt');
    }

    public static function hostileFilenames(): array
    {
        return [
            'parent folder'   => ['../secret-outside.txt'],
            'deep traversal'  => ['../../../.env'],
            'absolute path'   => ['/etc/passwd'],
            'windows path'    => ['..\\..\\.env'],
            'nested traversal after a valid name' => ['clinic_backup_2026-09-30_07-57-58.zip/../../.env'],
            'wrong extension' => ['clinic_backup_2026-09-30_07-57-58.zip.php'],
            'not our naming'  => ['anything.zip'],
            'htaccess'        => ['.htaccess'],
        ];
    }

    public function test_a_symlink_pointing_outside_the_backup_folder_is_not_served(): void
    {
        file_put_contents($this->dirPath . '/../secret-outside.txt', 'TOP-SECRET');

        $name = 'clinic_backup_2026-10-01_00-00-00.zip';

        if (!@symlink($this->dirPath . '/../secret-outside.txt', $this->dirPath . '/' . $name)) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }

        $backup = $this->makeBackup(['filename' => $name], false);

        $response = $this->actingAs($this->makeUser('Admin'))->get("/admin/backups/{$backup->id}/download");

        $response->assertRedirect('/admin/backups');
        $this->assertStringNotContainsString('TOP-SECRET', (string) $response->getContent());

        @unlink($this->dirPath . '/' . $name);
        @unlink($this->dirPath . '/../secret-outside.txt');
    }

    public function test_missing_file_gives_a_friendly_message_not_an_error(): void
    {
        $backup = $this->makeBackup([], false);

        $this->actingAs($this->makeUser('Admin'))
            ->get("/admin/backups/{$backup->id}/download")
            ->assertRedirect('/admin/backups')
            ->assertSessionHas('error');
    }

    public function test_download_route_only_accepts_a_numeric_record_id(): void
    {
        $admin = $this->makeUser('Admin');

        $this->actingAs($admin)->get('/admin/backups/abc/download')->assertNotFound();
        $this->actingAs($admin)->get('/admin/backups/..%2F..%2F.env/download')->assertNotFound();
        $this->actingAs($admin)->get('/admin/backups/9999/download')->assertNotFound();
    }

    public function test_download_ignores_any_path_the_browser_sends(): void
    {
        $backup = $this->makeBackup();
        file_put_contents($this->dirPath . '/../secret-outside.txt', 'TOP-SECRET');

        $response = $this->actingAs($this->makeUser('Admin'))
            ->get("/admin/backups/{$backup->id}/download?file=../secret-outside.txt&path=/etc/passwd");

        $response->assertOk();
        $this->assertSame($backup->filename, $response->baseResponse->getFile()->getFilename());

        @unlink($this->dirPath . '/../secret-outside.txt');
    }
}
