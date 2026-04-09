@extends('layouts.patient')

@section('content')
<!-- ================= PAGE HEADER ================= -->
<div class="page-header">
    <div class="header-left">
        <h1>My Appointments</h1>
    </div>
</div>

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
            </tr>
        </thead>
        <tbody>
            @forelse($appointments as $appointment)
                <tr>
                    <td>
                        {{ $appointment->patient->first_name ?? '' }}
                        {{ $appointment->patient->last_name ?? '' }}
                    </td>

                    <!-- FIXED DATE + TIME -->
                    <td>
                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                        -
                        {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                    </td>

                    <td>
                        {{ $appointment->doctor->first_name ?? 'N/A' }}
                        {{ $appointment->doctor->last_name ?? '' }}
                    </td>

                    <!-- FIXED reason instead of notes -->
                    <td>{{ $appointment->reason }}</td>

                    <td>
                        <span class="status {{ strtolower($appointment->status) }}">
                            {{ $appointment->status }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align:center;">
                        No appointments found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection