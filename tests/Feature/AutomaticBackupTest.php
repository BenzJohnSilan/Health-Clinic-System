<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\BackupSettings;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/**
 * Backup & Recovery, Phase 3: automatic daily backup + retention.
 *
 * Nothing here touches the real backups folder or runs mysqldump. The backup
 * folder is a throw-away directory, BackupService is faked wherever a real
 * dump would be needed, and every deletion is checked against files that must
 * survive (unrelated files, files outside the folder, symlinks, orphans).
 */
class AutomaticBackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dirName;

    private string $dirPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dirName = 'testing-backups-' . bin2hex(random_bytes(4));
        $this->dirPath = storage_path('app/private/' . $this->dirName);
        mkdir($this->dirPath, 0700, true);

        config(['backup.directory' => $this->dirName]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->dirPath);
        @unlink(dirname($this->dirPath) . '/outside-secret.txt');

        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function fileFor(int $n): string
    {
        return sprintf('clinic_backup_2026-09-%02d_02-00-00.zip', $n);
    }

    /** A real archive on disk (optional) + a backup_logs row. Larger $n = newer. */
    private function makeBackup(int $n, string $status = BackupLog::STATUS_SUCCESS, bool $withFile = true, string $type = 'automatic'): BackupLog
    {
        $filename = $this->fileFor($n);

        if ($withFile) {
            $zip = new ZipArchive();
            $zip->open($this->dirPath . '/' . $filename, ZipArchive::CREATE);
            $zip->addFromString('manifest.json', json_encode(['database' => ['tables' => 39], 'files' => ['total' => 395]]));
            $zip->close();
        }

        return BackupLog::create([
            'filename'     => $filename,
            'type'         => $type,
            'size'         => $withFile ? filesize($this->dirPath . '/' . $filename) : null,
            'status'       => $status,
            'started_at'   => Carbon::create(2026, 9, $n, 2, 0, 0),
            'completed_at' => Carbon::create(2026, 9, $n, 2, 1, 0),
        ]);
    }

    private function exists(int $n): bool
    {
        return is_file($this->dirPath . '/' . $this->fileFor($n));
    }

    private function makeAdmin(): User
    {
        $user = User::create([
            'first_name' => 'Admin', 'last_name' => 'Tester', 'birthdate' => '1990-01-01', 'gender' => 'Male',
            'civil_status' => 'Single', 'address' => 'Sample St', 'contact_number' => '0917' . random_int(1000000, 9999999),
            'username' => 'admin' . random_int(100000, 999999), 'email' => 'admin' . random_int(1000, 9999) . '@test.com',
            'password' => bcrypt('password'), 'role' => 'Admin', 'approval_status' => 'Approved',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function backupEvent(): Event
    {
        Artisan::call('list'); // boots the console kernel so routes/console.php registers its schedule

        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (Event $e) => str_contains($e->command, 'clinic:backup'));

        $this->assertCount(1, $events, 'The automatic backup must be scheduled exactly once.');

        return $events->first();
    }

    private function due(Event $event): bool
    {
        return $event->isDue($this->app) && $event->filtersPass($this->app);
    }

    /** What BackupService would leave behind after a real, successful run. */
    private function fakeSuccessfulRun(int $n, string $type): \Closure
    {
        return function (string $givenType) use ($n, $type) {
            $this->assertSame($type, $givenType);

            return $this->makeBackup($n, BackupLog::STATUS_SUCCESS, true, $givenType);
        };
    }

    private function mockService(\Closure $create): void
    {
        $this->mock(BackupService::class, function (MockInterface $m) use ($create) {
            $m->shouldReceive('create')->once()->andReturnUsing($create);
            $m->shouldReceive('summary')->andReturn(['tables' => 39, 'files' => 395, 'directories' => [], 'location' => 'x']);
        });
    }

    // ------------------------------------------------------------ 1. scheduler

    public function test_scheduler_registers_one_daily_automatic_backup_at_2am(): void
    {
        $event = $this->backupEvent();

        $this->assertSame('0 2 * * *', $event->expression);
        $this->assertStringContainsString('clinic:backup', $event->command);
        $this->assertStringContainsString('--type=automatic', $event->command);
        $this->assertTrue($event->withoutOverlapping, 'A second schedule:run must not start a duplicate.');
        $this->assertTrue($event->runsInMaintenanceMode(), 'Backups should still run while the site is in maintenance mode.');
    }

    public function test_the_overlap_mutex_expires_within_the_hour_so_a_crash_cannot_skip_tomorrow(): void
    {
        $this->assertLessThanOrEqual(60, $this->backupEvent()->expiresAt);
    }

    public function test_it_fires_at_2am_application_time_and_not_at_other_times(): void
    {
        $event = $this->backupEvent();
        $tz    = config('app.timezone');

        Carbon::setTestNow(Carbon::create(2026, 10, 1, 2, 0, 0, $tz));
        $this->assertTrue($this->due($event), '02:00 in the application timezone must fire.');

        Carbon::setTestNow(Carbon::create(2026, 10, 1, 1, 59, 0, $tz));
        $this->assertFalse($this->due($event));

        Carbon::setTestNow(Carbon::create(2026, 10, 1, 2, 1, 0, $tz));
        $this->assertFalse($this->due($event));

        // 02:00 UTC is a different wall-clock time in Asia/Manila: it must NOT fire.
        Carbon::setTestNow(Carbon::create(2026, 10, 1, 2, 0, 0, 'UTC'));
        $this->assertNotSame('UTC', $tz);
        $this->assertFalse($this->due($event));
    }

    public function test_the_schedule_can_be_turned_off_from_config(): void
    {
        $event = $this->backupEvent();
        Carbon::setTestNow(Carbon::create(2026, 10, 1, 2, 0, 0, config('app.timezone')));

        config(['backup.automatic.enabled' => false]);
        $this->assertFalse($this->due($event));

        config(['backup.automatic.enabled' => true]);
        $this->assertTrue($this->due($event));
    }

    public function test_settings_are_validated_so_a_typo_cannot_break_anything(): void
    {
        config(['backup.automatic.time' => 'soon']);
        $this->assertSame('02:00', BackupSettings::automaticTime());

        config(['backup.automatic.time' => '25:99']);
        $this->assertSame('02:00', BackupSettings::automaticTime());

        config(['backup.automatic.time' => '03:30']);
        $this->assertSame('03:30', BackupSettings::automaticTime());
        $this->assertSame('3:30 AM', BackupSettings::automaticTimeLabel());

        config(['backup.retention.keep' => 0]);
        $this->assertSame(1, BackupSettings::retentionKeep(), 'Retention must never be able to keep 0.');

        config(['backup.retention.keep' => '5']);
        $this->assertSame(5, BackupSettings::retentionKeep());

        $this->assertSame(7, (new \ReflectionClassConstant(BackupSettings::class, 'DEFAULT_KEEP'))->getValue());
    }

    // ------------------------------------- 2 + 3 + 4. reuses the existing backup

    public function test_the_scheduled_command_runs_the_existing_service_as_an_automatic_backup(): void
    {
        $this->mock(BackupService::class, function (MockInterface $m) {
            $m->shouldReceive('create')->once()->with(BackupLog::TYPE_AUTOMATIC)->andReturnUsing(
                fn () => $this->makeBackup(20, BackupLog::STATUS_SUCCESS, true, 'automatic')
            );
            $m->shouldReceive('summary')->andReturn(['tables' => 39, 'files' => 395, 'directories' => [], 'location' => 'x']);
        });

        $this->assertSame(0, Artisan::call('clinic:backup', ['--type' => 'automatic']), Artisan::output());

        $log = BackupLog::firstOrFail();
        $this->assertSame('automatic', $log->type);
        $this->assertSame('success', $log->status);
        $this->assertNull($log->created_by, 'A scheduled backup has no signed-in user.');
    }

    public function test_scheduled_event_and_the_manual_command_share_the_same_command(): void
    {
        $this->assertStringContainsString('clinic:backup', $this->backupEvent()->command);
        $this->assertArrayHasKey('clinic:backup', Artisan::all(), 'There must be a single backup command.');
        $this->assertArrayNotHasKey('clinic:automatic-backup', Artisan::all());
    }

    public function test_automatic_backup_stays_in_history_labelled_automatic(): void
    {
        $this->makeBackup(20, BackupLog::STATUS_SUCCESS, true, 'automatic');
        $this->makeBackup(19, BackupLog::STATUS_SUCCESS, true, 'manual');

        $page = $this->actingAs($this->makeAdmin())->get('/admin/backups')->assertOk();

        $page->assertSee('Automatic')->assertSee('Manual');
        $page->assertSee('every day at 2:00 AM')->assertSee('latest 7 successful backups');
        $this->assertSame(2, BackupLog::where('status', 'success')->count());
    }

    public function test_the_page_says_when_automatic_backup_is_off(): void
    {
        config(['backup.automatic.enabled' => false]);

        $this->actingAs($this->makeAdmin())->get('/admin/backups')->assertOk()
            ->assertSee('Automatic backup is turned off')
            ->assertDontSee('every day at');
    }

    // ------------------------------------------------- 5 + 6. retention keeps 7

    public function test_retention_keeps_the_newest_7_successful_backups_and_removes_older_ones(): void
    {
        foreach (range(1, 10) as $n) {
            $this->makeBackup($n);
        }

        $result = app(BackupRetentionService::class)->apply();

        $this->assertSame(7, $result['kept']);
        $this->assertSame(3, $result['deleted']);
        $this->assertGreaterThan(0, $result['freed']);

        foreach ([1, 2, 3] as $old) {
            $this->assertFalse($this->exists($old), "backup $old is older than the newest 7 and must be removed");
            $this->assertDatabaseMissing('backup_logs', ['filename' => $this->fileFor($old)]);
        }
        foreach ([4, 5, 6, 7, 8, 9, 10] as $new) {
            $this->assertTrue($this->exists($new), "backup $new is in the newest 7 and must stay");
            $this->assertDatabaseHas('backup_logs', ['filename' => $this->fileFor($new)]);
        }
    }

    public function test_retention_orders_by_completion_time_not_by_filename_or_id(): void
    {
        foreach (range(1, 8) as $n) {
            $this->makeBackup($n);
        }
        // The lowest id / oldest filename actually finished LAST, so it is the newest.
        BackupLog::where('filename', $this->fileFor(1))->update(['completed_at' => Carbon::create(2026, 12, 1)]);

        app(BackupRetentionService::class)->apply();

        $this->assertTrue($this->exists(1), 'the most recently completed backup must be kept');
        $this->assertFalse($this->exists(2), 'the oldest completed backup is the one removed');
    }

    public function test_nothing_is_removed_when_there_are_7_or_fewer(): void
    {
        foreach (range(1, 7) as $n) {
            $this->makeBackup($n);
        }

        $result = app(BackupRetentionService::class)->apply();

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(7, BackupLog::count());
    }

    public function test_a_second_run_removes_nothing_more(): void
    {
        foreach (range(1, 9) as $n) {
            $this->makeBackup($n);
        }

        app(BackupRetentionService::class)->apply();
        $second = app(BackupRetentionService::class)->apply();

        $this->assertSame(0, $second['deleted']);
        $this->assertSame(7, BackupLog::count());
    }

    public function test_retention_can_be_set_lower_but_never_below_one(): void
    {
        foreach (range(1, 4) as $n) {
            $this->makeBackup($n);
        }

        app(BackupRetentionService::class)->apply(0);

        $this->assertTrue($this->exists(4), 'Even with keep=0 the newest backup must survive.');
        $this->assertSame(1, BackupLog::count());
    }

    // --------------------------- failed / running / missing records are protected

    public function test_failed_and_running_records_are_never_removed(): void
    {
        $this->makeBackup(1, BackupLog::STATUS_FAILED, false);
        $this->makeBackup(2, BackupLog::STATUS_RUNNING, false);
        foreach (range(3, 12) as $n) {
            $this->makeBackup($n);
        }

        app(BackupRetentionService::class)->apply();

        $this->assertDatabaseHas('backup_logs', ['filename' => $this->fileFor(1), 'status' => 'failed']);
        $this->assertDatabaseHas('backup_logs', ['filename' => $this->fileFor(2), 'status' => 'running']);
        $this->assertSame(7, BackupLog::where('status', 'success')->count());
    }

    public function test_a_record_with_a_missing_file_is_left_alone_and_never_costs_a_real_backup(): void
    {
        // The 3 newest records lost their files (someone cleaned the folder).
        foreach ([8, 9, 10] as $n) {
            $this->makeBackup($n, BackupLog::STATUS_SUCCESS, false);
        }
        foreach (range(1, 7) as $n) {
            $this->makeBackup($n);
        }

        $result = app(BackupRetentionService::class)->apply();

        $this->assertSame(3, $result['missing']);
        $this->assertSame(0, $result['deleted']);
        foreach (range(1, 7) as $n) {
            $this->assertTrue($this->exists($n), 'A real backup must not be deleted because newer records have no file.');
        }
        $this->assertSame(10, BackupLog::count(), 'Records with missing files are not deleted.');
    }

    // ------------------------------------------- 7. a failed backup cleans nothing

    public function test_a_failed_backup_never_triggers_cleanup(): void
    {
        foreach (range(1, 9) as $n) {
            $this->makeBackup($n);
        }

        $this->mock(BackupService::class, function (MockInterface $m) {
            $m->shouldReceive('create')->once()->andReturnUsing(fn () => BackupLog::create([
                'filename' => 'clinic_backup_2026-09-30_02-00-00.zip', 'type' => 'automatic', 'status' => 'failed',
                'started_at' => now(), 'completed_at' => now(), 'error_message' => 'boom',
            ]));
        });
        $this->mock(BackupRetentionService::class, fn (MockInterface $m) => $m->shouldNotReceive('apply'));

        $this->artisan('clinic:backup', ['--type' => 'automatic'])->assertExitCode(1);

        foreach (range(1, 9) as $n) {
            $this->assertTrue($this->exists($n), "backup $n must survive a failed backup run");
        }
        $this->assertSame(9, BackupLog::where('status', 'success')->count());
    }

    public function test_a_real_failure_is_logged_as_automatic_failed_and_deletes_nothing(): void
    {
        // No fake: the real BackupService refuses to run on the sqlite test database.
        foreach (range(1, 9) as $n) {
            $this->makeBackup($n);
        }
        $this->mock(BackupRetentionService::class, fn (MockInterface $m) => $m->shouldNotReceive('apply'));

        $this->artisan('clinic:backup', ['--type' => 'automatic'])->assertExitCode(1);

        $failed = BackupLog::where('status', 'failed')->firstOrFail();
        $this->assertSame('automatic', $failed->type);
        $this->assertNotEmpty($failed->error_message);
        $this->assertSame(9, BackupLog::where('status', 'success')->count());
        $this->assertCount(9, glob($this->dirPath . '/clinic_backup_*.zip'));
    }

    // -------------------------------------------- retention runs after a success

    public function test_a_successful_automatic_backup_applies_retention(): void
    {
        foreach (range(1, 8) as $n) {
            $this->makeBackup($n);
        }
        $this->mockService($this->fakeSuccessfulRun(9, 'automatic'));

        $this->artisan('clinic:backup', ['--type' => 'automatic'])
            ->expectsOutputToContain('Backup completed successfully')
            ->expectsOutputToContain('keeping the latest 7 successful backups')
            ->expectsOutputToContain('removed 2 older backup(s)')
            ->assertExitCode(0);

        $this->assertSame(7, BackupLog::where('status', 'success')->count());
        $this->assertFalse($this->exists(1));
        $this->assertFalse($this->exists(2));
        $this->assertTrue($this->exists(9), 'The backup that was just made is never removed.');
    }

    public function test_a_cleanup_problem_does_not_turn_a_good_backup_into_a_failure(): void
    {
        $this->mockService($this->fakeSuccessfulRun(9, 'automatic'));
        $this->mock(BackupRetentionService::class, fn (MockInterface $m) => $m->shouldReceive('apply')->andThrow(new \RuntimeException('disk hiccup')));

        $this->artisan('clinic:backup', ['--type' => 'automatic'])
            ->expectsOutputToContain('old backups could not be cleaned up')
            ->assertExitCode(0);

        $this->assertTrue($this->exists(9));
    }

    // ------------------------------------------------------------ 8. path safety

    public function test_cleanup_only_removes_backup_archives_and_leaves_everything_else(): void
    {
        foreach (range(1, 9) as $n) {
            $this->makeBackup($n);
        }

        // Things that live in / near the folder and must NEVER be touched.
        file_put_contents($this->dirPath . '/.htaccess', 'Require all denied');
        file_put_contents($this->dirPath . '/notes.txt', 'keep me');
        file_put_contents($this->dirPath . '/clinic_backup_2026-01-01_00-00-00.zip.bak', 'keep me');
        file_put_contents($this->dirPath . '/clinic_backup_2026-01-01_00-00-00.zip', 'orphan archive without a record');
        file_put_contents(dirname($this->dirPath) . '/outside-secret.txt', 'TOP-SECRET');

        app(BackupRetentionService::class)->apply();

        foreach (['.htaccess', 'notes.txt', 'clinic_backup_2026-01-01_00-00-00.zip.bak', 'clinic_backup_2026-01-01_00-00-00.zip'] as $keep) {
            $this->assertFileExists($this->dirPath . '/' . $keep, "$keep is not a recorded backup and must not be deleted");
        }
        $this->assertFileExists(dirname($this->dirPath) . '/outside-secret.txt');
    }

    public function test_a_tampered_record_can_never_make_cleanup_delete_a_file_outside_the_folder(): void
    {
        file_put_contents(dirname($this->dirPath) . '/outside-secret.txt', 'TOP-SECRET');

        foreach (range(3, 12) as $n) {
            $this->makeBackup($n);
        }

        // Oldest "successful" records with hostile filenames (cleanup would pick them first).
        foreach (['../outside-secret.txt', '../../../.env', '/etc/passwd', '..\\..\\.env', 'clinic_backup_2026-09-01_02-00-00.zip/../../outside-secret.txt'] as $i => $evil) {
            BackupLog::create([
                'filename' => $evil, 'type' => 'automatic', 'status' => 'success', 'size' => 1,
                'started_at' => Carbon::create(2026, 8, 1 + $i), 'completed_at' => Carbon::create(2026, 8, 1 + $i),
            ]);
        }

        $result = app(BackupRetentionService::class)->apply();

        $this->assertFileExists(dirname($this->dirPath) . '/outside-secret.txt');
        $this->assertFileExists(base_path('.env.example'));
        $this->assertSame(5, $result['missing'], 'Hostile records are treated as having no usable file.');
        $this->assertSame(5, BackupLog::where('filename', 'like', '%outside-secret%')->orWhere('filename', 'like', '%.env%')->orWhere('filename', '/etc/passwd')->count(), 'Hostile records are left as they are, not "cleaned".');
    }

    public function test_a_symlink_is_never_followed_or_deleted(): void
    {
        file_put_contents(dirname($this->dirPath) . '/outside-secret.txt', 'TOP-SECRET');
        file_put_contents($this->dirPath . '/.htaccess', 'Require all denied');

        // Two old "backups" that are really symlinks: one to a file outside, one to a protected file in the SAME folder.
        $toOutside = $this->fileFor(1);
        $toSibling = $this->fileFor(2);
        if (!@symlink(dirname($this->dirPath) . '/outside-secret.txt', $this->dirPath . '/' . $toOutside)
            || !@symlink($this->dirPath . '/.htaccess', $this->dirPath . '/' . $toSibling)) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }
        foreach ([1, 2] as $n) {
            BackupLog::create([
                'filename' => $this->fileFor($n), 'type' => 'automatic', 'status' => 'success', 'size' => 1,
                'started_at' => Carbon::create(2026, 9, $n), 'completed_at' => Carbon::create(2026, 9, $n),
            ]);
        }
        foreach (range(3, 12) as $n) {
            $this->makeBackup($n);
        }

        app(BackupRetentionService::class)->apply();

        $this->assertFileExists(dirname($this->dirPath) . '/outside-secret.txt');
        $this->assertFileExists($this->dirPath . '/.htaccess', 'A symlink to a sibling file must not delete that file.');
        $this->assertSame('TOP-SECRET', file_get_contents(dirname($this->dirPath) . '/outside-secret.txt'));
    }

    public function test_an_old_record_that_is_a_symlink_to_the_newest_backup_cannot_delete_it(): void
    {
        foreach (range(3, 12) as $n) {
            $this->makeBackup($n);
        }

        // "Backup 1" is only an alias for the NEWEST real backup (12). Without
        // protection, cleaning up record 1 would delete backup 12 itself.
        if (!@symlink($this->dirPath . '/' . $this->fileFor(12), $this->dirPath . '/' . $this->fileFor(1))) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }
        BackupLog::create([
            'filename' => $this->fileFor(1), 'type' => 'automatic', 'status' => 'success', 'size' => 1,
            'started_at' => Carbon::create(2026, 9, 1), 'completed_at' => Carbon::create(2026, 9, 1),
        ]);

        app(BackupRetentionService::class)->apply();

        $this->assertFileExists($this->dirPath . '/' . $this->fileFor(12), 'The newest backup must never be deleted through an alias.');
        $this->assertDatabaseHas('backup_logs', ['filename' => $this->fileFor(12)]);
    }

    public function test_download_also_refuses_a_symlink_to_a_sibling_file(): void
    {
        file_put_contents($this->dirPath . '/.htaccess', 'Require all denied');
        if (!@symlink($this->dirPath . '/.htaccess', $this->dirPath . '/' . $this->fileFor(5))) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }
        $backup = BackupLog::create([
            'filename' => $this->fileFor(5), 'type' => 'manual', 'status' => 'success', 'size' => 1,
            'started_at' => now(), 'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->makeAdmin())->get("/admin/backups/{$backup->id}/download");

        $response->assertRedirect('/admin/backups');
        $this->assertStringNotContainsString('Require all denied', (string) $response->getContent());
    }

    // ------------------------------------------------ concurrency with a backup

    public function test_cleanup_is_skipped_while_a_backup_is_running(): void
    {
        foreach (range(1, 10) as $n) {
            $this->makeBackup($n);
        }

        // Simulate BackupService holding its lock.
        $held = fopen($this->dirPath . '/.backup.lock', 'c');
        $this->assertTrue(flock($held, LOCK_EX | LOCK_NB));

        $result = app(BackupRetentionService::class)->apply();

        flock($held, LOCK_UN);
        fclose($held);

        $this->assertNotNull($result['skipped']);
        $this->assertSame(0, $result['deleted']);
        $this->assertCount(10, glob($this->dirPath . '/clinic_backup_*.zip'));

        // Once the backup has finished, the next cleanup goes ahead.
        $this->assertSame(3, app(BackupRetentionService::class)->apply()['deleted']);
    }

    public function test_cleanup_does_nothing_if_the_backup_folder_does_not_exist(): void
    {
        File::deleteDirectory($this->dirPath);
        $this->makeBackup(1, BackupLog::STATUS_SUCCESS, false);

        $result = app(BackupRetentionService::class)->apply();

        $this->assertNotNull($result['skipped']);
        $this->assertSame(1, BackupLog::count());
    }

    // ------------------------------------------ 9 + 10 + 11. Phase 2 unchanged

    public function test_backup_now_still_works_and_stays_manual(): void
    {
        $admin = $this->makeAdmin();

        $this->mock(BackupService::class, function (MockInterface $m) use ($admin) {
            $m->shouldReceive('create')->once()->with(BackupLog::TYPE_MANUAL, $admin->id)->andReturnUsing(
                fn () => $this->makeBackup(20, BackupLog::STATUS_SUCCESS, true, 'manual')
            );
        });

        $this->actingAs($admin)->post('/admin/backups')
            ->assertRedirect('/admin/backups')
            ->assertSessionHas('success', 'Backup completed successfully.');
    }

    public function test_download_still_works_for_a_retained_backup_and_fails_cleanly_for_a_removed_one(): void
    {
        $admin = $this->makeAdmin();
        foreach (range(1, 8) as $n) {
            $this->makeBackup($n);
        }
        $oldest = BackupLog::where('filename', $this->fileFor(1))->first();
        $newest = BackupLog::where('filename', $this->fileFor(8))->first();

        app(BackupRetentionService::class)->apply();

        $this->actingAs($admin)->get("/admin/backups/{$newest->id}/download")->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->actingAs($admin)->get("/admin/backups/{$oldest->id}/download")->assertNotFound(); // record + file removed by retention
    }

    #[DataProvider('adminOnlyRoutes')]
    public function test_admin_only_protection_is_unchanged(string $role): void
    {
        $user = User::create([
            'first_name' => $role, 'last_name' => 'Tester', 'birthdate' => '1990-01-01', 'gender' => 'Male',
            'civil_status' => 'Single', 'address' => 'Sample St', 'contact_number' => '0917' . random_int(1000000, 9999999),
            'username' => strtolower($role) . random_int(100000, 999999), 'email' => strtolower($role) . random_int(1000, 9999) . '@test.com',
            'password' => bcrypt('password'), 'role' => $role, 'approval_status' => 'Approved',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $backup = $this->makeBackup(5);

        $this->actingAs($user)->get('/admin/backups')->assertForbidden();
        $this->actingAs($user)->post('/admin/backups')->assertForbidden();
        $this->actingAs($user)->get("/admin/backups/{$backup->id}/download")->assertForbidden();
    }

    public static function adminOnlyRoutes(): array
    {
        return [['Staff'], ['Doctor'], ['Patient']];
    }
}
