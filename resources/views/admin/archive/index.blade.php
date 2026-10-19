@extends('layouts.admin')

@section('head')
{{-- Same stylesheet as Manage Users / Contact Messages so the filter bar, table and pagination match. --}}
<link rel="stylesheet" href="{{ asset('css/admin-users.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-archive.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <div>
            <h1>Archive</h1>
        </div>
    </div>

    <!-- ================= SEARCH & FILTER ================= -->
    <form method="GET" action="{{ route('admin.archive.index') }}" class="filter-bar arc-filter">
        <input
            type="text"
            name="search"
            class="filter-input"
            placeholder="Search name, reference number or who archived it..."
            maxlength="100"
            value="{{ $search }}">

        <select name="type" class="filter-input" aria-label="Filter by module">
            <option value="">All Modules</option>
            @foreach($types as $key => $label)
                <option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select name="sort" class="filter-input" aria-label="Sort by date archived">
            <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest archived first</option>
            <option value="oldest" {{ $sort === 'oldest' ? 'selected' : '' }}>Oldest archived first</option>
        </select>

        <label class="arc-date">
            <span>From</span>
            <input type="date" name="from" class="filter-input" value="{{ $from }}">
        </label>
        <label class="arc-date">
            <span>To</span>
            <input type="date" name="to" class="filter-input" value="{{ $to }}">
        </label>

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        @if($search !== '' || $type !== '' || $from !== '' || $to !== '' || $sort !== 'newest')
            <a href="{{ route('admin.archive.index') }}" class="btn-clear-filter">Clear</a>
        @endif
    </form>

    <!-- ================= MESSAGES ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <!-- ================= TABLE ================= -->
    <div class="table-container">
    <table class="users-table arc-table {{ $entries->total() === 0 ? 'arc-table--empty' : '' }}">
        <thead>
            <tr>
                <th>Record</th>
                <th>Module</th>
                <th>Date Archived</th>
                <th>Archived By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($entries as $entry)
            @php
                $blocked = $blockers[$entry->id] ?? [];
                $typeLabel = $types[$entry->archivable_type] ?? 'Unknown';
            @endphp
            <tr>
                <td data-label="Record">
                    <span class="arc-name">{{ $entry->label }}</span>
                    @if($entry->sub_label)
                        <span class="arc-sub">{{ $entry->sub_label }}</span>
                    @endif
                </td>
                <td data-label="Module">
                    <span class="badge arc-type arc-type--{{ str_replace('_', '-', $entry->archivable_type) }}">{{ $typeLabel }}</span>
                </td>
                <td data-label="Date Archived" class="arc-date-cell">{{ $entry->archived_at?->format('M d, Y g:i A') }}</td>
                <td data-label="Archived By">{{ $entry->archivedByName() }}</td>
                <td data-label="Actions">
                    <div class="arc-actions">
                        <form method="POST" action="{{ route('admin.archive.restore', $entry) }}">
                            @csrf
                            <button type="submit" class="arc-btn arc-btn--restore">
                                <i class="fa-solid fa-rotate-left"></i> Restore
                            </button>
                        </form>

                        @if(count($blocked) === 0)
                            <button type="button"
                                    class="arc-btn arc-btn--delete js-arc-delete"
                                    data-action="{{ route('admin.archive.destroy', $entry) }}"
                                    data-label="{{ $entry->label }}"
                                    data-type="{{ $typeLabel }}">
                                <i class="fa-solid fa-trash"></i> Permanent Delete
                            </button>
                        @else
                            {{-- The reason shows only on hover. The span carries the tooltip because
                                 browsers do not reliably show titles on disabled buttons. --}}
                            <span class="arc-lock" title="Cannot be permanently deleted. Still linked to: {{ implode(', ', $blocked) }}.">
                                <button type="button" class="arc-btn arc-btn--locked" disabled>
                                    <i class="fa-solid fa-lock"></i> Protected
                                </button>
                            </span>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            @php $filtered = $search !== '' || $type !== '' || $from !== '' || $to !== ''; @endphp
            <tr class="arc-empty-row">
                <td colspan="5">
                    <div class="arc-empty">
                        <span class="arc-empty__icon">
                            <i class="fa-solid {{ $filtered ? 'fa-magnifying-glass' : 'fa-box-archive' }}"></i>
                        </span>
                        @if($filtered)
                            <h3>No archived records match your search or filters</h3>
                            <p>Try a different keyword, module or date range.</p>
                            <a href="{{ route('admin.archive.index') }}" class="arc-btn arc-btn--restore">Clear filters</a>
                        @else
                            <h3>Nothing archived yet</h3>
                            <p>Archived users, appointments, services, messages and website content will appear here. You can restore them any time.</p>
                        @endif
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div><!-- /table-container -->

    <!-- ================= PAGINATION ================= -->
    @if($entries->total() > 0)
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($entries->total() > 0)
                Showing <strong>{{ $entries->firstItem() }}–{{ $entries->lastItem() }}</strong>
                of <strong>{{ $entries->total() }}</strong> result{{ $entries->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">
            @if($entries->onFirstPage())
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>
            @else
                <a class="page-btn" href="{{ $entries->previousPageUrl() }}"><i class="fa-solid fa-chevron-left"></i></a>
            @endif

            @php
                $currentPage = $entries->currentPage();
                $lastPage    = $entries->lastPage();
                $pages = collect(range(1, max(1, $lastPage)))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1 || $p === $lastPage || abs($p - $currentPage) <= 2;
                })->values();
                $prev = null;
            @endphp

            @foreach($pages as $page)
                @if($prev !== null && $page - $prev > 1)
                    <span class="page-ellipsis">…</span>
                @endif
                @if($page === $currentPage)
                    <span class="page-btn active">{{ $page }}</span>
                @else
                    <a class="page-btn" href="{{ $entries->url($page) }}">{{ $page }}</a>
                @endif
                @php $prev = $page; @endphp
            @endforeach

            @if($entries->hasMorePages())
                <a class="page-btn" href="{{ $entries->nextPageUrl() }}"><i class="fa-solid fa-chevron-right"></i></a>
            @else
                <span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>
            @endif
        </nav>
    </div>
    @endif

</div>

<!-- ================= PERMANENT DELETE CONFIRMATION ================= -->
<div class="arc-modal" id="arcDeleteModal" role="dialog" aria-modal="true" aria-labelledby="arcDeleteTitle" hidden>
    <div class="arc-modal__box">
        <div class="arc-modal__icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 id="arcDeleteTitle">Permanently delete this record?</h3>
        <p class="arc-modal__record">
            <span id="arcDeleteType"></span>:
            <strong id="arcDeleteLabel"></strong>
        </p>
        <p class="arc-modal__warn">
            This <strong>cannot be undone</strong>. The record will be removed from the database for good.
            If any medical, appointment or billing history is still linked to it, the system will refuse.
        </p>

        <form method="POST" id="arcDeleteForm" action="">
            @csrf
            @method('DELETE')
            <label class="arc-modal__field" for="arcDeleteConfirm">
                Type <strong>DELETE</strong> to confirm
            </label>
            <input type="text" id="arcDeleteConfirm" name="confirmation" class="filter-input"
                   autocomplete="off" spellcheck="false" placeholder="DELETE">

            <div class="arc-modal__actions">
                <button type="button" class="arc-btn arc-btn--cancel js-arc-cancel">Cancel</button>
                <button type="submit" class="arc-btn arc-btn--delete" id="arcDeleteSubmit" disabled>
                    <i class="fa-solid fa-trash"></i> Delete Permanently
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal   = document.getElementById('arcDeleteModal');
    var form    = document.getElementById('arcDeleteForm');
    var input   = document.getElementById('arcDeleteConfirm');
    var submit  = document.getElementById('arcDeleteSubmit');
    var opener  = null;

    function open(btn) {
        opener = btn;
        form.action = btn.dataset.action;
        document.getElementById('arcDeleteLabel').textContent = btn.dataset.label;
        document.getElementById('arcDeleteType').textContent  = btn.dataset.type;
        input.value = '';
        submit.disabled = true;
        modal.hidden = false;
        input.focus();
    }

    function close() {
        modal.hidden = true;
        if (opener) opener.focus();
    }

    document.querySelectorAll('.js-arc-delete').forEach(function (btn) {
        btn.addEventListener('click', function () { open(btn); });
    });

    document.querySelectorAll('.js-arc-cancel').forEach(function (btn) {
        btn.addEventListener('click', close);
    });

    // The button only unlocks when DELETE is typed; the server checks it again.
    input.addEventListener('input', function () {
        submit.disabled = input.value !== 'DELETE';
    });

    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });

    // Avoid double submits on Restore / Delete.
    document.querySelectorAll('form').forEach(function (f) {
        f.addEventListener('submit', function () {
            f.querySelectorAll('button[type="submit"]').forEach(function (b) {
                setTimeout(function () { b.disabled = true; }, 0);
            });
        });
    });
});
</script>

@endsection