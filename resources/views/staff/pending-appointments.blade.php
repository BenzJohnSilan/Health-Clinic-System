@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-pending-appointments.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Pending Appointments</h1>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= PENDING APPOINTMENTS TABLE ================= -->
    <div class="table-container">
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Doctor</th>
                    <th>Schedule</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($appointments as $appointment)
                <tr>
                    <td>{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</td>
                    <td>{{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}</td>
                    <td>
                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') }}
                        at
                        {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') }}
                    </td>
                    <td>{{ $appointment->reason ?? '-' }}</td>
                    <td>
                        <!-- APPROVE -->
                        <form action="{{ route('staff.appointments.approve', $appointment->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn-approve"
                                onclick="return confirm('Approve this appointment?')">
                                Approve
                            </button>
                        </form>

                        <!-- REJECT -->
                        <button
                            class="btn-reject openRejectModal"
                            data-id="{{ $appointment->id }}"
                            data-name="{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}"
                            data-email="{{ $appointment->patient->email }}"
                            data-phone="{{ $appointment->patient->contact_number ?? 'N/A' }}">
                            Reject
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="empty-row">
                        No pending appointments found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($appointments->total() > 0)
                Showing <strong>{{ $appointments->firstItem() }}–{{ $appointments->lastItem() }}</strong>
                of <strong>{{ $appointments->total() }}</strong> result{{ $appointments->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($appointments->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $appointments->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $appointments->currentPage();
                $lastPage    = $appointments->lastPage();

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
                    <a class="page-btn" href="{{ $appointments->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($appointments->hasMorePages())
                <a class="page-btn" href="{{ $appointments->nextPageUrl() }}">
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

<!-- ================= REJECT MODAL ================= -->
<div id="rejectModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" id="closeModal" aria-label="Close">&times;</button>
        <h3 class="modal-title">Reject Appointment</h3>

        <div class="modal-patient-info">
            <div class="info-row">
                <span class="info-label">Patient</span>
                <span class="info-value" id="modalName">—</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value" id="modalEmail">—</span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone</span>
                <span class="info-value" id="modalPhone">—</span>
            </div>
        </div>

        <form id="rejectForm" method="POST" data-url="{{ route('staff.appointments.reject', ':id') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="rejectReason">Reason for Rejection</label>
                <textarea
                    class="form-input"
                    name="reason"
                    id="rejectReason"
                    rows="3"
                    placeholder="Enter reason..."
                    required></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-back" id="cancelRejectBtn">Cancel</button>
                <button type="submit" class="btn-danger">Confirm Reject</button>
            </div>
        </form>

    </div>
</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- ================= JAVASCRIPT ================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const rejectModal   = document.getElementById('rejectModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelBtn     = document.getElementById('cancelRejectBtn');

    document.querySelectorAll('.openRejectModal').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('modalName').textContent  = this.dataset.name;
            document.getElementById('modalEmail').textContent = this.dataset.email;
            document.getElementById('modalPhone').textContent = this.dataset.phone;

            const url = document.getElementById('rejectForm').dataset.url.replace(':id', this.dataset.id);
            document.getElementById('rejectForm').action = url;

            rejectModal.style.display = 'flex';
        });
    });

    function closeModal() {
        rejectModal.style.display = 'none';
    }

    closeModalBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    rejectModal.addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

});
</script>

@endsection