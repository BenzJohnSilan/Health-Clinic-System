<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use App\Services\BackupFileService;
use App\Services\BackupRetentionService;
use App\Services\BackupService;
use App\Services\BackupSettings;
use Illuminate\Console\Command;
use Throwable;

class CreateClinicBackup extends Command
{
    /**
     * --type is for the future scheduler (automatic); a person running the
     * command by hand uses the default "manual".
     */
    protected $signature = 'clinic:backup
                            {--type=manual : Backup type: manual or automatic}';

    protected $description = 'Create a protected ClinicRMS backup (database + uploaded files) and record it in backup_logs';

    public function handle(BackupService $backups, BackupRetentionService $retention, BackupFileService $files): int
    {
        $type = strtolower((string) $this->option('type'));

        if (!in_array($type, [BackupLog::TYPE_MANUAL, BackupLog::TYPE_AUTOMATIC], true)) {
            $this->error('Invalid --type. Use "manual" or "automatic".');

            return self::INVALID;
        }

        $this->info('Creating ClinicRMS backup...');

        try {
            $log = $backups->create($type);
        } catch (Throwable $e) {
            // Only reached when the attempt could not even be recorded
            // (for example, the application database is unreachable).
            // Prefer the underlying driver message over the full SQL text.
            $reason = ($e->getPrevious() ?? $e)->getMessage();
            $this->error('Backup could not be started: ' . strtok($reason, "\n"));

            return self::FAILURE;
        }

        if (!$log->isSuccessful()) {
            $this->error('Backup failed: ' . ($log->error_message ?: 'unknown error'));
            $this->line("See backup log #{$log->id} and storage/logs/laravel.log for details.");

            return self::FAILURE;
        }

        $summary = $backups->summary();

        $this->info('Backup completed successfully.');
        $this->line('  File     : ' . $log->filename);
        $this->line('  Size     : ' . $log->formatted_size);
        $this->line('  Location : ' . ($summary['location'] ?? 'storage/app/private/backups'));
        $this->line('  Database : ' . ($summary['tables'] ?? '?') . ' tables');
        $this->line('  Uploads  : ' . ($summary['files'] ?? 0) . ' files');

        foreach (($summary['directories'] ?? []) as $folder => $count) {
            $this->line("             - {$folder}: {$count}");
        }

        // Only reached after a SUCCESSFUL backup — a failed one returned above,
        // so a failure can never trigger a cleanup.
        $this->applyRetention($retention, $files);

        return self::SUCCESS;
    }

    /**
     * The backup itself is already safe and verified, so a cleanup problem is
     * only reported — it never turns a good backup into a failed command.
     */
    private function applyRetention(BackupRetentionService $retention, BackupFileService $files): void
    {
        try {
            $result = $retention->apply();
        } catch (Throwable $e) {
            report($e);
            $this->warn('Backup created, but old backups could not be cleaned up. See storage/logs/laravel.log.');

            return;
        }

        if ($result['skipped'] !== null) {
            $this->warn('  Retention: skipped, ' . $result['skipped']);

            return;
        }

        $this->line('  Retention: keeping the latest ' . BackupSettings::retentionKeep() . ' successful backups');
        $this->line($result['deleted'] > 0
            ? "             removed {$result['deleted']} older backup(s), freed " . $files->formatBytes($result['freed'])
            : '             nothing to remove');
    }
}
