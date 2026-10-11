<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off, safe, repeatable move of Valid IDs from the old PUBLIC location
 * (storage/app/public/valid_ids) to the PRIVATE disk (storage/app/private/valid_ids).
 *
 * For every file it: copies to private -> verifies the copy (size + SHA-256)
 * -> only then removes the public original. Nothing is deleted if the copy
 * cannot be verified, the database is not touched (the stored path
 * "valid_ids/<name>" is the same on both disks), and files already present
 * on the private disk are never overwritten.
 */
class SecureValidIds extends Command
{
    protected $signature = 'clinic:secure-valid-ids
                            {--dry-run : Only list what would be moved}
                            {--purge-temp : Also delete leftover public temp_uploads (unfinished registrations from the old flow)}';

    protected $description = 'Move existing Valid ID files from the public disk to the private disk (copy, verify, then remove the public copy)';

    public function handle(): int
    {
        $public  = Storage::disk('public');
        $private = Storage::disk('private');
        $dry     = (bool) $this->option('dry-run');

        $moved = $skipped = $failed = 0;

        foreach ($public->files('valid_ids') as $path) {
            if (str_starts_with(basename($path), '.')) {
                continue;
            }

            if ($dry) {
                $this->line("would move: {$path}");
                $moved++;
                continue;
            }

            if ($private->exists($path)) {
                // Never overwrite. Remove the public duplicate only if identical.
                if ($this->same($public, $private, $path)) {
                    $public->delete($path);
                    $moved++;
                } else {
                    $this->warn("kept (different file already on private disk): {$path}");
                    $skipped++;
                }
                continue;
            }

            try {
                $stream = $public->readStream($path);
                $private->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (!$this->same($public, $private, $path)) {
                    $private->delete($path);
                    throw new \RuntimeException('verification failed');
                }

                $public->delete($path);
                $moved++;
            } catch (\Throwable $e) {
                $this->error("FAILED (public copy left in place): {$path}");
                $failed++;
            }
        }

        $temp = $public->files('temp_uploads');
        $temp = array_values(array_filter($temp, fn ($f) => !str_starts_with(basename($f), '.')));

        if ($temp !== []) {
            if ($this->option('purge-temp') && !$dry) {
                foreach ($temp as $f) {
                    $public->delete($f);
                }
                $this->info(count($temp) . ' leftover temp upload(s) deleted.');
            } else {
                $this->warn(count($temp) . ' leftover file(s) in public temp_uploads (unfinished registrations from the old flow). Re-run with --purge-temp to delete them.');
            }
        }

        $this->info(($dry ? '[dry run] ' : '') . "moved: {$moved}, skipped: {$skipped}, failed: {$failed}");

        if (!$dry && $moved > 0) {
            $this->warn('Reminder: the backup module only archives the public disk, so these files are no longer in automatic backups. See the security report.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function same($a, $b, string $path): bool
    {
        return $a->size($path) === $b->size($path)
            && hash('sha256', (string) $a->get($path)) === hash('sha256', (string) $b->get($path));
    }
}
