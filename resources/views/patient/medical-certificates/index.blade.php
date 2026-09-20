@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-medical-certificates.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= HEADER ================= -->
    <div class="page-header">
        <h2>Medical Certificates</h2>
        <button type="button" class="btn-request" onclick="openRequestCertModal()">
            <i class='bx bx-plus'></i> Request Medical Certificate
        </button>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= STATUS FILTER TABS ================= -->
    <div class="filter-tabs">
        <a href="{{ route('patient.medical-certificates.index', ['status' => 'all']) }}"
           class="filter-tab {{ $filter === 'all' ? 'active' : '' }}">
            All
        </a>
        <a href="{{ route('patient.medical-certificates.index', ['status' => 'pending']) }}"
           class="filter-tab {{ $filter === 'pending' ? 'active' : '' }}">
            Pending
        </a>
        <a href="{{ route('patient.medical-certificates.index', ['status' => 'issued']) }}"
           class="filter-tab {{ $filter === 'issued' ? 'active' : '' }}">
            Issued
        </a>
        <a href="{{ route('patient.medical-certificates.index', ['status' => 'rejected']) }}"
           class="filter-tab {{ $filter === 'rejected' ? 'active' : '' }}">
            Rejected
        </a>
    </div>

    <!-- ================= TABLE ================= -->
    <div class="table-wrapper">
        <table class="medical-table">
            <thead>
                <tr>
                    <th>Certificate No.</th>
                    <th>Appointment</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $certificate)
                    <tr>
                        <td>
                            @if($certificate->certificate_number)
                                <span class="cert-no">{{ $certificate->certificate_number }}</span>
                            @else
                                <span class="cert-no">Request #{{ str_pad($certificate->id, 3, '0', STR_PAD_LEFT) }}</span>
                            @endif
                        </td>
                        <td>
                            {{ optional($certificate->appointment)->appointment_date
                                ? \Carbon\Carbon::parse($certificate->appointment->appointment_date)->format('M d, Y')
                                : '—' }}
                        </td>
                        <td>{{ $certificate->displayPurpose() }}</td>
                        <td>
                            <span class="status {{ $certificate->status }}">{{ ucfirst($certificate->status) }}</span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                @if($certificate->isPending())
                                    <a href="{{ route('patient.medical-certificates.show', $certificate->id) }}" class="btn-view">
                                        View Status
                                    </a>
                                @elseif($certificate->isIssued())
                                    <a href="{{ route('patient.medical-certificates.show', $certificate->id) }}" class="btn-view">
                                        View
                                    </a>
                                    <a href="{{ route('patient.medical-certificates.print', $certificate->id) }}"
                                       target="_blank" class="btn-print">
                                        Print
                                    </a>
                                @elseif($certificate->isRejected())
                                    <a href="{{ route('patient.medical-certificates.show', $certificate->id) }}" class="btn-reason">
                                        View Reason
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="no-data">
                            No medical certificate requests yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($certificates->total() > 0)
                Showing <strong>{{ $certificates->firstItem() }}–{{ $certificates->lastItem() }}</strong>
                of <strong>{{ $certificates->total() }}</strong> result{{ $certificates->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($certificates->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $certificates->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $certificates->currentPage();
                $lastPage    = $certificates->lastPage();

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
                    <a class="page-btn" href="{{ $certificates->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($certificates->hasMorePages())
                <a class="page-btn" href="{{ $certificates->nextPageUrl() }}">
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

<!-- ================= REQUEST MEDICAL CERTIFICATE MODAL ================= -->
<div class="modal-overlay" id="requestCertModal">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="closeRequestCertModal()" aria-label="Close">&times;</button>
        <h3 class="modal-title">Request Medical Certificate</h3>

        <!-- Loading state -->
        <div id="requestCertLoading" class="modal-loading">
            <i class='bx bx-loader-alt bx-spin'></i>
            <span>Checking eligibility…</span>
        </div>

        <!-- Unavailable state -->
        <div id="requestCertUnavailable" class="modal-warning" style="display:none;">
            <i class='bx bx-error-circle'></i>
            <h4 id="requestCertUnavailableTitle">Request Unavailable</h4>
            <p id="requestCertUnavailableText"></p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeRequestCertModal()">Close</button>
            </div>
        </div>

        <!-- Request form state -->
        <form id="requestCertForm" action="{{ route('patient.medical-certificates.store') }}" method="POST" style="display:none;">
            @csrf

            <div class="form-group">
                <label>Appointment <span class="required">*</span></label>
                <select name="appointment_id" id="requestCertAppointment" required></select>
            </div>

            <div class="form-group">
                <label>Purpose <span class="required">*</span></label>
                <select name="purpose" id="requestCertPurpose" required onchange="toggleRequestCertOtherPurpose()">
                    <option value="" disabled selected>Select purpose</option>
                    <option value="School">School</option>
                    <option value="Work">Work</option>
                    <option value="Sick Leave">Sick Leave</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group" id="requestCertOtherPurposeGroup" style="display:none;">
                <label>Other Purpose <span class="required">*</span></label>
                <input type="text" name="other_purpose" placeholder="Please specify">
            </div>

            <div class="form-group">
                <label>Additional Details</label>
                <textarea name="request_details" rows="4" placeholder="e.g. I need this medical certificate for my school absence."></textarea>
                <p class="form-hint">Optional — any extra information the doctor should know.</p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeRequestCertModal()">Cancel</button>
                <button type="submit" class="btn-submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRequestCertModal() {
    const modal        = document.getElementById('requestCertModal');
    const loading       = document.getElementById('requestCertLoading');
    const unavailable    = document.getElementById('requestCertUnavailable');
    const form          = document.getElementById('requestCertForm');

    // Reset to loading state every time the modal opens
    loading.style.display     = 'flex';
    unavailable.style.display = 'none';
    form.style.display        = 'none';

    modal.classList.add('show');

    fetch('{{ route('patient.medical-certificates.eligibility') }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';

            if (data.state === 'no_completed_appointment') {
                document.getElementById('requestCertUnavailableTitle').textContent = 'Request Unavailable';
                document.getElementById('requestCertUnavailableText').textContent =
                    "You cannot request a medical certificate because you don't have a completed appointment yet. Please complete an appointment first.";
                unavailable.style.display = 'flex';
                return;
            }

            if (data.state === 'pending_exists') {
                document.getElementById('requestCertUnavailableTitle').textContent = 'Request Unavailable';
                document.getElementById('requestCertUnavailableText').textContent =
                    'You already have a pending medical certificate request for this doctor. Please wait for the doctor to review your existing request.';
                unavailable.style.display = 'flex';
                return;
            }

            if (data.state === 'no_new_appointment') {
                document.getElementById('requestCertUnavailableTitle').textContent = 'Request Unavailable';
                document.getElementById('requestCertUnavailableText').textContent =
                    'All of your completed appointments already have a medical certificate on file (issued or under correction). You can view or print them from your Medical Certificates list — a new request needs a new completed appointment.';
                unavailable.style.display = 'flex';
                return;
            }

            if (data.state === 'eligible') {
                const select = document.getElementById('requestCertAppointment');
                select.innerHTML = '';
                data.appointments.forEach(appt => {
                    const option = document.createElement('option');
                    option.value = appt.id;
                    option.textContent = appt.date + ' — ' + appt.doctor_name;
                    select.appendChild(option);
                });
                form.style.display = 'block';
            }
        })
        .catch(() => {
            loading.style.display = 'none';
            document.getElementById('requestCertUnavailableTitle').textContent = 'Something Went Wrong';
            document.getElementById('requestCertUnavailableText').textContent =
                'We could not check your eligibility right now. Please try again.';
            unavailable.style.display = 'flex';
        });
}

function closeRequestCertModal() {
    document.getElementById('requestCertModal').classList.remove('show');
}

function toggleRequestCertOtherPurpose() {
    const purpose = document.getElementById('requestCertPurpose').value;
    document.getElementById('requestCertOtherPurposeGroup').style.display = purpose === 'Other' ? 'block' : 'none';
}
</script>

@endsection