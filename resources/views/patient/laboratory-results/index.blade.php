@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-laboratory.css') }}">
@endsection

@section('content')

<div class="container">

    <div class="page-header page-header--list">
        <h2>Laboratory Results</h2>
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
    <form method="GET" action="{{ route('patient.laboratory-results.index') }}" class="lab-toolbar">
        <div class="lab-search-box">
            <i class='bx bx-search'></i>
            <input type="text" name="search" placeholder="Search by reference no. or test name..." value="{{ $search }}">
        </div>

        <select name="status" class="lab-select" aria-label="Filter by status">
            <option value="All" {{ $statusFilter === 'All' ? 'selected' : '' }}>All Statuses</option>
            <option value="Awaiting Result" {{ $statusFilter === 'Awaiting Result' ? 'selected' : '' }}>Awaiting Result</option>
            <option value="Result Received" {{ $statusFilter === 'Result Received' ? 'selected' : '' }}>Result Received</option>
            <option value="Correction Required" {{ $statusFilter === 'Correction Required' ? 'selected' : '' }}>Correction Needed</option>
            <option value="Reviewed" {{ $statusFilter === 'Reviewed' ? 'selected' : '' }}>Reviewed</option>
        </select>

        <button type="submit" class="lab-search-btn">
            <i class='bx bx-filter-alt'></i> Filter
        </button>
    </form>

    <div class="table-wrapper">
        <table class="medical-table">
            <thead>
                <tr>
                    <th>Reference No.</th>
                    <th>Requested Tests</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($laboratoryRequests as $labRequest)
                    @php
                        // Patient-friendly badge text. The database status
                        // stays "Correction Required" everywhere else in the
                        // system (doctor/staff pages, logs); only what the
                        // Patient reads is softened, per the simple-wording
                        // rule for this page.
                        $statusClassMap = [
                            'Awaiting Result'     => 'awaiting',
                            'Result Received'     => 'received',
                            'Correction Required' => 'correction',
                            'Reviewed'            => 'reviewed',
                        ];
                        $patientStatusLabel = $labRequest->status === 'Correction Required'
                            ? 'Correction Needed'
                            : $labRequest->status;

                        $pendingByTest      = $labRequest->pendingCorrectionsByTest();
                        $hasAnyCorrection   = $pendingByTest->filter()->isNotEmpty();
                        $requestedTestNames = $labRequest->tests->pluck('test_name')->all();
                        $activeResults      = $labRequest->results; // already excludes soft-deleted (superseded/removed) files
                        $filesCount         = $activeResults->count();
                    @endphp
                    <tr>
                        <td>{{ $labRequest->reference_no }}</td>
                        <td class="lab-tests-cell">{{ $labRequest->testsDisplay() }}</td>
                        <td>{{ $labRequest->requested_at?->format('M d, Y') ?? '—' }}</td>
                        <td>
                            <div class="status-stack">
                                <span class="status {{ $statusClassMap[$labRequest->status] ?? 'awaiting' }}">
                                    {{ $patientStatusLabel }}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                @if($labRequest->isReviewed())
                                    <button type="button" class="btn-view-review"
                                            onclick="openReviewModal({{ $labRequest->id }})">
                                        View Review
                                    </button>
                                @elseif($filesCount > 0)
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
                                            onclick='openUploadModal({{ $labRequest->id }}, @json($labRequest->reference_no), @json($requestedTestNames))'>
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
                        <td colspan="5" class="no-data">
                            @if($search !== '' || $statusFilter !== 'All')
                                No laboratory requests match your search or filter.
                            @else
                                No laboratory requests yet. Your doctor will create one during a consultation.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= CARDS (Mobile) =================
         Phones (<= 640px) show these cards instead of the table. --}}
    <div class="lab-cards">
        @forelse($laboratoryRequests as $labRequest)
            @php
                $statusClassMap = [
                    'Awaiting Result'     => 'awaiting',
                    'Result Received'     => 'received',
                    'Correction Required' => 'correction',
                    'Reviewed'            => 'reviewed',
                ];
                $patientStatusLabel = $labRequest->status === 'Correction Required'
                    ? 'Correction Needed'
                    : $labRequest->status;

                $pendingByTest      = $labRequest->pendingCorrectionsByTest();
                $hasAnyCorrection   = $pendingByTest->filter()->isNotEmpty();
                $requestedTestNames = $labRequest->tests->pluck('test_name')->all();
                $filesCount         = $labRequest->results->count();
            @endphp
            <div class="lab-card">
                <div class="lab-card-top">
                    <span class="ref-no">{{ $labRequest->reference_no }}</span>
                    <span class="status {{ $statusClassMap[$labRequest->status] ?? 'awaiting' }}">
                        {{ $patientStatusLabel }}
                    </span>
                </div>

                <div class="lab-card-row">
                    <span class="lab-card-label">Tests</span>
                    <span class="lab-card-value">{{ $labRequest->testsDisplay() }}</span>
                </div>

                <div class="lab-card-row">
                    <span class="lab-card-label">Requested</span>
                    <span class="lab-card-value">{{ $labRequest->requested_at?->format('M d, Y') ?? '—' }}</span>
                </div>

                <div class="lab-card-actions">
                    @if($labRequest->isReviewed())
                        <button type="button" class="btn-view-review"
                                onclick="openReviewModal({{ $labRequest->id }})">
                            View Review
                        </button>
                    @elseif($filesCount > 0)
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
                                onclick='openUploadModal({{ $labRequest->id }}, @json($labRequest->reference_no), @json($requestedTestNames))'>
                            Upload Result
                        </button>
                    @else
                        <span class="lab-no-action">No action needed</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="lab-empty">
                @if($search !== '' || $statusFilter !== 'All')
                    No laboratory requests match your search or filter.
                @else
                    No laboratory requests yet. Your doctor will create one during a consultation.
                @endif
            </div>
        @endforelse
    </div>

    {{-- ================= PAGINATION ================= --}}
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($laboratoryRequests->total() > 0)
                Showing <strong>{{ $laboratoryRequests->firstItem() }}–{{ $laboratoryRequests->lastItem() }}</strong>
                of <strong>{{ $laboratoryRequests->total() }}</strong> result{{ $laboratoryRequests->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($laboratoryRequests->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $laboratoryRequests->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $laboratoryRequests->currentPage();
                $lastPage    = $laboratoryRequests->lastPage();

                $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1
                        || $p === $lastPage
                        || abs($p - $currentPage) <= 2;
                })->values();
            @endphp

            @php $prev = null; @endphp
            @foreach($pages as $page)
                @if($prev !== null && $page - $prev > 1)
                    <span class="page-ellipsis">…</span>
                @endif

                @if($page === $currentPage)
                    <span class="page-btn active">{{ $page }}</span>
                @else
                    <a class="page-btn" href="{{ $laboratoryRequests->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($laboratoryRequests->hasMorePages())
                <a class="page-btn" href="{{ $laboratoryRequests->nextPageUrl() }}">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @else
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            @endif

        </nav>
    </div>

</div>

<!-- ================= FONT AWESOME (for pagination icons) ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

{{-- ================= UPLOADED LABORATORY RESULTS MODAL (per request) ================= --}}
@foreach($laboratoryRequests as $labRequest)
    @php
        $currentByTest  = $labRequest->currentResultsByTest();
        $isReviewed     = $labRequest->isReviewed();
        $canModify      = $labRequest->canPatientModifyResults();
        $pendingByTest  = $labRequest->pendingCorrectionsByTest();
    @endphp
    @if($currentByTest->isNotEmpty())
    <div class="modal-overlay" id="filesModal{{ $labRequest->id }}">
        <div class="modal-box modal-box--wide">

            <div class="modal-header">
                <div>
                    <div class="modal-title">Uploaded Laboratory Results</div>
                    <div class="modal-ref-badge">{{ $labRequest->reference_no }}</div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('filesModal{{ $labRequest->id }}')">&times;</button>
            </div>

            @foreach($currentByTest as $testName => $file)
                @php
                    // Each test's own correction, independent of every
                    // other test's (Section 8 of the multi-test spec) —
                    // CBC can be flagged while Urinalysis stays plain.
                    $testCorrection = $pendingByTest->get($testName);
                    $groupNeedsCorrection = $testCorrection !== null;
                @endphp
                <div class="lab-modal-group @if($groupNeedsCorrection) lab-modal-group--correction @endif">
                    <span class="lab-modal-group__name">{{ $testName }}</span>

                    @if($groupNeedsCorrection)
                        <div class="lab-modal-correction">
                            <div class="lab-modal-correction__title">
                                <i class='bx bx-error-circle'></i> This result needs correction
                            </div>
                            {{-- The Doctor's correction reason is internal and is not shown to the patient. --}}
                            <p class="lab-modal-correction__reason">Your doctor needs a new copy of this result. Please upload the correct file.</p>
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
                                @if(!$isReviewed && $canModify && $file->uploaded_by === auth()->id())
                                    <button type="button" class="btn-file-replace @if($groupNeedsCorrection) btn-file-replace--alert @endif"
                                            onclick='openReplaceModal({{ $labRequest->id }}, {{ $file->id }}, @json($testName))'>
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
                                        onclick='closeModal("filesModal{{ $labRequest->id }}"); openUploadModal({{ $labRequest->id }}, @json($labRequest->reference_no), @json([$testName]))'>
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

{{-- ================= DOCTOR'S REVIEW MODAL (Reviewed requests only) ================= --}}
@foreach($laboratoryRequests as $labRequest)
    @continue(!$labRequest->isReviewed())
    @php
        $currentByTest = $labRequest->currentResultsByTest();
    @endphp
    <div class="modal-overlay" id="reviewModal{{ $labRequest->id }}">
        <div class="modal-box modal-box--wide">

            <div class="modal-header">
                <div>
                    <div class="modal-title">Laboratory Result</div>
                    <div class="modal-ref-badge">{{ $labRequest->reference_no }}</div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('reviewModal{{ $labRequest->id }}')">&times;</button>
            </div>

            <div class="lab-review-section">
                <div class="lab-review-section__title"><i class='bx bx-test-tube'></i> Requested Tests</div>
                <div class="lab-review-value">{{ $labRequest->testsDisplay() }}</div>
            </div>

            <div class="lab-review-section">
                <div class="lab-review-section__title"><i class='bx bx-folder-open'></i> Uploaded Results</div>
                @foreach($currentByTest as $testName => $file)
                    <div class="lab-modal-file">
                        <div class="lab-modal-file__info">
                            <span class="lab-modal-group__name" style="margin:0;">{{ $testName }}</span>
                            @if($file)
                                <span class="lab-modal-file__name">{{ $file->original_filename }}</span>
                            @endif
                        </div>
                        <div class="lab-modal-file__actions">
                            @if($file)
                                <a href="{{ $file->url }}" target="_blank" class="btn-file-view">View</a>
                            @else
                                <span class="lab-no-action">Not uploaded</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="lab-review-section lab-review-section--last">
                <div class="lab-review-section__title"><i class='bx bx-message-square-detail'></i> Doctor's Review</div>
                <div class="lab-review-card">
                    <p class="lab-review-text">{{ $labRequest->review_notes ?: 'Your doctor has reviewed this laboratory result.' }}</p>
                    <p class="lab-review-meta">
                        Reviewed {{ $labRequest->reviewed_at?->format('F d, Y g:i A') ?? '' }}
                        @if($labRequest->reviewedBy)
                            by Dr. {{ $labRequest->reviewedBy->first_name }} {{ $labRequest->reviewedBy->last_name }}
                        @endif
                    </p>
                </div>
            </div>

        </div>
    </div>
@endforeach

{{-- ================= UPLOAD / REPLACE RESULT MODAL ================= --}}
<div class="modal-overlay" id="uploadResultModal">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-title" id="uploadModalTitle">Upload Laboratory Result</div>
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
                <textarea name="notes" placeholder="Any notes for your doctor..."></textarea>
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

function openReviewModal(requestId) {
    const modal = document.getElementById('reviewModal' + requestId);
    if (modal) modal.classList.add('show');
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
function openUploadModal(requestId, referenceNo, requestedTests) {
    const form   = document.getElementById('uploadResultForm');
    const refEl  = document.getElementById('uploadRefDisplay');
    const select = document.getElementById('uploadTestSelect');

    document.getElementById('uploadModalTitle').textContent = 'Upload Laboratory Result';
    document.getElementById('uploadSubmitBtn').textContent = 'Upload';
    document.getElementById('uploadTestGroup').style.display = '';

    form.action = '/patient/laboratory-results/' + requestId + '/upload';
    form.dataset.mode = 'upload';
    refEl.value = referenceNo;

    select.innerHTML = '<option value="" disabled selected>— Select a test —</option>';
    select.required = true;
    requestedTests.forEach(function (testName) {
        const opt = document.createElement('option');
        opt.value = testName;
        opt.textContent = testName;
        select.appendChild(opt);
    });
    // A single requested test needs no choice at all.
    if (requestedTests.length === 1) {
        select.value = requestedTests[0];
    }

    document.getElementById('uploadResultModal').classList.add('show');
}

/**
 * Replace the CURRENT file for one specific test — the test is fixed
 * (taken from the file being replaced), so no dropdown is shown at all.
 */
function openReplaceModal(requestId, resultId, testName) {
    const form  = document.getElementById('uploadResultForm');
    const refEl = document.getElementById('uploadRefDisplay');

    document.getElementById('uploadModalTitle').textContent = 'Replace ' + testName + ' Result';
    document.getElementById('uploadSubmitBtn').textContent = 'Replace';
    document.getElementById('uploadTestGroup').style.display = 'none';

    form.action = '/patient/laboratory-results/' + requestId + '/results/' + resultId + '/replace';
    form.dataset.mode = 'replace';
    refEl.value = testName;

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