<?php

namespace App\Services;

use App\Models\BackupLog;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Read-side helper for backups that BackupService has already created.
 *
 * It answers "where is this backup's file, and is it safe to serve?" so the
 * Admin page never builds a filesystem path from anything the browser sent.
 * Paths are derived ONLY from a backup_logs record, and only when:
 *
 *  - the record is a successful backup,
 *  - its filename is exactly what BackupService generates
 *    (clinic_backup_YYYY-MM-DD_HH-mm-ss[_n].zip — no slashes, dots or "..")
 *  - the real (symlink-resolved) file sits directly inside the protected
 *    backup folder (storage/app/private/backups).
 *
 * This class never writes, moves or deletes anything.
 */
class BackupFileService
{
    /** Same naming convention BackupService::uniqueFilename() produces. */
    private const FILENAME_PATTERN = '/^clinic_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}(?:_\d+)?\.zip$/D';

    /** A manifest is a few hundred bytes; refuse to read anything absurd. */
    private const MAX_MANIFEST_BYTES = 65536;

    public function isValidFilename(?string $filename): bool
    {
        return is_string($filename)
            && basename($filename) === $filename
            && preg_match(self::FILENAME_PATTERN, $filename) === 1;
    }

    /**
     * Real path of the protected backup folder, or null if it does not exist
     * (yet) or is not on a local disk.
     */
    public function directory(): ?string
    {
        $disk = config('backup.disk', 'local');

        if (config("filesystems.disks.$disk.driver") !== 'local') {
            return null;
        }

        $dir  = Storage::disk($disk)->path(trim(config('backup.directory', 'backups'), '/\\'));
        $real = realpath($dir);

        return $real === false ? null : $real;
    }

    /**
     * Absolute path of the archive for a backup record, or null when the
     * record is not downloadable (not successful, unsafe filename, file
     * missing/unreadable, or resolves outside the backup folder).
     */
    public function resolve(BackupLog $log): ?string
    {
        if (!$log->isSuccessful() || !$log->isBackup() || !$this->isValidFilename($log->filename)) {
            return null;
        }

        $dir = $this->directory();
        if ($dir === null) {
            return null;
        }

        $candidate = $dir . DIRECTORY_SEPARATOR . $log->filename;

        // A backup is always a real file. A symlink is never served or deleted,
        // even one that points at another file inside this same folder.
        if (is_link($candidate)) {
            return null;
        }

        $path = realpath($candidate);

        if ($path === false || !is_file($path) || !is_readable($path)) {
            return null;
        }

        // realpath() resolves symlinks, so a link pointing elsewhere fails here.
        if (!$this->samePath(dirname($path), $dir)) {
            return null;
        }

        // The real file must be the one the record names — never a different
        // file that merely sits in the same folder.
        if (!$this->samePath(basename($path), $log->filename)) {
            return null;
        }

        return $path;
    }

    public function isAvailable(BackupLog $log): bool
    {
        return $this->resolve($log) !== null;
    }

    /**
     * What is inside a backup, read from the manifest.json that BackupService
     * writes into every archive. Works for backups created before this page
     * existed, and needs no extra database columns.
     *
     * @return array{tables:?int, files:?int}|null null when unreadable
     */
    public function contents(BackupLog $log): ?array
    {
        $path = $this->resolve($log);
        if ($path === null) {
            return null;
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                return null;
            }

            try {
                $stat = $zip->statName('manifest.json');
                if ($stat === false || ($stat['size'] ?? 0) > self::MAX_MANIFEST_BYTES) {
                    return null;
                }

                $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            } finally {
                $zip->close();
            }
        } catch (Throwable $e) {
            return null;
        }

        if (!is_array($manifest)) {
            return null;
        }

        return [
            'tables' => isset($manifest['database']['tables']) ? (int) $manifest['database']['tables'] : null,
            'files'  => isset($manifest['files']['total']) ? (int) $manifest['files']['total'] : null,
        ];
    }

    /**
     * Total bytes of backup archives actually stored on disk.
     */
    public function storageUsed(): int
    {
        $dir = $this->directory();
        if ($dir === null) {
            return 0;
        }

        $total = 0;

        foreach (new \DirectoryIterator($dir) as $item) {
            if ($item->isFile() && !$item->isLink() && $this->isValidFilename($item->getFilename())) {
                $total += $item->getSize();
            }
        }

        return $total;
    }

    /**
     * Human readable bytes, same style as BackupLog::formatted_size.
     */
    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size  = (float) $bytes;
        $i     = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
    }

    private function samePath(string $a, string $b): bool
    {
        return PHP_OS_FAMILY === 'Windows' ? strcasecmp($a, $b) === 0 : $a === $b;
    }
}
