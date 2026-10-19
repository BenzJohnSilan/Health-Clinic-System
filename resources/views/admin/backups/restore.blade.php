@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-backups.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-backup-restore.css') }}">
@endsection

@section('content')

@php
    $typeLabel = match($backup->type) {
        'automatic'   => 'Automatic',
        'pre-restore' => 'Pre-restore',
        default       => 'Manual',
    };
@endphp

<div class="rs-page">

    {{-- ================= HERO (same card as the Admin Dashboard) ================= --}}
    <div class="rs-hero">
        <div class="rs-hero-left">
            <div class="rs-hero-badge"><i class='bx bx-history'></i></div>
            <div>
                <h2>Restore backup</h2>
                <p>Check this backup carefully before you continue.</p>
            </div>
        </div>

        <a class="rs-hero-back" href="{{ route('admin.backups.index') }}">
            <i class='bx bx-arrow-back'></i> Back to Backup &amp; Recovery
        </a>
    </div>

    @if(session('error'))
        <div class="rs-alert" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ================= DETAILS + WHAT HAPPENS ================= --}}
    <div class="rs-grid">

        {{-- ---------- BACKUP TO RESTORE ---------- --}}
        <div class="rs-card">
            <div class="rs-card-title">
                <span class="rs-card-icon"><i class='bx bx-archive'></i></span>
                <h3>Backup to restore</h3>
            </div>

            <div class="rs-file">
                <i class='bx bx-file'></i>
                <div>
                    <small>Backup file</small>
                    <code>{{ $backup->filename }}</code>
                </div>
            </div>

            <div class="rs-facts">
                <div class="rs-fact">
                    <span class="rs-fact-icon"><i class='bx bx-calendar'></i></span>
                    <div><p>Date &amp; time</p><h4>{{ $backup->started_at->format('M d, Y h:i A') }}</h4></div>
                </div>

                <div class="rs-fact">
                    <span class="rs-fact-icon"><i class='bx bx-tag'></i></span>
                    <div><p>Type</p><h4>{{ $typeLabel }}</h4></div>
                </div>

                <div class="rs-fact">
                    <span class="rs-fact-icon"><i class='bx bx-hdd'></i></span>
                    <div><p>Size</p><h4>{{ $backup->formatted_size ?? '—' }}</h4></div>
                </div>

                <div class="rs-fact">
                    <span class="rs-fact-icon"><i class='bx bx-data'></i></span>
                    <div><p>Database tables</p><h4>{{ $info['tables'] }}</h4></div>
                </div>

                <div class="rs-fact">
                    <span class="rs-fact-icon"><i class='bx bx-images'></i></span>
                    <div><p>Uploaded files</p><h4>{{ number_format($info['file_count']) }}</h4></div>
                </div>

                <div class="rs-fact">
                    <span class="rs-fact-icon"><i class='bx bx-check-shield'></i></span>
                    <div>
                        <p>Status</p>
                        <span class="rs-pill rs-pill--green"><i class='bx bx-check-circle'></i> Success</span>
                    </div>
                </div>
            </div>

            @if($liveTables > $info['tables'])
                <p class="rs-hint">
                    The current database has {{ $liveTables }} tables and this backup has {{ $info['tables'] }}.
                    Restore is meant for a backup made by the same version of the system. Tables that are not in the
                    backup are left as they are.
                </p>
            @endif
        </div>

        {{-- ---------- WHAT WILL HAPPEN ---------- --}}
        <div class="rs-card rs-card--warn" role="alert">
            <div class="rs-card-title">
                <span class="rs-card-icon"><i class='bx bx-error'></i></span>
                <h3>Before you continue</h3>
            </div>

            <p style="margin:0 0 12px; font-size:13.5px; font-weight:700; color:#7c2d12;">
                Restoring this backup will replace current application data.
            </p>

            <ul class="rs-list">
                <li>
                    <i class='bx bx-data'></i>
                    <span>The database is replaced with the copy in this backup. Everything entered after <strong>{{ $backup->started_at->format('M d, Y h:i A') }}</strong> will be lost.</span>
                </li>
                <li>
                    <i class='bx bx-images'></i>
                    <span>Uploaded photos, IDs and laboratory files from this backup are put back. Files that are not in the backup are left where they are.</span>
                </li>
                <li>
                    <i class='bx bx-info-circle'></i>
                    <span>The backup history, sessions, cache and queue are not changed.</span>
                </li>
                <li>
                    <i class='bx bx-shield-quarter'></i>
                    <span>A safety backup of the current data is made first. If it fails, nothing is restored.</span>
                </li>
                <li>
                    <i class='bx bx-block'></i>
                    <span>Everyone is blocked from using the system until the restore has finished. This can take a few minutes.</span>
                </li>
            </ul>
        </div>

    </div>

    {{-- ================= CONFIRMATION ================= --}}
    <form method="POST" action="{{ route('admin.backups.restore.run', $backup) }}" class="rs-card rs-card--danger" id="restoreForm" autocomplete="off">
        @csrf

        <div class="rs-card-title">
            <span class="rs-card-icon"><i class='bx bx-lock-alt'></i></span>
            <h3>Confirm restore</h3>
        </div>

        <div class="rs-confirm">
            <div class="rs-confirm-field">
                <label for="confirmation" class="rs-confirm-label">
                    Type <strong>{{ $confirmWord }}</strong> to confirm
                </label>
                <input type="text" id="confirmation" name="confirmation" class="rs-confirm-input"
                       autocomplete="off" autocapitalize="characters" spellcheck="false" required>
            </div>

            <div class="rs-actions">
                <a class="rs-btn-cancel" href="{{ route('admin.backups.index') }}">Cancel</a>
                <button type="submit" class="btn-restore" id="restoreBtn" disabled>
                    <i class="fa-solid fa-clock-rotate-left"></i> <span id="restoreBtnText">Restore backup</span>
                </button>
            </div>
        </div>
    </form>

</div>

<script>
(function () {
    var form  = document.getElementById('restoreForm');
    var input = document.getElementById('confirmation');
    var btn   = document.getElementById('restoreBtn');
    var text  = document.getElementById('restoreBtnText');
    var word  = @json($confirmWord);
    var busy  = false;

    // The button stays disabled until the word is typed exactly. (The server checks it again.)
    input.addEventListener('input', function () {
        btn.disabled = busy || input.value.trim() !== word;
    });

    form.addEventListener('submit', function (e) {
        if (busy || input.value.trim() !== word) { e.preventDefault(); return; }
        busy = true;
        btn.disabled = true;
        input.readOnly = true;
        text.textContent = 'Restoring... keep this page open';
    });

    // Coming back with the browser's Back button must not stay stuck on "Restoring...".
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            busy = false; input.readOnly = false; input.value = '';
            btn.disabled = true; text.textContent = 'Restore backup';
        }
    });
})();
</script>

@endsection