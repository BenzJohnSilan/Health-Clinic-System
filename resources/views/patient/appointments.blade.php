@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-appointments.css') }}">
@endsection

@section('content')

<!-- ================= PAGE HEADER ================= -->
<div class="page-header">
    <div class="header-left">
        <h1>My Appointments</h1>
    </div>
</div>

<!-- ================= ALERTS ================= -->
@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert-error">{{ session('error') }}</div>
@endif

<!-- ================= APPOINTMENTS TABLE ================= -->
<div class="appointments-table">
    <table>
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>Appointment Schedule</th>
                <th>Doctor</th>
                <th>Reason</th>
                <th>Status</th>
                <th style="width: 130px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($appointments as $appointment)
                <tr>
                    <td>
                        {{ $appointment->patient->first_name ?? auth()->user()->first_name }}
                        {{ $appointment->patient->last_name ?? auth()->user()->last_name }}
                    </td>

                    <td>
                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                        &mdash;
                        {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                    </td>

                    <td>
                        {{ $appointment->doctor->first_name ?? 'N/A' }}
                        {{ $appointment->doctor->last_name ?? '' }}
                    </td>

                    <td>{{ $appointment->reason }}</td>

                    <td>
                        <span class="status {{ strtolower($appointment->status) }}">
                            {{ $appointment->status }}
                        </span>
                        @if($appointment->status === 'Rescheduled' && $appointment->rescheduled_by)
                            <div class="reschedule-by">by {{ ucfirst($appointment->rescheduled_by) }}</div>
                        @endif
                    </td>

                    <td>
                        {{--
                            Show Cancel button for: Pending, Approved, Rescheduled
                            Hidden for: Rejected, Completed, Cancelled

                            For Approved: the 2-hour rule is enforced server-side.
                            We also disable the button client-side if within 2 hours.
                        --}}
                        @if(in_array($appointment->status, ['Pending', 'Approved', 'Rescheduled']))
                            @php
                                // Parse date and time separately to avoid "double time" exception
                                // when appointment_date is stored as datetime (e.g. "2026-05-18 00:00:00")
                                $dateOnly = \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d');
                                $timeOnly = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i:s');
                                $apptDateTime = \Carbon\Carbon::parse($dateOnly . ' ' . $timeOnly);
                                $minutesUntil = \Carbon\Carbon::now()->diffInMinutes($apptDateTime, false);
                                $withinTwoHours = $appointment->status === 'Approved' && $minutesUntil <= 120;
                            @endphp

                            @if($withinTwoHours)
                                <span class="no-cancel-hint" title="Cannot cancel within 2 hours of an approved appointment">
                                    Locked
                                </span>
                            @else
                                <button
                                    class="btn-cancel-appt"
                                    onclick="openCancel({{ $appointment->id }}, '{{ $appointment->status }}')">
                                    Cancel
                                </button>
                            @endif
                        @else
                            <span class="no-action">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-row">
                        No appointments found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- ===== CANCEL CONFIRMATION MODAL ===== -->
<div id="cancelModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3 class="modal-title">Cancel Appointment</h3>
        <p class="modal-message" id="cancelModalMessage">
            Are you sure you want to cancel this appointment? This action cannot be undone.
        </p>

        <form id="cancelForm" method="POST">
            @csrf
            @method('DELETE')

            <div class="modal-actions">
                <button type="button" onclick="closeCancel()" class="btn-back">No, Go Back</button>
                <button type="submit" class="btn-danger">Yes, Cancel Appointment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancel(id, status) {
    const msg = document.getElementById('cancelModalMessage');

    if (status === 'Approved') {
        msg.textContent = 'This appointment has already been approved. Are you sure you want to cancel it? This action cannot be undone.';
    } else {
        msg.textContent = 'Are you sure you want to cancel this appointment? This action cannot be undone.';
    }

    document.getElementById('cancelForm').action = `/patient/appointments/${id}/cancel`;
    document.getElementById('cancelModal').style.display = 'flex';
}

function closeCancel() {
    document.getElementById('cancelModal').style.display = 'none';
}

document.getElementById('cancelModal').addEventListener('click', function (e) {
    if (e.target === this) closeCancel();
});
</script>

@endsection