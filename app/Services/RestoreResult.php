<?php

namespace App\Services;

use App\Models\BackupLog;

/**
 * Outcome of BackupRestoreService::restore().
 *
 *  success       the backup was restored AND the restored state verified
 *  log           the "restore" record in backup_logs
 *  safetyBackup  the pre-restore backup made first (null if restore stopped before it)
 *  changed       true once the destructive phase (database / files) had started —
 *                if a restore fails with changed = true the system may be partly restored
 *  message       safe, human-readable reason when it failed
 */
final class RestoreResult
{
    public function __construct(
        public readonly bool $success,
        public readonly BackupLog $log,
        public readonly ?BackupLog $safetyBackup,
        public readonly bool $changed,
        public readonly ?string $message = null,
    ) {
    }
}
