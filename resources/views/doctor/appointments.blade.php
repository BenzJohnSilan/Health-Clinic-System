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

    <!-- Alerts -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= APPOINTMENTS TABLE ================= -->
    <div class="table-container">
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Appointment Schedule</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th style="width:220px;">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($appointments as $appointment)

                @php
                    $date = \Carbon\Carbon::parse($appointment->appointment_date);
                    $time = \Carbon\Carbon::parse($appointment->appointment_time);
                @endphp

                <tr>
                    <!-- Patient Name -->
                    <td>
                        {{ $appointment->patient->first_name ?? 'N/A' }}
                        {{ $appointment->patient->last_name ?? '' }}
                    </td>

                    <!-- Schedule -->
                    <td>
                        {{ $date->format('F d, Y') }}
                        <span style="margin-left:8px; color:#555; font-size:13px;">
                            {{ $time->format('h:i A') }}
                        </span>
                    </td>

                    <!-- Reason -->
                    <td>{{ $appointment->reason ?? '-' }}</td>

                    <!-- Status -->
                    <td>
                        @if($appointment->status === 'Approved')
                            <span class="status approved">Approved</span>

                        @elseif($appointment->status === 'Completed')
                            <span class="status completed">Completed</span>

                        @elseif($appointment->status === 'Pending')
                            <span class="status pending">Pending</span>

                        @elseif($appointment->status === 'Rejected')
                            <span class="status rejected">Rejected</span>

                        @elseif($appointment->status === 'Cancelled')
                            <span class="status cancelled">Cancelled</span>

                        @elseif($appointment->status === 'Rescheduled')
                            <span class="status rescheduled">Rescheduled</span>
                            @if($appointment->rescheduled_by)
                                <span class="reschedule-by">
                                    by {{ ucfirst($appointment->rescheduled_by) }}
                                </span>
                            @endif

                        @else
                            {{ $appointment->status }}
                        @endif
                    </td>

                    <!-- Actions -->
                    <td>
                        <a href="{{ route('doctor.appointments.show', $appointment->id) }}" class="btn-view">
                            View
                        </a>

                        <a href="{{ route('doctor.appointments.report', $appointment->id) }}" class="btn-report">
                            Report
                        </a>

                        {{-- Reschedule button --}}
                        @if(in_array($appointment->status, ['Approved', 'Rescheduled']))
                            <button
                                class="btn-reschedule"
                                onclick="openReschedule(
                                    {{ $appointment->id }},
                                    '{{ $date->format('Y-m-d') }}',
                                    '{{ $time->format('H:i') }}'
                                )">
                                Reschedule
                            </button>
                        @endif
                    </td>
                </tr>

                @empty
                <tr>
                    <td colspan="6" style="text-align:center; font-style:italic; color:#555;">
                        No appointments found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<!-- ===== RESCHEDULE MODAL ===== -->
<div id="rescheduleModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <!-- Modal Header -->
        <div class="modal-header">
            <div class="modal-icon">
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

        <!-- Divider -->
        <div class="modal-divider"></div>

        <!-- Modal Body -->
        <form id="rescheduleForm" method="POST">
            @csrf
            @method('PATCH')

            <div class="modal-body">
                <div class="form-row">
                    <!-- Date Field -->
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

                    <!-- Time Field -->
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

            <!-- Modal Footer -->
            <div class="modal-divider"></div>
            <div class="modal-footer">
                <button type="button" onclick="closeReschedule()" class="btn-cancel">
                    Cancel
                </button>
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

<script>
function openReschedule(id, date, time) {
    document.getElementById('rescheduleDate').value = date;
    document.getElementById('rescheduleTime').value = time;
    document.getElementById('rescheduleForm').action = `/doctor/appointments/${id}/reschedule`;

    const modal = document.getElementById('rescheduleModal');
    modal.style.display = 'flex';
    // Trigger animation
    requestAnimationFrame(() => {
        modal.classList.add('modal-visible');
    });
}

function closeReschedule() {
    const modal = document.getElementById('rescheduleModal');
    modal.classList.remove('modal-visible');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 200);
}

document.getElementById('rescheduleModal').addEventListener('click', function (e) {
    if (e.target === this) closeReschedule();
});
</script>
@endsection