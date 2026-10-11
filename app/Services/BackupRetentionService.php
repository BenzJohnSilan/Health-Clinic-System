<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the newest N successful backups and removes older ones (Phase 3).
 *
 * Safety rules — cleanup can only ever remove a backup archive that
 * BackupService created:
 *
 *  - Files are found ONLY through BackupFileService::resolve(), which accepts
 *    a file only when it belongs to a successful backup_logs record, has the
 *    exact clinic_backup_YYYY-MM-DD_HH-mm-ss[_n].zip name, is a real file
 *    (not a symlink) and sits directly inside the private backup folder.
 *    Nothing else in that folder, and nothing outside it, is ever touched.
 *  - Only SUCCESSFUL backups count and can be removed. Failed and running
 *    records are never deleted.
 *  - The "newest N" are counted among backups whose file actually exists, so
 *    a record with a missing file can never cause a real backup to be
 *    deleted in its place. Records whose file is missing are left alone.
 *  - N is never lower than 1 (see BackupSettings::retentionKeep()).
 *  - It skips the run while a backup is being created, by taking the same
 *    .backup.lock file BackupService uses (no second locking system).
 *
 * The caller (the clinic:backup command) only runs this after a backup
 * SUCCEEDED, so a failed backup can never trigger a cleanup.
 */
class BackupRetentionService
{
    public function __construct(private BackupFileService $files, private RestoreGuard $guard)
    {
    }

    /**
     * @return array{kept:int, deleted:int, freed:int, missing:int, skipped:?string}
     *         kept    = archives left in place
     *         deleted = old archives (and their records) removed
     *         freed   = bytes released
     *         missing = successful records whose file is not on disk (left untouched)
     *         skipped = reason nothing was done, or null
     */
    public function apply(?int $keep = null): array
    {
        $keep   = $keep === null ? BackupSettings::retentionKeep() : max(1, $keep);
        $result = ['kept' => 0, 'deleted' => 0, 'freed' => 0, 'missing' => 0, 'skipped' => null];

        $dir = $this->files->directory();

        if ($dir === null) {
            $result['skipped'] = 'the backup folder does not exist yet.';

            return $result;
        }

        if ($this->guard->isRestoreRunning()) {
            $result['skipped'] = 'a restore is running right now.';

            return $result;
        }

        $lock = $this->acquireLock($dir);

        if ($lock === null) {
            $result['skipped'] = 'a backup is running right now. Old backups will be cleaned after the next one.';

            return $result;
        }

        try {
            // Newest first: latest completion time, then latest id.
            // Only manual + automatic backups are ever cleaned up. A "pre-restore"
            // safety backup is the way back from a restore, so it is never
            // counted or deleted here; "restore" rows are records, not archives.
            $logs = BackupLog::where('status', BackupLog::STATUS_SUCCESS)
                ->whereIn('type', [BackupLog::TYPE_MANUAL, BackupLog::TYPE_AUTOMATIC])
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->get();

            $available = [];

            foreach ($logs as $log) {
                $path = $this->files->resolve($log);

                if ($path === null) {
                    $result['missing']++;

                    continue;
                }

                $available[] = [$log, $path];
            }

            $result['kept'] = min($keep, count($available));

            foreach (array_slice($available, $keep) as [$log, $path]) {
                $size = (int) @filesize($path);

                // If the file cannot be removed and is still there, keep the
                // record so the next run can try again.
                if (!@unlink($path) && file_exists($path)) {
                    Log::warning('Backup retention could not remove an old backup file.', [
                        'backup_log_id' => $log->id,
                        'filename'      => $log->filename,
                    ]);
                    $result['kept']++;

                    continue;
                }

                $log->delete();

                $result['deleted']++;
                $result['freed'] += $size;

                Log::info('Backup retention removed an old backup.', [
                    'backup_log_id' => $log->id,
                    'filename'      => $log->filename,
                    'keep'          => $keep,
                ]);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $result;
    }

    /**
     * Take the lock BackupService uses, without waiting. Returns null when a
     * backup is running (or the lock file cannot be opened) — then cleanup is
     * simply skipped this time, which is always the safe choice.
     *
     * @return resource|null
     */
    private function acquireLock(string $dir)
    {
        $handle = \App\Support\BackupFilesystem::openLock($dir . DIRECTORY_SEPARATOR . '.backup.lock');

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
