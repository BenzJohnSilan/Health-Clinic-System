<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchiveEntry;
use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin > Archive. Reached only through the admin route group
 * (auth + verified + role:Admin); every action re-checks the role and
 * ArchiveService checks it again.
 */
class ArchiveController extends Controller
{
    public function __construct(private ArchiveService $archive)
    {
    }

    // =========================================================
    //  LIST  –  search + module filter + date sort/range + pagination
    // =========================================================
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type'   => ['nullable', Rule::in(array_keys(ArchiveService::TYPES))],
            'sort'   => ['nullable', Rule::in(['newest', 'oldest'])],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));
        $type   = $data['type'] ?? '';
        $sort   = $data['sort'] ?? 'newest';
        $from   = $data['from'] ?? '';
        $to     = $data['to'] ?? '';

        $entries = ArchiveEntry::query()
            ->with('archivedBy')
            ->when($type !== '', fn ($q) => $q->where('archivable_type', $type))
            ->when($from !== '', fn ($q) => $q->whereDate('archived_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('archived_at', '<=', $to))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('label', 'like', $like)
                      ->orWhere('sub_label', 'like', $like)
                      ->orWhere('archived_by_name', 'like', $like);
                });
            })
            ->orderBy('archived_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(10)
            ->withQueryString();

        // Why a permanent delete would be refused, worked out once per row so
        // the button can be disabled with an explanation.
        $blockers = [];
        foreach ($entries as $entry) {
            $blockers[$entry->id] = $this->archive->deleteBlockers($entry);
        }

        return view('admin.archive.index', [
            'entries'  => $entries,
            'blockers' => $blockers,
            'types'    => ArchiveService::TYPES,
            'search'   => $search,
            'type'     => $type,
            'sort'     => $sort,
            'from'     => $from,
            'to'       => $to,
            'total'    => ArchiveEntry::count(),
        ]);
    }

    // =========================================================
    //  ARCHIVE  –  generic endpoint (type + id are validated)
    // =========================================================
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(ArchiveService::TYPES))],
            'id'   => ['required', 'integer', 'min:1'],
        ]);

        try {
            $entry = $this->archive->archive($data['type'], (int) $data['id'], $request->user());
        } catch (ArchiveException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', '"' . $entry->label . '" was moved to the Archive.');
    }

    // =========================================================
    //  RESTORE
    // =========================================================
    public function restore(Request $request, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $label = $entry->label;

        try {
            $this->archive->restore($entry, $request->user());
        } catch (ArchiveException $e) {
            return redirect()->route('admin.archive.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.archive.index')->with('success', '"' . $label . '" was restored.');
    }

    // =========================================================
    //  PERMANENT DELETE  –  needs the typed word DELETE, and the
    //  service refuses it while protected history still points at it
    // =========================================================
    public function destroy(Request $request, ArchiveEntry $entry): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'confirmation' => ['required', 'string', Rule::in(['DELETE'])],
        ], [
            'confirmation.required' => 'Type DELETE to confirm the permanent deletion.',
            'confirmation.in'       => 'Type DELETE (in capital letters) to confirm the permanent deletion.',
        ]);

        try {
            $message = $this->archive->permanentlyDelete($entry, $request->user());
        } catch (ArchiveException $e) {
            return redirect()->route('admin.archive.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.archive.index')->with('success', $message);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'Admin', 403);
    }
}
