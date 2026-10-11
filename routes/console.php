<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\BackupSettings;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Automatic backup (Backup & Recovery, Phase 3)
|--------------------------------------------------------------------------
| Reuses the existing `clinic:backup` command (BackupService does the work and
| writes the backup_logs row as an "automatic" backup; the command then applies
| retention). Time / on-off / retention come from config/backup.php and the run
| follows the application timezone.
|
| Trigger it with:  php artisan schedule:run   (every minute, from cron)
|
| withoutOverlapping(60): a second schedule:run in the same minute won't start
| another one. The expiry is short on purpose — the default of 24 hours would
| keep a crashed run's mutex alive into the next day and skip tomorrow's backup.
| (BackupService's own file lock still prevents two backups running at once.)
*/
Schedule::command('clinic:backup', ['--type=automatic'])
    ->dailyAt(BackupSettings::automaticTime())
    ->when(fn () => BackupSettings::automaticEnabled())
    ->withoutOverlapping(60)
    ->evenInMaintenanceMode()
    ->description('Automatic ClinicRMS backup + retention cleanup');


/*
|--------------------------------------------------------------------------
| Registration staging cleanup
|--------------------------------------------------------------------------
| A Valid ID kept between failed registration attempts lives in the private
| `registration_tmp` folder. Anything older than a day belongs to an abandoned
| registration, so it is removed (finished registrations delete their own copy).
*/
Schedule::call(function () {
    $disk   = \Illuminate\Support\Facades\Storage::disk('private');
    $cutoff = now()->subDay()->getTimestamp();

    foreach ($disk->files('registration_tmp') as $file) {
        if (!str_starts_with(basename($file), '.') && $disk->lastModified($file) < $cutoff) {
            $disk->delete($file);
        }
    }
})->name('registration-staging-cleanup')->daily();
