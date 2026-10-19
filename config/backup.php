<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup storage location
    |--------------------------------------------------------------------------
    |
    | Archives are written to this folder on the "local" disk, i.e.
    | storage/app/private/backups. That disk is NOT web-accessible.
    | BackupService refuses to run if the folder ever resolves inside a
    | public directory. Must be a local-driver disk.
    |
    */
    'disk'      => 'local',
    'directory' => 'backups',

    /*
    |--------------------------------------------------------------------------
    | mysqldump binary
    |--------------------------------------------------------------------------
    |
    | Leave BACKUP_MYSQLDUMP_PATH empty to auto-detect (PATH, Laragon and
    | common Linux locations). Set it to the full path if auto-detection
    | fails, e.g. C:\laragon\bin\mysql\mysql-8.0.30\bin\mysqldump.exe
    |
    */
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),

    /*
    | How the database is dumped:
    |   auto      mysqldump, or - when the host switched off proc_open (Hostinger)
    |             - a PHP-only dump with the same file layout (default)
    |   php       always the PHP-only dump
    |   mysqldump always the mysqldump program
    */
    'dump_driver' => env('BACKUP_DUMP_DRIVER', 'auto'),

    // Seconds before a stuck mysqldump run is aborted.
    'dump_timeout' => (int) env('BACKUP_DUMP_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Uploaded files to include
    |--------------------------------------------------------------------------
    |
    | Only persistent, user-uploaded data is included (never vendor,
    | node_modules, caches, sessions, logs or .env). Folders are relative to
    | the root of the given disk; missing folders are skipped.
    |
    |   avatars           profile photos (patients, staff, doctors, admins)
    |   valid_ids         government ID images uploaded at registration
    |   laboratory_results uploaded laboratory result files
    |   signatures        doctor signatures used on medical certificates
    |
    | "temp_uploads" is intentionally excluded: it is a transient staging
    | area for registrations that are not finished yet.
    |
    */
    'files' => [
        'disk'        => 'public',
        'directories' => [
            'avatars',
            'valid_ids',
            'laboratory_results',
            'signatures',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic backup (Phase 3)
    |--------------------------------------------------------------------------
    |
    | Runs the existing `clinic:backup --type=automatic` command once a day
    | through Laravel's scheduler, so it is recorded in backup_logs as an
    | "Automatic" backup. It follows the application timezone (config/app.php).
    |
    | The scheduler only fires when the server runs `php artisan schedule:run`
    | every minute (a cron job — on Hostinger, add it in hPanel > Cron Jobs).
    |
    | time: 24-hour "HH:MM". An invalid value falls back to 02:00.
    |
    */
    'automatic' => [
        'enabled' => env('BACKUP_AUTOMATIC_ENABLED', true),
        'time'    => env('BACKUP_AUTOMATIC_TIME', '02:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention (Phase 3)
    |--------------------------------------------------------------------------
    |
    | After a backup SUCCEEDS, only the newest `keep` successful backups are
    | kept and older archives are deleted. Never lower than 1. Nothing is
    | deleted when a backup fails, and only files that belong to a backup
    | record inside the private backup folder can ever be removed.
    |
    */
    'retention' => [
        'keep' => (int) env('BACKUP_RETENTION_KEEP', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Restore (Phase 4)
    |--------------------------------------------------------------------------
    |
    | preserve_tables: tables that KEEP their current contents during a
    | restore instead of being replaced by the backup's copy.
    |   backup_logs   the backup/restore history itself (always preserved, even
    |                 if removed from this list)
    |   sessions      keeps people logged in
    |   cache, cache_locks   framework cache and scheduler locks
    |   jobs, job_batches, failed_jobs   the queue: restoring an old queue
    |                 could re-send SMS/e-mail that were already sent
    |
    | max_uncompressed_mb: a backup that would unpack to more than this is
    | refused (protects against a "zip bomb").
    |
    | temp_directory: private working folder (on the backup disk) where a
    | backup is unpacked and checked before anything is replaced.
    |
    */
    'restore' => [
        'preserve_tables'     => ['backup_logs', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
        'max_uncompressed_mb' => (int) env('BACKUP_RESTORE_MAX_MB', 4096),
        'temp_directory'      => 'restore-temp',
    ],

];