@extends('layouts.patient')

@section('head')
{{-- Same stylesheet as the Patient Dashboard / Consultation Summary: hero banner, info-cards, status pills. --}}
<link rel="stylesheet" href="{{ asset('css/patient-dashboard.css') }}">
<style>
    /* Only what the dashboard styles don't already cover. Uses the dashboard's own colour variables. */
    .ad-page .info-card { margin-bottom: 20px; }

    .ad-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 12px; }
    .ad-tile { background: var(--bg-page); border-radius: var(--radius-md); padding: 12px 16px; min-width: 0; }
    .ad-tile-label { display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase; color: var(--text-light); margin-bottom: 4px; }
    .ad-tile-value { display: block; font-size: 15px; font-weight: 800; color: var(--text-dark); word-break: break-word; }
    .ad-tile-value a { color: var(--primary); text-decoration: none; }
    .ad-tile-value a:hover { text-decoration: underline; }

    .ad-text { font-size: 14px; line-height: 1.65; color: var(--text-mid); white-space: pre-line; margin: 0; }

    .info-card-icon--red { background: var(--accent-red-bg); color: var(--accent-red); }
</style>
@endsection

@section('content')

@php
    $doctor     = $appointment->doctor;
    $doctorName = trim(($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? ''));

    // Same colour language as the Dashboard / Consultation Summary status pills.
    [$pillClass, $pillIcon] = match($appointment->status) {
        'Approved', 'Completed'      => ['status-pill--green', 'bx-check-circle'],
        'Pending', 'Rescheduled'     => ['status-pill--amber', 'bx-time-five'],
        'Checked In', 'In Progress'  => ['status-pill--blue',  'bx-info-circle'],
        'Rejected', 'No Show'        => ['status-pill--red',   'bx-x-circle'],
        default                      => ['status-pill--gray',  'bx-x-circle'],   // Cancelled
    };
@endphp

{{-- ================= HERO (same banner as the Dashboard) ================= --}}
<div class="hero-wrapper">
    <div class="dashboard-hero">
        <div class="hero-greeting-group">
            <div class="hero-icon-badge"><i class='bx bx-calendar-check'></i></div>
            <div class="hero-greeting">
                <h2 class="greeting-text">Appointment Details</h2>
                <p class="greeting-subtitle">{{ $appointment->reference_no ?? '—' }}</p>
            </div>
        </div>

        <a href="{{ route('patient.appointments.index') }}" class="hero-cta-btn">
            <i class='bx bx-arrow-back'></i> Back to Appointments
        </a>
    </div>
</div>

<div class="ad-page">

    {{-- ================= APPOINTMENT INFORMATION ================= --}}
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--purple"><i class='bx bx-calendar'></i></span>
                <h3>Appointment Information</h3>
            </div>
            <span class="status-pill {{ $pillClass }}"><i class='bx {{ $pillIcon }}'></i> {{ $appointment->status }}</span>
        </div>

        <div class="info-card-body">
            <div class="ad-tiles">
                <div class="ad-tile">
                    <span class="ad-tile-label">Doctor</span>
                    <span class="ad-tile-value">{{ $doctorName !== '' ? 'Dr. ' . $doctorName : 'N/A' }}</span>
                </div>
                <div class="ad-tile">
                    <span class="ad-tile-label">Date</span>
                    <span class="ad-tile-value">{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</span>
                </div>
                <div class="ad-tile">
                    <span class="ad-tile-label">Time</span>
                    <span class="ad-tile-value">{{ $appointment->timeLabel('h:i A') }}</span>
                </div>
                <div class="ad-tile">
                    <span class="ad-tile-label">Consultation Type</span>
                    <span class="ad-tile-value">{{ $appointment->isFollowUp() ? 'Follow-up Consultation' : 'Regular Consultation' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= REASON / NOTES ================= --}}
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--blue"><i class='bx bx-clipboard'></i></span>
                <h3>Reason / Notes</h3>
            </div>
        </div>
        <div class="info-card-body"><p class="ad-text">{{ filled($appointment->reason) ? $appointment->reason : '—' }}</p></div>
    </div>

    @if($appointment->status === 'Rejected')
        {{-- ================= REASON FOR REJECTION ================= --}}
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <span class="info-card-icon info-card-icon--red"><i class='bx bx-x-circle'></i></span>
                    <h3>Reason for Rejection</h3>
                </div>
            </div>
            <div class="info-card-body"><p class="ad-text">{{ $appointment->rejection_reason ?? '-' }}</p></div>
        </div>
    @endif

</div>

@endsection