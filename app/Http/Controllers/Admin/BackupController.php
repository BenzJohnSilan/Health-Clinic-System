<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupLog;
use App\Models\UserLog;
use App\Services\BackupFileService;
use App\Services\BackupService;
use App\Services\BackupSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin → Backup & Recovery (Phase 2: overview, history, Backup Now, download).
 *
 * This controller only presents backups and hands work to the services:
 *  - creating a backup is BackupService::create() — the same code path as
 *    `php artisan clinic:backup`; nothing is duplicated here.
 *  - locating a backup file safely is BackupFileService.
 *
 * Access is Admin-only: these routes live in the Admin route group
 * (auth + verified + role:Admin). There is deliberately NO delegable
 * permission for backups — a backup holds the whole database and every
 * uploaded ID, so it is not something Staff/Doctor can be granted.
 *
 * Restore, scheduling and retention are later phases and do not exist yet.
 */
class BackupController extends Controller
{
    private const PER_PAGE = 10;

    private const MODULE = 'Backup & Recovery';

    public function index(Request $request, BackupFileService $files)
    {
        // "Restore" rows are records of an operation, not archives: leave them out.
        $latest = BackupLog::backups()->where('status', BackupLog::STATUS_SUCCESS)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        $stats = [
            'total'        => BackupLog::backups()->where('status', BackupLog::STATUS_SUCCESS)->count(),
            'last_at'      => $latest?->completed_at ?? $latest?->started_at,
            'last_size'    => $latest?->formatted_size,
            'storage_used' => $files->formatBytes($files->storageUsed()),
        ];

        // Search & filter (all optional, combined with AND).
        $search = trim((string) $request->query('search'));
        $type   = $request->query('type');
        $status = $request->query('status');

        // Only known values are accepted — anything else is ignored.
        if (!in_array($type, ['manual', 'automatic', 'pre-restore', 'restore'], true)) {
            $type = '';
        }
        if (!in_array($status, [BackupLog::STATUS_SUCCESS, BackupLog::STATUS_FAILED, BackupLog::STATUS_RUNNING], true)) {
            $status = '';
        }

        $backups = BackupLog::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('filename', 'like', '%' . $search . '%');
            })
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Per-row facts for this page only: is the file still on disk, and
        // what does it contain (from its manifest).
        $details = [];
        foreach ($backups as $backup) {
            if ($backup->isSuccessful() && $backup->isBackup()) {
                $details[$backup->id] = [
                    'available' => $files->isAvailable($backup),
                    'contents'  => $files->contents($backup),
                ];
            }
        }

        // Phase 3: what the automatic backup is set to do, and how it last went.
        $schedule = [
            'enabled' => BackupSettings::automaticEnabled(),
            'time'    => BackupSettings::automaticTimeLabel(),
            'keep'    => BackupSettings::retentionKeep(),
            'last'    => BackupLog::where('type', BackupLog::TYPE_AUTOMATIC)
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first(),
        ];

        return view('admin.backups.index', [
            'backups'  => $backups,
            'stats'    => $stats,
            'details'  => $details,
            // Whether ANY backup exists at all (ignores the filters), so a
            // search with no match shows "no results", not the first-backup screen.
            'hasAny'   => BackupLog::exists(),
            'search'   => $search,
            'type'     => $type,
            'status'   => $status,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Backup Now — always a "manual" backup created by the signed-in Admin.
     */
    public function store(BackupService $backups)
    {
        // A backup of a large clinic can outlast PHP's default time limit, and
        // must not be cut off if the browser tab is closed halfway.
        ignore_user_abort(true);
        @set_time_limit(0);

        try {
            $log = $backups->create(BackupLog::TYPE_MANUAL, auth()->id());
        } catch (Throwable $e) {
            // Only when the attempt could not even be recorded. The detail
            // goes to the server log, never to the browser.
            report($e);

            return $this->failed();
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => $log->isSuccessful() ? 'Created Backup' : 'Backup Failed',
            'module'  => self::MODULE,
            'details' => $log->isSuccessful()
                ? $log->filename . ' (' . $log->formatted_size . ')'
                : 'Manual backup did not complete (backup log #' . $log->id . ')',
        ]);

        if (!$log->isSuccessful()) {
            return $this->failed();
        }

        return redirect()
            ->route('admin.backups.index')
            ->with('success', 'Backup completed successfully.');
    }

    /**
     * Download — the file is located from the backup_logs record only. The
     * browser sends a record id, never a filename or path.
     */
    public function download(BackupLog $backup, BackupFileService $files)
    {
        if (!$backup->isSuccessful() || !$backup->isBackup()) {
            abort(404);
        }

        if (!$files->isValidFilename($backup->filename)) {
            Log::warning('Blocked backup download: record has an unexpected filename.', [
                'backup_log_id' => $backup->id,
                'user_id'       => auth()->id(),
            ]);

            abort(404);
        }

        $path = $files->resolve($backup);

        if ($path === null) {
            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'This backup file is no longer available on the server.');
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Downloaded Backup',
            'module'  => self::MODULE,
            'details' => $backup->filename,
        ]);

        // The route opts out of prevent-back-history (that middleware calls
        // ->header(), which streamed file responses do not have), so the same
        // no-cache rules are applied here.
        $response = response()->download($path, $backup->filename, [
            'Content-Type'           => 'application/zip',
            'Cache-Control'          => 'no-cache, no-store, max-age=0, must-revalidate, private',
            'Pragma'                 => 'no-cache',
            'Expires'                => 'Sat, 01 Jan 1990 00:00:00 GMT',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // A streamed file response marks itself "public" by default, which would override the
        // "private" above. A backup holds patient records: it must never be cache-shareable.
        $response->setPrivate();

        return $response;
    }

    private function failed()
    {
        return redirect()
            ->route('admin.backups.index')
            ->with('error', 'Backup failed. Please check the backup logs for details.');
    }
}