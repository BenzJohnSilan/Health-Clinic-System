@extends('layouts.patient')

@section('head')
{{-- Same stylesheet as the Patient Dashboard / Consultation Summary: hero banner, info-cards, status pills. --}}
<link rel="stylesheet" href="{{ asset('css/patient-dashboard.css') }}">
<style>
    /* Only what the dashboard styles don't already cover. Uses the dashboard's own colour variables. */
    .mc-page .info-card { margin-bottom: 20px; }

    /* 4 equal columns; wider tiles span 2-3 so every row ends flush with the card edge. */
    .mc-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .mc-tile { background: var(--bg-page); border-radius: var(--radius-md); padding: 12px 16px; min-width: 0; }
    .mc-span2 { grid-column: span 2; }
    .mc-span3 { grid-column: span 3; }
    .mc-span-full { grid-column: 1 / -1; }
    .mc-sub { display: block; margin-top: 2px; font-size: 12.5px; font-weight: 600; color: var(--text-light); }
    .mc-label { display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase; color: var(--text-light); margin-bottom: 4px; }
    .mc-value { display: block; font-size: 15px; font-weight: 800; color: var(--text-dark); word-break: break-word; }
    .mc-value--normal { font-weight: 600; font-size: 14px; line-height: 1.6; color: var(--text-mid); white-space: pre-line; }

    .mc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr)); gap: 14px; }
    .mc-panel { background: var(--bg-page); border-radius: var(--radius-md); padding: 14px 18px; min-width: 0; }
    .mc-text { font-size: 14px; line-height: 1.65; color: var(--text-mid); white-space: pre-line; margin: 0; }
    .mc-meta { margin-top: 14px; }

    .info-card-icon--red { background: var(--accent-red-bg); color: var(--accent-red); }

    /* Action buttons (card header, right side) */
    .mc-actions { display: flex; flex-wrap: wrap; gap: 10px; }
    .mc-actions .card-view-btn { cursor: pointer; }
    .mc-btn-solid { background: var(--primary); color: #fff; border-color: var(--primary); }
    .mc-btn-solid:hover { background: #560aaf; border-color: #560aaf; color: #fff; }

    /* Status banners + session alerts */
    .mc-banner { display: flex; align-items: center; gap: 14px; padding: 16px 20px; border-radius: var(--radius-lg); margin-bottom: 20px; font-size: 14px; font-weight: 600; line-height: 1.5; }
    .mc-banner i { flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 19px; }
    .mc-banner--pending { background: var(--primary-soft); color: var(--primary); }
    .mc-banner--pending i { background: #fff; color: var(--primary); }
    .mc-banner--success { background: var(--accent-green-bg); color: var(--accent-green); }
    .mc-banner--success i { background: #fff; color: var(--accent-green); }
    .mc-banner--error { background: var(--accent-red-bg); color: var(--accent-red); }
    .mc-banner--error i { background: #fff; color: var(--accent-red); }

    /* Correction modal */
    .mc-modal { display: none; position: fixed; inset: 0; z-index: 2000; align-items: center; justify-content: center; padding: 20px; background: rgba(26, 26, 46, 0.55); }
    .mc-modal.show { display: flex; }
    .mc-modal-box { position: relative; width: 100%; max-width: 480px; background: #fff; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 28px; }
    .mc-modal-close { position: absolute; top: 14px; right: 16px; border: none; background: none; font-size: 26px; line-height: 1; color: var(--text-light); cursor: pointer; }
    .mc-modal-close:hover { color: var(--text-dark); }
    .mc-modal-title { font-size: 18px; font-weight: 800; color: var(--text-dark); margin-bottom: 18px; }
    .mc-modal label { display: block; font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; }
    .mc-modal label .required { color: var(--accent-red); }
    .mc-modal textarea { width: 100%; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; font-size: 14px; color: var(--text-dark); resize: vertical; outline: none; transition: border-color var(--transition); }
    .mc-modal textarea:focus { border-color: var(--primary); }
    .mc-field-error { display: block; margin-top: 6px; font-size: 12.5px; font-weight: 600; color: var(--accent-red); }
    .mc-modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    .mc-modal-actions .card-view-btn { cursor: pointer; }

    @media (max-width: 1100px) {
        .mc-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .mc-span3 { grid-column: 1 / -1; }
    }
    @media (max-width: 560px) {
        .mc-tiles { grid-template-columns: 1fr; }
        .mc-span2, .mc-span3, .mc-span-full { grid-column: auto; }
        .mc-actions { width: 100%; }
        .mc-actions .card-view-btn { flex: 1; justify-content: center; }
    }
</style>
@endsection

@section('content')

@php
    $certNo    = $medicalCertificate->certificate_number;
    $physician = $medicalCertificate->doctor ?? optional($medicalCertificate->appointment)->doctor;
    $medicalCertificate->loadMissing('invoice');
@endphp

{{-- ================= HERO (same banner as the Dashboard) ================= --}}
<div class="hero-wrapper">
    <div class="dashboard-hero">
        <div class="hero-greeting-group">
            <div class="hero-icon-badge"><i class='bx bx-file'></i></div>
            <div class="hero-greeting">
                <h2 class="greeting-text">Medical Certificate</h2>
                <p class="greeting-subtitle">{{ $certNo }}</p>
            </div>
        </div>

        <div class="mc-actions">
            @if($medicalCertificate->isPaid())
                <a href="{{ route('patient.medical-certificates.print', $medicalCertificate->id) }}"
                   target="_blank" class="hero-cta-btn">
                    <i class='bx bx-printer'></i> Print / Save Copy
                </a>
            @endif
            <a href="{{ route('patient.medical-certificates.index') }}" class="hero-cta-btn">
                <i class='bx bx-arrow-back'></i> Back to Medical Certificates
            </a>
        </div>
    </div>
</div>

<div class="mc-page">

    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--purple"><i class='bx bx-id-card'></i></span>
                <h3>Certificate Details</h3>
            </div>
            <span class="status-pill status-pill--green"><i class='bx bx-check-circle'></i> Released</span>
        </div>

        <div class="info-card-body">
            <div class="mc-tiles">
                <div class="mc-tile">
                    <span class="mc-label">Certificate No.</span>
                    <span class="mc-value">{{ $certNo }}</span>
                </div>
                <div class="mc-tile">
                    <span class="mc-label">Date Examined</span>
                    <span class="mc-value">
                        {{ optional($medicalCertificate->appointment)->appointment_date
                            ? \Carbon\Carbon::parse($medicalCertificate->appointment->appointment_date)->format('F d, Y')
                            : '—' }}
                    </span>
                </div>
                <div class="mc-tile">
                    <span class="mc-label">Attending Physician</span>
                    <span class="mc-value">
                        {{ $physician ? 'Dr. ' . $physician->first_name . ' ' . $physician->last_name : '—' }}
                    </span>
                </div>
                <div class="mc-tile">
                    <span class="mc-label">Purpose</span>
                    <span class="mc-value">{{ $medicalCertificate->displayPurpose() }}</span>
                </div>
                <div class="mc-tile">
                    <span class="mc-label">Released On</span>
                    <span class="mc-value">{{ $medicalCertificate->released_at?->format('F d, Y') ?? '—' }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--green"><i class='bx bx-plus-medical'></i></span>
                <h3>Medical Details</h3>
            </div>
        </div>

        <div class="info-card-body">
            <div class="mc-grid">
                <div class="mc-panel">
                    <span class="mc-label">Final Diagnosis</span>
                    <p class="mc-text">{{ $medicalCertificate->medical_statement }}</p>
                </div>
                <div class="mc-panel">
                    <span class="mc-label">Remarks</span>
                    <p class="mc-text">{{ $medicalCertificate->recommendation }}</p>
                </div>
            </div>

            @if(($medicalCertificate->rest_start_date && $medicalCertificate->rest_end_date) || $medicalCertificate->additional_remarks)
            <div class="mc-tiles mc-meta">
                @if($medicalCertificate->rest_start_date && $medicalCertificate->rest_end_date)
                <div class="mc-tile mc-span2">
                    <span class="mc-label">Rest Period</span>
                    <span class="mc-value">
                        {{ $medicalCertificate->rest_start_date->format('F d, Y') }}
                        to
                        {{ $medicalCertificate->rest_end_date->format('F d, Y') }}
                    </span>
                </div>
                @endif
                @if($medicalCertificate->additional_remarks)
                <div class="mc-tile {{ ($medicalCertificate->rest_start_date && $medicalCertificate->rest_end_date) ? 'mc-span2' : 'mc-span-full' }}">
                    <span class="mc-label">Additional Remarks</span>
                    <span class="mc-value mc-value--normal">{{ $medicalCertificate->additional_remarks }}</span>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    <p class="mc-text" style="color:#6b7280;">
        @if($medicalCertificate->isPaid())
            You can print or save a copy of this certificate. The official certificate is still the signed paper copy released to you by the clinic.
        @else
            You can print or save a copy once the invoice for this certificate is fully paid. The official certificate is the signed paper copy released to you by the clinic.
        @endif
    </p>

</div>

@endsection