@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-backups.css') }}">
@endsection

@section('content')

<div class="container">

    <div class="page-header">
        <div>
            <a class="bk-back-link" href="{{ route('admin.backups.index') }}">
                <i class="fa-solid fa-arrow-left"></i> Back to Backup &amp; Recovery
            </a>
            <h1>Restore backup</h1>
            <p>Check this backup carefully before you continue.</p>
        </div>
    </div>

    @if(session('error'))
        <div class="alert-error" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ================= WARNING ================= --}}
    <div class="bk-warning" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <div>
            <strong>Restoring this backup will replace current application data.</strong>
            <ul>
                <li>The database is replaced with the copy in this backup. Everything entered after {{ $backup->started_at->format('M d, Y h:i A') }} will be lost.</li>
                <li>Uploaded photos, IDs, laboratory files and signatures from this backup are put back. Files that are not in the backup are left where they are.</li>
                <li>The backup history, sessions, cache and queue are not changed.</li>
                <li>A safety backup of the current data is made first. If it fails, nothing is restored.</li>
                <li>Everyone is blocked from using the system until the restore has finished. This can take a few minutes.</li>
            </ul>
        </div>
    </div>

    {{-- ================= WHAT WILL BE RESTORED ================= --}}
    <div class="bk-panel">
        <h2 class="bk-panel-title">Backup to restore</h2>

        <dl class="bk-detail-list">
            <div><dt>Backup file</dt><dd class="bk-mono">{{ $backup->filename }}</dd></div>
            <div><dt>Date &amp; time</dt><dd>{{ $backup->started_at->format('M d, Y h:i A') }}</dd></div>
            <div>
                <dt>Type</dt>
                <dd>{{ match($backup->type) { 'automatic' => 'Automatic', 'pre-restore' => 'Pre-restore', default => 'Manual' } }}</dd>
            </div>
            <div><dt>Size</dt><dd>{{ $backup->formatted_size ?? '—' }}</dd></div>
            <div><dt>Database tables</dt><dd>{{ $info['tables'] }}</dd></div>
            <div><dt>Uploaded files</dt><dd>{{ number_format($info['file_count']) }}</dd></div>
            <div>
                <dt>Status</dt>
                <dd><span class="bk-status bk-status--success"><i class="fa-solid fa-circle-check"></i> Success</span></dd>
            </div>
        </dl>

        @if($liveTables > $info['tables'])
            <p class="bk-hint">
                The current database has {{ $liveTables }} tables and this backup has {{ $info['tables'] }}.
                Restore is meant for a backup made by the same version of the system. Tables that are not in the
                backup are left as they are.
            </p>
        @endif
    </div>

    {{-- ================= CONFIRMATION ================= --}}
    <form method="POST" action="{{ route('admin.backups.restore.run', $backup) }}" class="bk-panel" id="restoreForm" autocomplete="off">
        @csrf

        <label for="confirmation" class="bk-confirm-label">
            Type <strong>{{ $confirmWord }}</strong> to confirm
        </label>
        <input type="text" id="confirmation" name="confirmation" class="bk-confirm-input"
               autocomplete="off" autocapitalize="characters" spellcheck="false" required>

        <div class="bk-form-actions">
            <a class="btn-back bk-cancel-link" href="{{ route('admin.backups.index') }}">Cancel</a>
            <button type="submit" class="btn-restore" id="restoreBtn" disabled>
                <i class="fa-solid fa-clock-rotate-left"></i> <span id="restoreBtnText">Restore backup</span>
            </button>
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
