@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-report.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- HEADER (hidden on print) -->
    <div class="page-header no-print">
        <h2>Medical Report</h2>
        <div class="header-actions">
            <a href="{{ route('doctor.appointments.show', $appointment->id) }}" class="btn-cancel">
                ← Back
            </a>
            <button onclick="window.print()" class="btn-save">
                🖨 Print Report
            </button>
        </div>
    </div>

    <!-- APPOINTMENT DETAILS -->
    <div class="card">
        <h3 class="section-title">Appointment Information</h3>

        <table class="info-table">
            <tr>
                <th>Patient Name</th>
                <td>
                    {{ $appointment->patient->first_name }}
                    {{ $appointment->patient->last_name }}
                </td>
            </tr>
            <tr>
                <th>Appointment Date</th>
                <td>{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</td>
            </tr>
            <tr>
                <th>Time</th>
                <td>{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</td>
            </tr>
            <tr>
                <th>Reason</th>
                <td>{{ $appointment->reason }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $appointment->status }}</td>
            </tr>
        </table>
    </div>

    <!-- DIAGNOSIS -->
    <div class="card">
        <h3 class="section-title">Diagnosis</h3>

        @if($appointment->diagnosis)
            <p style="white-space: pre-wrap; color:#1f2937; line-height:1.7;">
                {{ $appointment->diagnosis }}
            </p>
        @else
            <p style="color:#6b7280; font-style:italic;">No diagnosis recorded.</p>
        @endif
    </div>

    <!-- PRESCRIBED MEDICINES (view only, no delete) -->
    <div class="card">
        <h3 class="section-title">Prescribed Medicines</h3>

        <table class="medicine-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine Name</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Qty Prescribed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($prescriptions as $i => $prescription)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $prescription->medicine->medicine_name
                            ?? $prescription->manual_medicine_name
                            ?? 'N/A' }}
                    </td>
                    <td>{{ $prescription->dosage }}</td>
                    <td>{{ $prescription->frequency }}</td>
                    <td>{{ $prescription->duration }}</td>
                    <td>{{ $prescription->quantity_prescribed }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#6b7280; font-style:italic;">
                        No medicines prescribed.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- REVIEW SCHEDULE (view only) -->
    <div class="card">
        <h3 class="section-title">Review Schedule</h3>

        @if($review)
            <table class="info-table">
                @if($review->next_review_date)
                <tr>
                    <th>Next Review Date</th>
                    <td>{{ \Carbon\Carbon::parse($review->next_review_date)->format('F d, Y') }}</td>
                </tr>
                @endif
                @if($review->message)
                <tr>
                    <th>Message / Notes</th>
                    <td>{{ $review->message }}</td>
                </tr>
                @endif
            </table>
        @else
            <p style="color:#6b7280; font-style:italic;">No review scheduled.</p>
        @endif
    </div>

</div>

@endsection