<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per backup attempt (see App\Services\BackupService).
 *
 * The archive itself lives in protected storage (storage/app/private/backups);
 * this table only records what happened.
 */
class BackupLog extends Model
{
    public const TYPE_MANUAL    = 'manual';
    public const TYPE_AUTOMATIC = 'automatic';

    // Phase 4. A "pre-restore" row is a REAL backup archive (the safety backup
    // made just before a restore). A "restore" row is only a RECORD of a
    // restore operation: it names the backup that was restored and has no
    // archive of its own, so it is never downloaded, counted or cleaned up.
    public const TYPE_PRE_RESTORE = 'pre-restore';
    public const TYPE_RESTORE     = 'restore';

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'filename',
        'type',
        'size',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'details',
        'created_by',
    ];

    protected $casts = [
        'size'         => 'integer',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * The row types that are real backup archives.
     */
    public static function backupTypes(): array
    {
        return [self::TYPE_MANUAL, self::TYPE_AUTOMATIC, self::TYPE_PRE_RESTORE];
    }

    public function scopeBackups($query)
    {
        return $query->whereIn('type', self::backupTypes());
    }

    /** A real backup archive (manual, automatic or pre-restore). */
    public function isBackup(): bool
    {
        return in_array($this->type, self::backupTypes(), true);
    }

    /** A record of a restore operation (no archive of its own). */
    public function isRestoreOperation(): bool
    {
        return $this->type === self::TYPE_RESTORE;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * The stored error message, prepared for showing in the browser.
     *
     * error_message is already stripped of the database password when it is
     * saved, but a MySQL error can still name the database user/host and
     * server folders. Those are hidden here; the full detail stays in
     * storage/logs/laravel.log.
     */
    protected function displayError(): Attribute
    {
        return Attribute::get(function () {
            $message = (string) $this->error_message;

            if ($message === '') {
                return null;
            }

            // Application/server folders.
            foreach ([base_path(), storage_path()] as $folder) {
                $message = str_replace([$folder, str_replace('\\', '/', $folder)], '[server path]', $message);
            }

            // Any other absolute path (/usr/bin/..., C:\laragon\...).
            $message = preg_replace('#(?<![\w.])(?:[A-Za-z]:[\\\\/]|/)(?:[^\s\'"`:,;()]+[\\\\/])*[^\s\'"`:,;()]*#', '[server path]', $message);

            // On Windows the text after the replaced folder can start with a backslash
            // ("[server path]\storage/app/..."), which the pass above cannot see as a path:
            // everything that continues the path is part of it.
            $message = preg_replace('#\[server path\](?:[\\\\/][^\s\'"`:,;()]*)+#', '[server path]', $message);

            // A path can be hit by both passes above; show one placeholder.
            $message = preg_replace('/(?:\[server path\])+/', '[server path]', $message);

            // The configured database host / name / user and MySQL's 'user'@'host'.
            $message = \App\Support\BackupFilesystem::hideConnectionDetails($message);

            // IP addresses and the temporary credential file / working folder / lock names.
            $message = \App\Support\BackupFilesystem::hideInternalDetails($message);

            return \Illuminate\Support\Str::limit($message, 500, '...');
        });
    }

    /**
     * Human readable size, e.g. "34.7 MB" (null while unknown).
     */
    protected function formattedSize(): Attribute
    {
        return Attribute::get(function () {
            if ($this->size === null) {
                return null;
            }

            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $size  = (float) $this->size;
            $i     = 0;

            while ($size >= 1024 && $i < count($units) - 1) {
                $size /= 1024;
                $i++;
            }

            return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
        });
    }
}
