@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-appointments.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Appointments</h1>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= TABLE ================= -->
    <div class="table-container">

        <table class="appointments-table">

            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Appointment Schedule</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($appointments as $appointment)

                @php
                    $date    = \Carbon\Carbon::parse($appointment->appointment_date);
                    $time    = \Carbon\Carbon::parse($appointment->appointment_time);
                    $isToday = $date->isToday();
                @endphp

                <tr>

                    <!-- Patient Name -->
                    <td>
                        @if($appointment->patient)
                            {{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}
                        @elseif($appointment->walkinPatient)
                            {{ $appointment->walkinPatient->first_name }} {{ $appointment->walkinPatient->last_name }}
                        @else
                            N/A
                        @endif
                    </td>

                    <!-- Schedule -->
                    <td>
                        {{ $date->format('M d, Y') }}
                        <span style="margin-left:6px; color:#6b7280; font-size:13px;">
                            {{ $time->format('h:i A') }}
                        </span>
                    </td>

                    <!-- Reason -->
                    <td>{{ $appointment->reason ?? '—' }}</td>

                    <!-- Status -->
                    <td>
                        @if($appointment->status === 'Approved')
                            <span class="status approved">Approved</span>

                        @elseif($appointment->status === 'Rescheduled')
                            <span class="status rescheduled">Rescheduled</span>
                            @if($appointment->rescheduledBy)
                                <span class="reschedule-by">
                                    by {{ $appointment->rescheduledBy->first_name ?? ucfirst($appointment->rescheduled_by) }}
                                </span>
                            @endif

                        @elseif($appointment->status === 'No Show')
                            <span class="status no-show">No Show</span>
                        @endif
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="action-buttons">

                            {{-- Consult Button --}}
                            @if($isToday)
                                <a href="{{ route('doctor.appointments.show', $appointment->id) }}"
                                   class="btn-view">
                                    Consult
                                </a>
                            @else
                                <span class="btn-wrapper">
                                    <span class="btn-view-disabled" aria-disabled="true">Consult</span>
                                    <span class="tooltip">Available on {{ $date->format('M d, Y') }}</span>
                                </span>
                            @endif

                            {{-- Reschedule Button --}}
                            <button
                                class="btn-reschedule"
                                onclick="openReschedule(
                                    {{ $appointment->id }},
                                    '{{ $date->format('Y-m-d') }}',
                                    '{{ $time->format('H:i') }}'
                                )">
                                Reschedule
                            </button>

                            {{-- No Show Button — only today's approved/rescheduled --}}
                            @if(in_array($appointment->status, ['Approved', 'Rescheduled']) && $isToday)
                                <button
                                    class="btn-no-show"
                                    onclick="openNoShow({{ $appointment->id }})">
                                    No Show
                                </button>
                            @endif

                        </div>
                    </td>

                </tr>

                @empty
                <tr>
                    <td colspan="5" class="no-data">
                        No approved or rescheduled appointments found.
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

<!-- ===== RESCHEDULE MODAL ===== -->
<div id="rescheduleModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-icon icon-reschedule">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div>
                <h3 class="modal-title">Reschedule Appointment</h3>
                <p class="modal-subtitle">Set a new date and time for this appointment.</p>
            </div>
            <button class="modal-close" onclick="closeReschedule()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="modal-divider"></div>

        <form id="rescheduleForm" method="POST">
            @csrf
            @method('PATCH')

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="rescheduleDate">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            New Date
                        </label>
                        <input
                            type="date"
                            name="appointment_date"
                            id="rescheduleDate"
                            min="{{ date('Y-m-d') }}"
                            required
                            class="form-input"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="rescheduleTime">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            New Time
                        </label>
                        <input
                            type="time"
                            name="appointment_time"
                            id="rescheduleTime"
                            min="08:00"
                            max="17:00"
                            required
                            class="form-input"
                        >
                    </div>
                </div>
            </div>

            <div class="modal-divider"></div>
            <div class="modal-footer">
                <button type="button" onclick="closeReschedule()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-confirm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Confirm Reschedule
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ===== NO SHOW MODAL ===== -->
<div id="noShowModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-icon icon-no-show">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div>
                <h3 class="modal-title">Mark as No Show</h3>
                <p class="modal-subtitle">Are you sure the patient did not show up for this appointment?</p>
            </div>
            <button class="modal-close" onclick="closeNoShow()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="modal-divider"></div>

        <form id="noShowForm" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="No Show">

            <div class="modal-body">
                <p style="font-size:14px; color:#374151; margin:0;">
                    This action will update the appointment status to <strong>No Show</strong>.
                    This cannot be undone.
                </p>
            </div>

            <div class="modal-divider"></div>
            <div class="modal-footer">
                <button type="button" onclick="closeNoShow()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-confirm-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Confirm No Show
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
/* ===== Reschedule Modal ===== */
function openReschedule(id, date, time) {
    document.getElementById('rescheduleDate').value = date;
    document.getElementById('rescheduleTime').value = time;
    document.getElementById('rescheduleForm').action = `/doctor/appointments/${id}/reschedule`;

    const modal = document.getElementById('rescheduleModal');
    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('modal-visible'));
}

function closeReschedule() {
    const modal = document.getElementById('rescheduleModal');
    modal.classList.remove('modal-visible');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
}

document.getElementById('rescheduleModal').addEventListener('click', function (e) {
    if (e.target === this) closeReschedule();
});

/* ===== No Show Modal ===== */
function openNoShow(id) {
    document.getElementById('noShowForm').action = `/doctor/appointments/${id}`;

    const modal = document.getElementById('noShowModal');
    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('modal-visible'));
}

function closeNoShow() {
    const modal = document.getElementById('noShowModal');
    modal.classList.remove('modal-visible');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
}

document.getElementById('noShowModal').addEventListener('click', function (e) {
    if (e.target === this) closeNoShow();
});
</script>

@endsection