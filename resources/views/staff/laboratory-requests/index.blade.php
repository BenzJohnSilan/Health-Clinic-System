@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-laboratory.css') }}">
@endsection

@section('content')

<div class="container">

    <div class="page-header">
        <h1 class="page-title">Laboratory Results</h1>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- ================= SEARCH + FILTER ================= --}}
    <form method="GET" action="{{ route('staff.laboratory-requests.index') }}" class="lab-toolbar">
        <div class="lab-search-box">
            <i class='bx bx-search'></i>
            <input type="text" name="search" placeholder="Search patient or reference no..." value="{{ $search }}">
        </div>

        <select name="status" class="lab-select" onchange="this.form.submit()">
            <option value="All" {{ $statusFilter === 'All' ? 'selected' : '' }}>All Statuses</option>
            <option value="Awaiting Result" {{ $statusFilter === 'Awaiting Result' ? 'selected' : '' }}>Awaiting Result</option>
            <option value="Result Received" {{ $statusFilter === 'Result Received' ? 'selected' : '' }}>Result Received</option>
            <option value="Correction Required" {{ $statusFilter === 'Correction Required' ? 'selected' : '' }}>Correction Required</option>
            <option value="Reviewed" {{ $statusFilter === 'Reviewed' ? 'selected' : '' }}>Reviewed</option>
        </select>

        <button type="submit" class="lab-search-btn">
            <i class='bx bx-filter-alt'></i> Filter
        </button>
    </form>

    {{-- ================= TABLE ================= --}}
    <div class="table-container">
        <table class="record-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Requested Tests</th>
                    <th>Doctor</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($laboratoryRequests as $labRequest)
                    @php
                        $statusClassMap = [
                            'Awaiting Result'     => 'awaiting',
                            'Result Received'     => 'received',
                            'Correction Required' => 'correction',
                            'Reviewed'            => 'reviewed',
                        ];
                        $pendingByTest      = $labRequest->pendingCorrectionsByTest();
                        $hasAnyCorrection   = $pendingByTest->filter()->isNotEmpty();
                        $requestedTestNames = $labRequest->tests->pluck('test_name')->all();
                        $filesCount         = $labRequest->results->count(); // already excludes soft-deleted (superseded/removed) files
                    @endphp
                    <tr>
                        <td>{{ $labRequest->patientName() }}</td>
                        <td class="lab-tests-cell">{{ $labRequest->testsDisplay() }}</td>
                        <td>Dr. {{ $labRequest->doctor->first_name ?? '' }} {{ $labRequest->doctor->last_name ?? '' }}</td>
                        <td>{{ $labRequest->requested_at?->format('M d, Y') ?? '—' }}</td>
                        <td>
                            <div class="status-stack">
                                <span class="status {{ $statusClassMap[$labRequest->status] ?? 'awaiting' }}">
                                    {{ $labRequest->status }}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                @if($filesCount > 0)
                                    <button type="button" class="btn-files-count @if($hasAnyCorrection) btn-files-count--alert @endif"
                                            onclick="openFilesModal({{ $labRequest->id }})">
                                        @if($hasAnyCorrection)
                                            <i class='bx bx-error-circle'></i> Correction Needed
                                        @else
                                            {{ $filesCount }} {{ Str::plural('file', $filesCount) }} uploaded
                                        @endif
                                    </button>
                                @elseif($labRequest->canAcceptUploads())
                                    <button type="button" class="btn-upload"
                                            onclick='openUploadModal({{ $labRequest->id }}, @json($labRequest->reference_no), @json($labRequest->patientName()), @json($requestedTestNames))'>
                                        Upload Result
                                    </button>
                                @else
                                    <span class="lab-no-action">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="no-data">No laboratory requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px;">
        {{ $laboratoryRequests->onEachSide(1)->links() }}
    </div>

</div>

{{-- ================= UPLOADED LABORATORY RESULTS MODAL (per request) ================= --}}
@foreach($laboratoryRequests as $labRequest)
    @php
        $currentByTest = $labRequest->currentResultsByTest();
        $isReviewed    = $labRequest->isReviewed();
        $canModify     = $labRequest->canPatientModifyResults();
        $pendingByTest = $labRequest->pendingCorrectionsByTest();
    @endphp
    @if($currentByTest->isNotEmpty())
    <div class="modal-overlay" id="filesModal{{ $labRequest->id }}">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <div class="modal-title">Uploaded Laboratory Results</div>
                    <span class="modal-ref-badge">{{ $labRequest->reference_no }} — {{ $labRequest->patientName() }}</span>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('filesModal{{ $labRequest->id }}')">&times;</button>
            </div>

            @foreach($currentByTest as $testName => $file)
                @php
                    // Each test carries its OWN correction state,
                    // independent of every other test on this request.
                    $testCorrection = $pendingByTest->get($testName);
                    $groupNeedsCorrection = $testCorrection !== null;
                @endphp
                <div class="lab-modal-group @if($groupNeedsCorrection) lab-modal-group--correction @endif">
                    <div class="lab-modal-group__name">{{ $testName }}</div>

                    @if($groupNeedsCorrection)
                        <div class="lab-modal-correction">
                            <div class="lab-modal-correction__title">
                                <i class='bx bx-error-circle'></i> This result needs correction
                            </div>
                            <p class="lab-modal-correction__reason">"{{ $testCorrection->reason }}"</p>
                        </div>
                    @endif

                    @if($file)
                        <div class="lab-modal-file">
                            <div class="lab-modal-file__info">
                                <i class='bx {{ $file->file_type === "pdf" ? "bxs-file-pdf" : "bxs-file-image" }}'></i>
                                <span class="lab-modal-file__name">{{ $file->original_filename }}</span>
                            </div>
                            <div class="lab-modal-file__actions">
                                <a href="{{ $file->url }}" target="_blank" class="btn-file-view">View</a>
                                {{-- Staff replacement does NOT depend on who originally uploaded the
                                     file (Section 11 of the workflow spec) — only whether it's still
                                     modifiable at all. --}}
                                @if(!$isReviewed && $canModify)
                                    <button type="button" class="btn-file-replace @if($groupNeedsCorrection) btn-file-replace--alert @endif"
                                            onclick='openReplaceModal({{ $labRequest->id }}, {{ $file->id }}, @json($testName), @json($labRequest->patientName()))'>
                                        Replace
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="lab-modal-file lab-modal-file--empty">
                            <span>Not yet uploaded</span>
                            @if($labRequest->canAcceptUploads())
                                <button type="button" class="btn-file-replace @if($groupNeedsCorrection) btn-file-replace--alert @endif"
                                        onclick='closeModal("filesModal{{ $labRequest->id }}"); openUploadModal({{ $labRequest->id }}, @json($labRequest->reference_no), @json($labRequest->patientName()), @json([$testName]))'>
                                    Upload
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @endif
@endforeach

{{-- ================= UPLOAD / REPLACE RESULT MODAL ================= --}}
<div class="modal-overlay" id="uploadResultModal">
    <div class="modal-box">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="uploadModalTitle">Upload Laboratory Result</div>
                <span class="modal-ref-badge" id="uploadPatientBadge"></span>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeUploadModal()">&times;</button>
        </div>

        <form id="uploadResultForm" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
                <label>Request Reference</label>
                <input type="text" id="uploadRefDisplay" value="" disabled>
            </div>

            <div class="form-group" id="uploadTestGroup">
                <label>Which test is this result for? <span style="color:#dc2626;">*</span></label>
                <select name="test_name" id="uploadTestSelect" required>
                    <option value="" disabled selected>— Select a test —</option>
                </select>
            </div>

            <div class="form-group">
                <label>Result File <span style="color:#dc2626;">*</span></label>
                <input type="file" name="result_file" accept=".pdf,.jpg,.jpeg,.png" required>
                <p class="form-hint">Accepted formats: PDF, JPG, PNG. Max 5 MB.</p>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label>Notes (optional)</label>
                <textarea name="notes" placeholder="e.g. Patient handed over a printed result on..."></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn-submit" id="uploadSubmitBtn">Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function openFilesModal(requestId) {
    const modal = document.getElementById('filesModal' + requestId);
    if (modal) modal.classList.add('show');
}

document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('show');
    });
});

/**
 * Fresh upload — "Which test is this result for?" dropdown is limited
 * to exactly this laboratory request's own requested tests.
 */
function openUploadModal(requestId, referenceNo, patientName, requestedTests) {
    const form   = document.getElementById('uploadResultForm');
    const refEl  = document.getElementById('uploadRefDisplay');
    const select = document.getElementById('uploadTestSelect');

    document.getElementById('uploadModalTitle').textContent = 'Upload Laboratory Result';
    document.getElementById('uploadSubmitBtn').textContent = 'Upload';
    document.getElementById('uploadTestGroup').style.display = '';

    form.action = '/staff/laboratory-requests/' + requestId + '/upload';
    refEl.value = referenceNo;
    document.getElementById('uploadPatientBadge').textContent = patientName;

    select.innerHTML = '<option value="" disabled selected>— Select a test —</option>';
    select.required = true;
    requestedTests.forEach(function (testName) {
        const opt = document.createElement('option');
        opt.value = testName;
        opt.textContent = testName;
        select.appendChild(opt);
    });
    if (requestedTests.length === 1) {
        select.value = requestedTests[0];
    }

    document.getElementById('uploadResultModal').classList.add('show');
}

/**
 * Replace the CURRENT file for one specific test — the test is fixed
 * (taken from the file being replaced), so no dropdown is shown.
 */
function openReplaceModal(requestId, resultId, testName, patientName) {
    const form  = document.getElementById('uploadResultForm');
    const refEl = document.getElementById('uploadRefDisplay');

    document.getElementById('uploadModalTitle').textContent = 'Replace ' + testName + ' Result';
    document.getElementById('uploadSubmitBtn').textContent = 'Replace';
    document.getElementById('uploadTestGroup').style.display = 'none';

    form.action = '/staff/laboratory-requests/' + requestId + '/results/' + resultId + '/replace';
    refEl.value = testName;
    document.getElementById('uploadPatientBadge').textContent = patientName;

    const select = document.getElementById('uploadTestSelect');
    select.required = false;
    select.innerHTML = '';

    document.getElementById('uploadResultModal').classList.add('show');
}

function closeUploadModal() {
    document.getElementById('uploadResultModal').classList.remove('show');
}
</script>

@endsection