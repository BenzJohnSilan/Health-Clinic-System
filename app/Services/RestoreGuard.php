<?php

namespace App\Services;

use App\Exceptions\RestoreException;
use App\Support\BackupFilesystem;

/**
 * Locks and the "restore in progress" marker (Phase 4).
 *
 * Two lock files live in the private backup folder, next to the existing
 * .backup.lock that BackupService already uses:
 *
 *   .restore.lock   held for the WHOLE restore -> only one restore at a time
 *   .backup.lock    (existing) taken by the restore only for its destructive
 *                   phase -> no backup can run while data is being replaced
 *
 * Lock order is always restore -> backup, so they cannot deadlock. The
 * safety backup is made through BackupService, which takes and releases
 * .backup.lock itself, BEFORE the restore takes it for the destructive phase.
 *
 * .restore-in-progress is a marker file. While it exists AND .restore.lock is
 * held, BlockDuringRestore answers every web request with 503, so nobody can
 * work with half-replaced data. If the restore process dies, the OS releases
 * the lock and the site un-blocks itself — no stuck maintenance mode.
 */
class RestoreGuard
{
    public const RESTORE_LOCK = '.restore.lock';

    public const BACKUP_LOCK = '.backup.lock';

    public const MARKER = '.restore-in-progress';

    /**
     * Backup folder path without touching realpath()/Storage: this runs on
     * every web request, so it stays cheap.
     */
    public function directory(): string
    {
        $disk = config('backup.disk', 'local');
        $root = rtrim((string) config("filesystems.disks.$disk.root"), '/\\');

        return BackupFilesystem::normalizePath($root . DIRECTORY_SEPARATOR . trim((string) config('backup.directory', 'backups'), '/\\'));
    }

    /**
     * True only while a live restore process holds the restore lock.
     */
    public function isRestoreRunning(): bool
    {
        $dir = $this->directory();

        if (!is_file($dir . DIRECTORY_SEPARATOR . self::MARKER)) {
            return false;
        }

        $handle = BackupFilesystem::openLock($dir . DIRECTORY_SEPARATOR . self::RESTORE_LOCK);

        if ($handle === false) {
            return true; // cannot tell -> stay safe and keep blocking
        }

        if (!flock($handle, LOCK_SH | LOCK_NB)) {
            fclose($handle);

            return true; // a restore holds the exclusive lock
        }

        flock($handle, LOCK_UN);
        fclose($handle);

        return false; // marker left behind by a crashed restore: nothing is running
    }

    /**
     * @return resource|null null when another restore is running
     */
    public function acquireRestoreLock()
    {
        return $this->lock(self::RESTORE_LOCK);
    }

    /**
     * @return resource|null null when a backup is running
     */
    public function acquireBackupLock()
    {
        return $this->lock(self::BACKUP_LOCK);
    }

    public function markStarted(): void
    {
        $marker = $this->directory() . DIRECTORY_SEPARATOR . self::MARKER;

        // Remove whatever is there (a stale marker, or a symlink someone planted: only the
        // link goes, never its target) and create the file fresh with "x", which refuses
        // to write through a link.
        @unlink($marker);
        $handle = @fopen($marker, 'xb');

        if ($handle === false) {
            throw new RestoreException('The restore could not be started safely, so nothing was restored.');
        }

        fwrite($handle, json_encode(['pid' => getmypid(), 'started_at' => date('c')]));
        fclose($handle);
    }

    public function clearMarker(): void
    {
        @unlink($this->directory() . DIRECTORY_SEPARATOR . self::MARKER);
    }

    /**
     * @param resource|null $handle
     */
    public function release($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return resource|null
     */
    private function lock(string $file)
    {
        $dir = $this->directory();

        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return null;
        }

        $handle = BackupFilesystem::openLock($dir . DIRECTORY_SEPARATOR . $file);

        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }
}
