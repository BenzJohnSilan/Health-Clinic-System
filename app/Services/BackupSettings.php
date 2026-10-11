<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * The one place that reads config('backup.automatic') and
 * config('backup.retention').
 *
 * The scheduler (routes/console.php), the retention service and the Admin
 * page all go through here, so they always agree — and a typo in .env can
 * never break the scheduler or make retention delete everything:
 *
 *  - an invalid time falls back to 02:00,
 *  - the retention count can never be lower than 1.
 */
class BackupSettings
{
    public const DEFAULT_TIME = '02:00';

    public const DEFAULT_KEEP = 7;

    public static function automaticEnabled(): bool
    {
        return filter_var(config('backup.automatic.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Daily run time as "HH:MM" (24-hour), in the application timezone.
     */
    public static function automaticTime(): string
    {
        $time = trim((string) config('backup.automatic.time', self::DEFAULT_TIME));

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $time) === 1 ? $time : self::DEFAULT_TIME;
    }

    /**
     * Friendly form of automaticTime(), e.g. "2:00 AM".
     */
    public static function automaticTimeLabel(): string
    {
        return Carbon::createFromFormat('H:i', self::automaticTime())->format('g:i A');
    }

    /**
     * How many successful backups retention keeps (never less than 1).
     */
    public static function retentionKeep(): int
    {
        return max(1, (int) config('backup.retention.keep', self::DEFAULT_KEEP));
    }
}
