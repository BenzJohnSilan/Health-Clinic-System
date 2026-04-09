@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-appointments.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">My Approved Appointments</h1>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
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
                    <th style="width:160px;">Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($appointments as $appointment)
                <tr>
                    <!-- Patient Name -->
                    <td>{{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}</td>

                    <!-- Schedule (Date + Time Inline) -->
                    <td>
                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                        <span style="margin-left:8px; color:#555; font-size:13px;">
                            {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
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
                        @else
                            {{ $appointment->status }}
                        @endif
                    </td>

                    <!-- Action Buttons -->
                    <td>
                        <a href="{{ route('doctor.appointments.show', $appointment->id) }}" class="btn-edit">
                            View
                        </a>

                        <a href="{{ route('doctor.appointments.report', $appointment->id) }}" class="btn-delete">
                            Report
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; font-style:italic; color:#555;">
                        No approved appointments found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection

@push('styles')
<style>
/* ================= PAGE HEADER ================= */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 20px;
    gap: 10px;
}

.page-title {
    font-size: 28px;
    font-weight: 600;
    color: #333;
}

/* ================= TABLE ================= */
.table-container {
    overflow-x: auto;
}

.appointments-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.appointments-table th,
.appointments-table td {
    padding: 12px 15px;
    text-align: left;
    font-size: 14px;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

.appointments-table th {
    background-color: #f3f4f6;
    font-weight: 600;
}

.appointments-table tr:nth-child(even) {
    background-color: #f9fafb;
}

.appointments-table tr:hover {
    background-color: #f0f4ff;
}

/* ================= STATUS ================= */
.status {
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 13px;
    display: inline-block;
}
.status.approved { color: #2563eb; background: #dbeafe; }
.status.completed { color: #16a34a; background: #d1fae5; }
.status.pending   { color: #f59e0b; background: #fef3c7; }
.status.rejected  { color: #dc2626; background: #fee2e2; }
.status.cancelled { color: #b91c1c; background: #fecaca; }

/* ================= BUTTONS ================= */
.btn-edit {
    background-color: #4f46e5;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    text-decoration: none;
    margin-right: 6px;
}
.btn-edit:hover {
    background-color: #4338ca;
}

.btn-delete {
    background-color: #ef4444;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.btn-delete:hover {
    background-color: #b91c1c;
}

/* ================= RESPONSIVE ================= */
@media screen and (max-width: 700px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .appointments-table th,
    .appointments-table td {
        padding: 8px;
        font-size: 13px;
    }

    .btn-edit, .btn-delete {
        font-size: 12px;
        padding: 5px 10px;
    }
}
</style>
@endpush
