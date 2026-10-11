@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-backups.css') }}">
@endsection

@section('content')

<div class="container">

    {{-- ================= HEADER ================= --}}
    <div class="page-header">
        <div>
            <h1>Backup &amp; Recovery</h1>
            <p>Keep a safe copy of the clinic database and its uploaded files.</p>
        </div>

        @if($hasAny)
            <button type="button" class="btn-add js-open-backup">
                <i class="fa-solid fa-database"></i> Backup Now
            </button>
        @endif
    </div>

    {{-- ================= FLASH MESSAGES ================= --}}
    @if(session('success'))
        <div class="alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ================= SUMMARY CARDS ================= --}}
    <div class="bk-stats-grid">

        <div class="bk-stat-card">
            <div class="bk-stat-icon"><i class="fa-solid fa-box-archive"></i></div>
            <div class="bk-stat-body">
                <p>Total Backups</p>
                <h3>{{ number_format($stats['total']) }}</h3>
            </div>
        </div>

        <div class="bk-stat-card">
            <div class="bk-stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="bk-stat-body">
                <p>Last Backup</p>
                @if($stats['last_at'])
                    <h3>{{ $stats['last_at']->format('M d, Y') }}</h3>
                    <small>{{ $stats['last_at']->format('h:i A') }} ({{ $stats['last_at']->diffForHumans() }})</small>
                @else
                    <h3 class="is-empty">No backup yet</h3>
                @endif
            </div>
        </div>

        <div class="bk-stat-card">
            <div class="bk-stat-icon"><i class="fa-solid fa-file-zipper"></i></div>
            <div class="bk-stat-body">
                <p>Last Backup Size</p>
                @if($stats['last_size'])
                    <h3>{{ $stats['last_size'] }}</h3>
                @else
                    <h3 class="is-empty">&mdash;</h3>
                @endif
            </div>
        </div>

        <div class="bk-stat-card">
            <div class="bk-stat-icon"><i class="fa-solid fa-hard-drive"></i></div>
            <div class="bk-stat-body">
                <p>Storage Used</p>
                <h3>{{ $stats['storage_used'] }}</h3>
            </div>
        </div>

    </div>

    <div class="bk-note">
        <i class="fa-solid fa-shield-halved"></i>
        <span>Backups hold patient records and uploaded IDs. They are kept in private server storage and only an Admin can download them. Keep any downloaded copy somewhere safe and never share it.</span>
    </div>

    {{-- ================= AUTOMATIC BACKUP + RETENTION ================= --}}
    <div class="bk-note" id="autoBackupNote">
        <i class="fa-solid fa-calendar-check"></i>
        <span>
            @if($schedule['enabled'])
                Automatic backup runs every day at {{ $schedule['time'] }}.
                @if($schedule['keep'] === 1)
                    Only the latest successful backup is kept; older ones are deleted.
                @else
                    Only the latest {{ $schedule['keep'] }} successful backups are kept; older ones are deleted.
                @endif
                @if($schedule['last'])
                    Last automatic run: {{ $schedule['last']->started_at->format('M d, Y h:i A') }}
                    ({{ ucfirst($schedule['last']->status) }}).
                @else
                    It has not run yet.
                @endif
            @else
                Automatic backup is turned off. Backups are only created when you use Backup Now.
            @endif
        </span>
    </div>

    @if(!$hasAny)

        {{-- ================= EMPTY STATE ================= --}}
        <div class="bk-empty">
            <div class="bk-empty-icon"><i class="fa-solid fa-database"></i></div>
            <h2>No backups yet.</h2>
            <p>Create your first backup to protect your ClinicRMS data.</p>
            <button type="button" class="btn-add js-open-backup">
                <i class="fa-solid fa-database"></i> Backup Now
            </button>
        </div>

    @else

        {{-- ================= HISTORY TABLE ================= --}}
        <div class="table-wrapper">

            <table class="medical-table">

                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Type</th>
                        <th>Contents</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($backups as $backup)
                        @php
                            $info      = $details[$backup->id] ?? null;
                            $contents  = $info['contents'] ?? null;
                            $available = $info['available'] ?? false;
                        @endphp
                        <tr>

                            <td class="bk-datetime">
                                {{ $backup->started_at->format('M d, Y') }}<br>{{ $backup->started_at->format('h:i A') }}
                            </td>

                            <td>
                                @php
                                    $typeStyle = match($backup->type) {
                                        'automatic'   => ['bk-badge--automatic', 'Automatic'],
                                        'pre-restore' => ['bk-badge--prerestore', 'Pre-restore'],
                                        'restore'     => ['bk-badge--restore', 'Restore'],
                                        default       => ['bk-badge--manual', 'Manual'],
                                    };
                                @endphp
                                <span class="bk-badge {{ $typeStyle[0] }}">{{ $typeStyle[1] }}</span>
                                @if($backup->creator)
                                    <span class="bk-sub">by {{ $backup->creator->full_name }}</span>
                                @endif
                                @if($backup->details)
                                    <span class="bk-sub">{{ $backup->details }}</span>
                                @endif
                            </td>

                            <td>
                                @if($backup->isRestoreOperation())
                                    <span class="bk-contents-title">Restored backup</span>
                                    <span class="bk-sub">{{ $backup->filename }}</span>
                                @elseif($backup->isSuccessful())
                                    <span class="bk-contents-title">Database + Files</span>
                                    @if($contents && $contents['tables'] !== null && $contents['files'] !== null)
                                        <span class="bk-sub">{{ $contents['tables'] }} tables, {{ number_format($contents['files']) }} uploaded files</span>
                                    @endif
                                @else
                                    <span class="bk-muted">&mdash;</span>
                                @endif
                            </td>

                            <td class="bk-size">
                                @if($backup->isSuccessful() && $backup->formatted_size)
                                    {{ $backup->formatted_size }}
                                @else
                                    <span class="bk-muted">&mdash;</span>
                                @endif
                            </td>

                            <td>
                                @if($backup->status === 'success')
                                    <span class="bk-status bk-status--success"><i class="fa-solid fa-circle-check"></i> Success</span>
                                @elseif($backup->status === 'failed')
                                    <span class="bk-status bk-status--failed"><i class="fa-solid fa-circle-xmark"></i> Failed</span>
                                @else
                                    <span class="bk-status bk-status--running"><i class="fa-solid fa-spinner"></i> Running</span>
                                @endif
                            </td>

                            <td>
                                @if($backup->isSuccessful() && $backup->isBackup())
                                    @if($available)
                                        <a class="bk-btn bk-btn--download" href="{{ route('admin.backups.download', $backup) }}">
                                            <i class="fa-solid fa-download"></i> Download
                                        </a>
                                        <a class="bk-btn bk-btn--restore" href="{{ route('admin.backups.restore', $backup) }}">
                                            <i class="fa-solid fa-clock-rotate-left"></i> Restore
                                        </a>
                                    @else
                                        <span class="bk-muted" title="The archive is no longer in the backup folder.">File missing</span>
                                    @endif
                                @elseif($backup->status === 'failed')
                                    <button type="button"
                                            class="bk-btn bk-btn--ghost js-view-error"
                                            data-when="{{ $backup->started_at->format('M d, Y h:i A') }}"
                                            data-message="{{ $backup->display_error }}">
                                        <i class="fa-solid fa-circle-info"></i> View details
                                    </button>
                                @elseif($backup->isSuccessful())
                                    <span class="bk-muted">Completed</span>
                                @else
                                    <span class="bk-muted">In progress</span>
                                @endif
                            </td>

                        </tr>
                    @endforeach
                </tbody>

            </table>

        </div>

        {{-- ================= PAGINATION ================= --}}
        <div class="pagination-wrapper">

            <div class="pagination-info">
                Showing <strong>{{ $backups->firstItem() }}–{{ $backups->lastItem() }}</strong>
                of <strong>{{ $backups->total() }}</strong> backup{{ $backups->total() !== 1 ? 's' : '' }}
            </div>

            <nav class="pagination-nav" aria-label="Pagination">

                @if($backups->onFirstPage())
                    <span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>
                @else
                    <a class="page-btn" href="{{ $backups->previousPageUrl() }}" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                @endif

                @php
                    $currentPage = $backups->currentPage();
                    $lastPage    = $backups->lastPage();

                    $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                        return $p === 1 || $p === $lastPage || abs($p - $currentPage) <= 2;
                    })->values();

                    $prev = null;
                @endphp

                @foreach($pages as $page)
                    @if($prev !== null && $page - $prev > 1)
                        <span class="page-ellipsis">…</span>
                    @endif

                    @if($page === $currentPage)
                        <span class="page-btn active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page-btn" href="{{ $backups->url($page) }}">{{ $page }}</a>
                    @endif

                    @php $prev = $page; @endphp
                @endforeach

                @if($backups->hasMorePages())
                    <a class="page-btn" href="{{ $backups->nextPageUrl() }}" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                @else
                    <span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>
                @endif

            </nav>

        </div>

    @endif

</div>

{{-- ================= BACKUP NOW CONFIRMATION ================= --}}
<div class="modal-overlay" id="backupModal" role="dialog" aria-modal="true" aria-labelledby="backupModalTitle">
    <div class="modal-box">
        <h3 class="modal-title" id="backupModalTitle">Create a new backup?</h3>

        <p class="modal-text">
            This makes a new backup of the ClinicRMS database and the important uploaded files:
        </p>
        <ul class="modal-list">
            <li>All clinic records in the database</li>
            <li>Profile photos and doctor signatures</li>
            <li>Valid ID images and laboratory result files</li>
        </ul>
        <p class="modal-text">
            Your live data is not changed. It can take a minute, so keep this page open until it finishes.
        </p>

        <form method="POST" action="{{ route('admin.backups.store') }}" id="backupForm">
            @csrf
            <div class="modal-actions">
                <button type="button" class="btn-back" id="backupCancel">Cancel</button>
                <button type="submit" class="btn-confirm" id="backupConfirm">Create backup</button>
            </div>
        </form>
    </div>
</div>

{{-- ================= FAILED BACKUP DETAILS ================= --}}
<div class="modal-overlay" id="errorModal" role="dialog" aria-modal="true" aria-labelledby="errorModalTitle">
    <div class="modal-box">
        <h3 class="modal-title" id="errorModalTitle">Backup failed</h3>
        <p class="bk-error-when" id="errorWhen"></p>
        <div class="bk-error-box" id="errorMessage"></div>
        <p class="modal-text" style="margin-top:12px;">
            The full technical details are in the server log (storage/logs/laravel.log).
        </p>
        <div class="modal-actions">
            <button type="button" class="btn-back" id="errorClose">Close</button>
        </div>
    </div>
</div>

<script>
(function () {
    var backupModal = document.getElementById('backupModal');
    var errorModal  = document.getElementById('errorModal');
    var form        = document.getElementById('backupForm');
    var confirmBtn  = document.getElementById('backupConfirm');
    var cancelBtn   = document.getElementById('backupCancel');
    var busy        = false;

    function open(modal, focusEl) {
        modal.classList.add('is-open');
        if (focusEl) focusEl.focus();
    }

    function close(modal) {
        if (busy) return;               // never dismiss while a backup is running
        modal.classList.remove('is-open');
    }

    function setIdle() {
        busy = false;
        confirmBtn.disabled = false;
        cancelBtn.disabled  = false;
        confirmBtn.textContent = 'Create backup';
        document.querySelectorAll('.js-open-backup').forEach(function (b) { b.disabled = false; });
        backupModal.removeAttribute('aria-busy');
    }

    // Backup Now (header button and empty-state button)
    document.querySelectorAll('.js-open-backup').forEach(function (btn) {
        btn.addEventListener('click', function () { open(backupModal, cancelBtn); });
    });

    cancelBtn.addEventListener('click', function () { close(backupModal); });

    // Confirm: submit once, then show progress and lock the dialog.
    form.addEventListener('submit', function (e) {
        if (busy) { e.preventDefault(); return; }
        busy = true;
        confirmBtn.disabled = true;
        cancelBtn.disabled  = true;
        confirmBtn.innerHTML = '<span class="bk-spinner" aria-hidden="true"></span> Creating backup…';
        backupModal.setAttribute('aria-busy', 'true');
        document.querySelectorAll('.js-open-backup').forEach(function (b) { b.disabled = true; });
    });

    // Coming back with the browser's Back button must not stay stuck on "Creating…".
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { setIdle(); backupModal.classList.remove('is-open'); }
    });

    // Failed-backup details (text is inserted as plain text, never as HTML)
    document.querySelectorAll('.js-view-error').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('errorWhen').textContent =
                btn.dataset.when ? 'Attempted on ' + btn.dataset.when : '';
            document.getElementById('errorMessage').textContent =
                btn.dataset.message || 'No error details were recorded for this attempt.';
            open(errorModal, document.getElementById('errorClose'));
        });
    });

    document.getElementById('errorClose').addEventListener('click', function () { close(errorModal); });

    // Click outside / Escape close a dialog (unless a backup is running)
    [backupModal, errorModal].forEach(function (modal) {
        modal.addEventListener('click', function (e) { if (e.target === modal) close(modal); });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { close(backupModal); close(errorModal); }
    });
})();
</script>

@endsection
