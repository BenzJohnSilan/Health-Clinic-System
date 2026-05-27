@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-appointment-show.css') }}">
@endsection

@section('content')

<div class="container">

    {{-- Back Button --}}
    <a href="{{ route('staff.appointments.index') }}" class="back-btn">
        ← Back to Appointments
    </a>

    <div class="page-header">
        <h1 class="page-title">Appointment Details</h1>
    </div>

    @php
        $patient = $appointment->walkinPatient ?? $appointment->patient;
    @endphp

    {{-- ================= PATIENT INFORMATION ================= --}}
    <div class="details-card">

        <div class="card-header">
            <h2>👤 Patient Information</h2>
        </div>

        <div class="details-grid">

            <div class="detail-item">
                <span class="label">Full Name</span>
                <span class="value">{{ $appointment->patientName() }}</span>
            </div>

            <div class="detail-item">
                <span class="label">Age</span>
                <span class="value">
                    {{ $patient->age ?? '-' }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Gender</span>
                <span class="value">
                    {{ $patient->gender ?? '-' }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Contact Number</span>
                <span class="value">
                    {{ $patient->contact_number ?? '-' }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Address</span>
                <span class="value">
                    {{ $patient->address ?? '-' }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Patient Type</span>
                <span class="value">
                    {{ $appointment->walkin_patient_id ? 'Walk-in' : 'Registered' }}
                </span>
            </div>

        </div>

    </div>

    {{-- ================= APPOINTMENT DETAILS ================= --}}
    <div class="details-card">

        <div class="card-header">
            <h2>📅 Appointment Details</h2>
        </div>

        <div class="details-grid">

            <div class="detail-item">
                <span class="label">Reference No.</span>
                <span class="value">
                    {{ $appointment->reference_no ?? '-' }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Appointment Date</span>
                <span class="value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Appointment Time</span>
                <span class="value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Doctor</span>
                <span class="value">
                    Dr. {{ 
                            ($appointment->doctor->first_name ?? '') . ' ' .
                            ($appointment->doctor->last_name ?? '')
                        }}
                </span>
            </div>

            <div class="detail-item">
                <span class="label">Status</span>
                <span class="value">
                    <span class="status {{ str_replace(' ', '-', strtolower($appointment->status)) }}">
                        {{ $appointment->status }}
                    </span>
                </span>
            </div>

            <div class="detail-item full-width">
                <span class="label">Reason for Appointment</span>
                <span class="value">
                    {{ $appointment->reason ?? '-' }}
                </span>
            </div>

        </div>

    </div>

</div>

@endsection