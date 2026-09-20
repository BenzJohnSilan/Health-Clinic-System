@extends('layouts.patient')

@section('head')
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/patient-appointments.css') }}">
<link rel="stylesheet" href="{{ asset('css/patient-appointment-details.css') }}">
@endsection

@section('content')

<div class="ad-wrapper">

    {{-- ================= BACK ROW (own row, above header) ================= --}}
    <div class="ad-back-row">
        <a href="{{ route('patient.appointments.index') }}" class="ad-btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Appointments
        </a>
    </div>

    {{-- ================= PAGE HEADER ================= --}}
    <div class="ad-page-header">
        <div class="ad-page-header-text">
            <h1>Appointment Details</h1>
            <span class="ref-no">{{ $appointment->reference_no ?? '—' }}</span>
        </div>

        <span class="status {{ strtolower($appointment->status) }}">
            {{ $appointment->status }}
        </span>
    </div>

    {{-- ================= APPOINTMENT INFORMATION ================= --}}
    <div class="ad-card">
        <div class="ad-card-heading">Appointment Information</div>
        <div class="ad-grid ad-grid-3">
            <div class="ad-field">
                <span class="ad-label">Doctor</span>
                <span class="ad-value">
                    {{ $appointment->doctor->first_name ?? 'N/A' }}
                    {{ $appointment->doctor->last_name ?? '' }}
                </span>
            </div>
            <div class="ad-field">
                <span class="ad-label">Date</span>
                <span class="ad-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                </span>
            </div>
            <div class="ad-field">
                <span class="ad-label">Time</span>
                <span class="ad-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                </span>
            </div>
        </div>
    </div>

    {{-- ================= REASON / NOTES ================= --}}
    <div class="ad-card">
        <div class="ad-card-heading">Reason / Notes</div>
        <div class="ad-text-box">{{ $appointment->reason }}</div>
    </div>

    @if($appointment->status === 'Rejected')
        {{-- ================= REASON FOR REJECTION ================= --}}
        <div class="ad-card">
            <div class="ad-card-heading">Reason for Rejection</div>
            <div class="ad-text-box">{{ $appointment->rejection_reason ?? '-' }}</div>
        </div>
    @endif

</div>

@endsection