<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Small filesystem helpers shared by the backup / restore code (Phase 5).
 *
 * Every one of them exists because a file inside the private backup folder
 * could be replaced by a symlink by someone who can write there, and the
 * plain PHP/Laravel call would then follow that link to somewhere else.
 */
final class BackupFilesystem
{
    /**
     * One separator style for a path built from parts. On Windows Laravel's
     * storage_path('app/private') is "...\storage\app/private" (mixed), so a path
     * built from it must be normalised; on Linux/macOS it is returned untouched.
     */
    public static function normalizePath(string $path, string $separator = DIRECTORY_SEPARATOR): string
    {
        return $separator === '\\' ? str_replace('/', '\\', $path) : $path;
    }

    /**
     * Opens (creating if needed) a lock file. If the path is a symlink, the LINK
     * is removed first (never its target), so opening can neither create nor
     * touch a file somewhere else.
     *
     * @return resource|false
     */
    public static function openLock(string $path)
    {
        if (is_link($path)) {
            @unlink($path);
        }

        return @fopen($path, 'c');
    }

    /**
     * Deletes a folder tree. If $path is itself a symlink only the link is
     * removed: Laravel's deleteDirectory() would otherwise empty the folder the
     * link points to. (Links INSIDE the tree are never followed by it.)
     */
    public static function deleteTree(string $path): void
    {
        if (is_link($path)) {
            @unlink($path);

            return;
        }

        if (is_dir($path)) {
            File::deleteDirectory($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Removes direct children of $folder whose NAME matches $pattern (used for
     * leftovers of a crashed run). Symlinks are unlinked, never followed.
     */
    public static function purgeStale(string $folder, string $pattern): int
    {
        if (!is_dir($folder)) {
            return 0;
        }

        $removed = 0;

        foreach (new \DirectoryIterator($folder) as $item) {
            if (!$item->isDot() && preg_match($pattern, $item->getFilename()) === 1) {
                self::deleteTree($item->getPathname());
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * "Never serve this folder" for Apache / LiteSpeed (Hostinger), in case the
     * project is ever deployed with storage/ inside the web root.
     */
    public static function denyWebAccess(string $dir): void
    {
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';

        if (is_link($htaccess)) {
            @unlink($htaccess);
        }

        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, implode("\n", [
                '# ClinicRMS backups are private. Never serve files from this folder.',
                '<IfModule mod_authz_core.c>',
                '    Require all denied',
                '</IfModule>',
                '<IfModule !mod_authz_core.c>',
                '    Order allow,deny',
                '    Deny from all',
                '</IfModule>',
                '',
            ]));
        }
    }

    /**
     * Hides what tells an outsider where the database is: the configured host,
     * database name, user and socket, plus MySQL's 'user'@'host'.
     */
    public static function hideConnectionDetails(string $message): string
    {
        $config = (array) config('database.connections.' . config('database.default'), []);

        foreach (['host', 'database', 'username', 'unix_socket'] as $key) {
            $value = $config[$key] ?? null;

            if (is_string($value) && strlen($value) >= 2) {
                $message = (string) preg_replace('/(?<![\w.-])' . preg_quote($value, '/') . '(?![\w-])/i', '[hidden]', $message);
            }
        }

        return (string) preg_replace("/'[^']*'@'[^']*'/", "'[hidden]'", $message);
    }

    /**
     * For messages shown to the Admin: hides IP addresses and the names of the
     * temporary credential file, working folders and lock files.
     */
    public static function hideInternalDetails(string $message): string
    {
        $patterns = [
            // IPv4, and IPv6 (eight groups, or the compressed "::" form).
            '/(?<![\w.])(?:\d{1,3}\.){3}\d{1,3}(?![\w.])/',
            '/(?<![\w:])(?:[0-9a-f]{1,4}:){7}[0-9a-f]{1,4}(?![\w:])/i',
            '/(?<![\w:])[0-9a-f]{0,4}(?::[0-9a-f]{0,4}){0,6}::[0-9a-f]{0,4}(?::[0-9a-f]{1,4}){0,6}(?![\w:])/i',
            // The temporary database options file, the temp folders and the lock/marker files.
            '/\bclient\.cnf\b/i',
            '/\.tmp-[0-9a-z]+/i',
            '/\brestore-temp\b/i',
            '/\brestore-[0-9a-f]{16}\b/i',
            '/\.(?:backup|restore)\.lock\b/i',
            '/\.restore-in-progress\b/i',
        ];

        return (string) preg_replace($patterns, '[hidden]', $message);
    }

    /**
     * A technical message made safe for the server log: connection details
     * hidden and length limited.
     */
    public static function scrub(string $message, int $limit = 200): string
    {
        $message = self::hideConnectionDetails($message);

        return mb_strlen($message) > $limit ? mb_substr($message, 0, $limit) . '...' : $message;
    }
}
