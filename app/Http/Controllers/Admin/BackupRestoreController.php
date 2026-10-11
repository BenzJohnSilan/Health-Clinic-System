<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\RestoreException;
use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Models\User;
use App\Models\UserLog;
use App\Services\BackupRestoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin → Backup & Recovery → Restore (Phase 4).
 *
 * Deliberately thin: it checks the Admin is allowed (the route group already
 * requires auth + verified + role:Admin), checks the typed confirmation, and
 * hands everything else to BackupRestoreService. The URL carries a backup
 * record id only — never a filename or path.
 */
class BackupRestoreController extends Controller
{
    private const MODULE = 'Backup & Recovery';

    private const CONFIRM_WORD = 'RESTORE';

    /**
     * The confirmation page: what will be restored, and a warning.
     */
    public function show(BackupLog $backup, BackupRestoreService $restore)
    {
        try {
            $info = $restore->inspect($backup);
        } catch (RestoreException $e) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'This backup cannot be restored. ' . $e->getMessage());
        } catch (Throwable $e) {
            // Anything unexpected: generic message for the browser, detail (scrubbed) for the log.
            Log::warning('Restore page could not inspect a backup.', [
                'backup_log_id' => $backup->id,
                'exception'     => get_class($e),
                'message'       => \App\Support\BackupFilesystem::scrub($e->getMessage()),
            ]);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'This backup cannot be restored. The selected backup is invalid.');
        }

        return view('admin.backups.restore', [
            'backup'      => $backup,
            'info'        => $info,
            'liveTables'  => $restore->liveTableCount(),
            'confirmWord' => self::CONFIRM_WORD,
        ]);
    }

    /**
     * Runs the restore, only after RESTORE has been typed exactly.
     */
    public function run(Request $request, BackupLog $backup, BackupRestoreService $restore)
    {
        if (trim((string) $request->input('confirmation')) !== self::CONFIRM_WORD) {
            return redirect()
                ->route('admin.backups.restore', $backup)
                ->with('error', 'Type RESTORE in capital letters to confirm.');
        }

        // A restore can outlast PHP's default time limit and must not be
        // cut off if the browser tab is closed halfway.
        ignore_user_abort(true);
        @set_time_limit(0);

        $userId = auth()->id();
        $result = $restore->restore($backup, $userId);

        if ($result->success) {
            $this->audit($userId, 'Restored Backup', $backup->filename . ' (safety backup: ' . $result->safetyBackup?->filename . ')');

            return redirect()
                ->route('admin.backups.index')
                ->with('success', 'Backup restored successfully.');
        }

        $this->audit($userId, 'Restore Failed', $backup->filename);

        return redirect()
            ->route('admin.backups.index')
            ->with('error', $this->failureMessage($result->safetyBackup !== null, $result->changed, (string) $result->message));
    }

    /**
     * Never a raw exception: only messages written by us.
     */
    private function failureMessage(bool $hasSafetyBackup, bool $changed, string $reason): string
    {
        if (!$hasSafetyBackup) {
            return 'Backup restore failed. ' . $reason . ' Nothing was changed.';
        }

        $message = 'Backup restore failed. Your pre-restore safety backup has been preserved.';

        return $changed
            ? $message . ' The system may be partly restored: restore the safety backup listed in the history to go back to how it was.'
            : $message . ' Nothing was changed.';
    }

    /**
     * user_logs belongs to the data a restore replaces, so the admin may not
     * exist in it any more: write the entry only if the account is there, and
     * never let a logging problem break the result page.
     */
    private function audit(?int $userId, string $action, string $details): void
    {
        try {
            if ($userId !== null && User::whereKey($userId)->exists()) {
                UserLog::create([
                    'user_id' => $userId,
                    'action'  => $action,
                    'module'  => self::MODULE,
                    'details' => $details,
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
