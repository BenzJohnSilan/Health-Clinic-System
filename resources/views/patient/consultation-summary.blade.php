@extends('layouts.patient')

@section('head')
{{-- Same stylesheet as the Patient Dashboard: hero banner, info-cards, status pills, card buttons. --}}
<link rel="stylesheet" href="{{ asset('css/patient-dashboard.css') }}">
<style>
    /* Only what the dashboard styles don't already cover (text blocks, vitals, tables, lab block).
       Uses the dashboard's own colour variables so it stays in the same palette. */
    .cs-page .info-card { margin-bottom: 20px; }
    .cs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: 20px; margin-bottom: 20px; }
    .cs-grid .info-card { margin-bottom: 0; }

    .cs-text { font-size: 14px; line-height: 1.65; color: var(--text-mid); white-space: pre-line; margin: 0; }

    .cs-vitals { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
    .cs-vital { background: var(--bg-page); border-radius: var(--radius-md); padding: 12px 16px; }
    .cs-vital-label { display: block; font-size: 11px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase; color: var(--text-light); margin-bottom: 4px; }
    .cs-vital-value { display: block; font-size: 15.5px; font-weight: 800; color: var(--text-dark); }

    .cs-table-wrap { width: 100%; overflow-x: auto; }
    .cs-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .cs-table thead th { background: var(--primary-soft); color: var(--primary); text-align: left; font-size: 11.5px; font-weight: 800; letter-spacing: 0.4px; text-transform: uppercase; padding: 10px 14px; white-space: nowrap; }
    .cs-table thead th:first-child { border-radius: var(--radius-sm) 0 0 var(--radius-sm); }
    .cs-table thead th:last-child  { border-radius: 0 var(--radius-sm) var(--radius-sm) 0; }
    .cs-table tbody td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text-mid); vertical-align: top; }
    .cs-table tbody tr:last-child td { border-bottom: none; }
    .cs-table tbody td strong { color: var(--text-dark); font-weight: 700; }

    .cs-lab { border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px 18px; margin-bottom: 14px; }
    .cs-lab:last-of-type { margin-bottom: 0; }
    .cs-lab-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .cs-lab-ref { font-size: 14px; font-weight: 800; color: var(--text-dark); }
    .cs-lab-date { font-size: 12.5px; color: var(--text-light); margin-left: 8px; }
    .cs-lab-reviewed { font-size: 12.5px; color: var(--text-light); margin-top: 12px; }
    .cs-muted { font-size: 13px; color: var(--text-light); }
    .cs-sublabel { font-size: 11px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase; color: var(--text-light); margin: 16px 0 6px; }
    .cs-file-link { color: var(--primary); font-weight: 700; text-decoration: none; }
    .cs-file-link:hover { text-decoration: underline; }
    .cs-file-name { font-size: 12px; color: var(--text-xlight); margin-left: 6px; }

    /* ---- Appointment details: label / value list ---- */
    .cs-detail-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px 24px; }
    .cs-detail { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
    .cs-detail-label { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; color: var(--text-light); }
    .cs-detail-label i { font-size: 14px; color: var(--primary); }
    .cs-detail-value { font-size: 14.5px; font-weight: 600; color: var(--text-dark); overflow-wrap: anywhere; }

    /* ================= PHONES =================
       Tables become stacked cards (no sideways scrolling), vitals sit in
       two tidy columns, and the spacing matches the other patient pages. */
    @media (max-width: 640px) {
        .cs-page .info-card { margin-bottom: 14px; }
        .cs-grid { gap: 14px; margin-bottom: 14px; }

        /* Appointment details: one "LABEL   value" row per line */
        .cs-detail-list { grid-template-columns: 1fr; gap: 0; }
        .cs-detail {
            display: grid;
            grid-template-columns: 112px minmax(0, 1fr);
            align-items: start;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .cs-detail:first-child { padding-top: 0; }
        .cs-detail:last-child { border-bottom: none; padding-bottom: 0; }
        .cs-detail-label { padding-top: 2px; font-size: 10.5px; }
        .cs-detail-value { font-size: 14px; line-height: 1.45; }

        .cs-text { font-size: 14px; line-height: 1.6; overflow-wrap: anywhere; }

        /* Vital signs: always 2 columns */
        .cs-vitals { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .cs-vital { padding: 10px 12px; }
        .cs-vital-label { font-size: 10px; letter-spacing: 0.4px; }
        .cs-vital-value { font-size: 15px; overflow-wrap: anywhere; }

        /* Prescription + lab tables -> one card per row */
        .cs-table-wrap { overflow: visible; }
        .cs-table, .cs-table tbody, .cs-table tr, .cs-table td { display: block; width: 100%; }
        .cs-table thead { display: none; }

        .cs-table tbody tr {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 4px 14px;
            margin-bottom: 10px;
        }
        .cs-table tbody tr:last-child { margin-bottom: 0; }

        .cs-table tbody td {
            display: grid;
            grid-template-columns: 104px minmax(0, 1fr);
            align-items: start;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid var(--border);
            overflow-wrap: anywhere;
        }
        .cs-table tbody tr td:last-child { border-bottom: none; }

        .cs-table tbody td::before {
            content: attr(data-label);
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: var(--text-light);
            padding-top: 2px;
            overflow-wrap: normal;
            word-break: normal;
        }

        /* The medicine / test name is the card's title, so it has no label */
        .cs-table tbody td[data-label="Medicine"],
        .cs-table tbody td[data-label="Requested Test"] {
            display: block;
            padding: 12px 0 10px;
            font-size: 14.5px;
        }
        .cs-table tbody td[data-label="Medicine"]::before,
        .cs-table tbody td[data-label="Requested Test"]::before { display: none; }

        .cs-file-name { display: block; margin: 2px 0 0; }

        /* Card header that has a button (Prescription): icon + title and the
           "View Prescription" button stay on the SAME row. The button is
           compact (not full width) so the title is never covered. */
        .cs-page .info-card-header--with-btn { flex-wrap: nowrap; align-items: center; gap: 10px; }
        .cs-page .info-card-header--with-btn .info-card-title { flex: 1 1 auto; min-width: 0; }
        .cs-page .info-card-header--with-btn .card-view-btn {
            flex: 0 0 auto;
            width: auto;
            margin: 0;
            padding: 8px 12px;
            font-size: 12.5px;
            white-space: nowrap;
        }

        /* Laboratory request blocks */
        .cs-lab { padding: 14px; margin-bottom: 12px; }
        .cs-lab-head { flex-direction: column; align-items: flex-start; gap: 8px; }
        .cs-lab-date { display: block; margin: 2px 0 0; }
    }

    /* Very small phones (320px class): not enough room for title + button on
       one row, so the button goes under the title at full width. */
    @media (max-width: 359px) {
        .cs-page .info-card-header--with-btn { flex-wrap: wrap; }
        .cs-page .info-card-header--with-btn .card-view-btn { flex: 1 1 100%; width: 100%; }
    }
</style>
@endsection

@section('content')

@php
    $doctor     = $appointment->doctor;
    $doctorName = trim(($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? ''));
    $visitDate  = \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y');

    $hasReason    = filled($appointment->reason);
    $hasComplaint = filled($medicalRecord?->chief_complaint);
    $hasDiagnosis = filled($medicalRecord?->diagnosis);
    $hasTreatment = filled($medicalRecord?->treatment);
    $hasNotes     = filled($medicalRecord?->notes);
    $hasFollowUp  = $followUpDate || $followUpMessage;
@endphp

{{-- ================= HERO (same banner as the Dashboard) ================= --}}
<div class="hero-wrapper">
    <div class="dashboard-hero">
        <div class="hero-greeting-group">
            <div class="hero-icon-badge"><i class='bx bx-file'></i></div>
            <div class="hero-greeting">
                <h2 class="greeting-text">Consultation Summary</h2>
                <p class="greeting-subtitle">{{ $visitDate }}{{ $doctorName !== '' ? ' · Dr. ' . $doctorName : '' }}</p>
            </div>
        </div>

        <a href="{{ route('patient.appointment.history') }}" class="hero-cta-btn">
            <i class='bx bx-arrow-back'></i> Back to Appointment History
        </a>
    </div>
</div>

<div class="cs-page">

    {{-- ================= APPOINTMENT DETAILS ================= --}}
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--purple"><i class='bx bx-calendar-check'></i></span>
                <h3>Appointment Details</h3>
            </div>
            <span class="status-pill status-pill--green"><i class='bx bx-check-circle'></i> {{ $appointment->status }}</span>
        </div>
        <div class="info-card-body">
            <div class="cs-detail-list">
                <div class="cs-detail">
                    <span class="cs-detail-label"><i class='bx bx-id-card'></i> Reference No.</span>
                    <span class="cs-detail-value">{{ $appointment->reference_no ?? '—' }}</span>
                </div>
                <div class="cs-detail">
                    <span class="cs-detail-label"><i class='bx bx-calendar'></i> Date</span>
                    <span class="cs-detail-value">{{ $visitDate }}</span>
                </div>
                <div class="cs-detail">
                    <span class="cs-detail-label"><i class='bx bx-time-five'></i> Time</span>
                    <span class="cs-detail-value">{{ $appointment->timeLabel('h:i A') }}</span>
                </div>
                <div class="cs-detail">
                    <span class="cs-detail-label"><i class='bx bx-user'></i> Doctor</span>
                    <span class="cs-detail-value">Dr. {{ $doctor->first_name ?? '' }} {{ $doctor->last_name ?? '' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= REASON FOR VISIT + CHIEF COMPLAINT ================= --}}
    @if($hasReason || $hasComplaint)
    <div class="cs-grid">
        @if($hasReason)
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <span class="info-card-icon info-card-icon--blue"><i class='bx bx-clipboard'></i></span>
                    <h3>Reason for Visit</h3>
                </div>
            </div>
            <div class="info-card-body"><p class="cs-text">{{ $appointment->reason }}</p></div>
        </div>
        @endif
        @if($hasComplaint)
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <span class="info-card-icon info-card-icon--blue"><i class='bx bx-message-square-detail'></i></span>
                    <h3>Chief Complaint</h3>
                </div>
            </div>
            <div class="info-card-body"><p class="cs-text">{{ $medicalRecord->chief_complaint }}</p></div>
        </div>
        @endif
    </div>
    @endif

    {{-- ================= VITAL SIGNS ================= --}}
    @if($vitals->isNotEmpty())
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--green"><i class='bx bx-heart-circle'></i></span>
                <h3>Vital Signs</h3>
            </div>
        </div>
        <div class="info-card-body">
            <div class="cs-vitals">
                @foreach($vitals as $vital)
                    <div class="cs-vital">
                        <span class="cs-vital-label">{{ $vital['label'] }}</span>
                        <span class="cs-vital-value">{{ $vital['value'] }}{{ $vital['unit'] ? ' ' . $vital['unit'] : '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ================= ASSESSMENT / DIAGNOSIS + TREATMENT / PLAN ================= --}}
    @if($hasDiagnosis || $hasTreatment)
    <div class="cs-grid">
        @if($hasDiagnosis)
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <span class="info-card-icon info-card-icon--purple"><i class='bx bx-file'></i></span>
                    <h3>Assessment / Diagnosis</h3>
                </div>
            </div>
            <div class="info-card-body"><p class="cs-text">{{ $medicalRecord->diagnosis }}</p></div>
        </div>
        @endif
        @if($hasTreatment)
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <span class="info-card-icon info-card-icon--purple"><i class='bx bx-plus-medical'></i></span>
                    <h3>Treatment / Plan</h3>
                </div>
            </div>
            <div class="info-card-body"><p class="cs-text">{{ $medicalRecord->treatment }}</p></div>
        </div>
        @endif
    </div>
    @endif

    {{-- ================= NOTES ================= --}}
    @if($hasNotes)
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--purple"><i class='bx bx-message-square-detail'></i></span>
                <h3>Notes</h3>
            </div>
        </div>
        <div class="info-card-body"><p class="cs-text">{{ $medicalRecord->notes }}</p></div>
    </div>
    @endif

    {{-- ================= PRESCRIPTION ================= --}}
    @if($appointment->prescriptions->isNotEmpty())
    @php $hasRxInstructions = $appointment->prescriptions->contains(fn ($rx) => filled($rx->instructions)); @endphp
    <div class="info-card">
        <div class="info-card-header info-card-header--with-btn">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--purple"><i class='bx bx-receipt'></i></span>
                <h3>Prescription</h3>
            </div>
            {{-- Standalone, printable page: opens in its own tab, no Patient sidebar. --}}
            <a href="{{ route('patient.prescription.show', $appointment->id) }}"
               target="_blank" rel="noopener noreferrer"
               class="card-view-btn">
                View Prescription <i class='bx bx-chevron-right'></i>
            </a>
        </div>

        <div class="info-card-body">
            <div class="cs-table-wrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Quantity</th>
                            @if($hasRxInstructions)<th>Instructions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appointment->prescriptions as $prescription)
                        <tr>
                            <td data-label="Medicine">
                                <strong>
                                    {{ $prescription->medicine?->medicine_name
                                        ?? $prescription->manual_medicine_name
                                        ?? 'N/A' }}
                                </strong>
                            </td>
                            <td data-label="Dosage">{{ $prescription->dosage ?? '—' }}</td>
                            <td data-label="Frequency">{{ $prescription->frequency ?? '—' }}</td>
                            <td data-label="Duration">{{ $prescription->duration ?? '—' }}</td>
                            <td data-label="Quantity">{{ $prescription->quantity_prescribed ?? '—' }}</td>
                            @if($hasRxInstructions)<td data-label="Instructions">{{ $prescription->instructions ?: '—' }}</td>@endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ================= LABORATORY ================= --}}
    @if($laboratoryRequests->isNotEmpty())
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--purple"><i class='bx bxs-flask'></i></span>
                <h3>Laboratory</h3>
            </div>
        </div>

        <div class="info-card-body">
            @foreach($laboratoryRequests as $lab)
                @php
                    $labRequest = $lab['request'];
                    // Same colour mapping as the Dashboard's "Latest Laboratory Result" card.
                    $labPillClass = match($lab['status_label']) {
                        'Reviewed'          => 'status-pill--green',
                        'Correction Needed' => 'status-pill--red',
                        'Result Received'   => 'status-pill--blue',
                        default             => 'status-pill--amber',
                    };
                @endphp
                <div class="cs-lab">
                    <div class="cs-lab-head">
                        <div>
                            <span class="cs-lab-ref">{{ $labRequest->reference_no }}</span>
                            @if($labRequest->requested_at)
                                <span class="cs-lab-date">Requested {{ $labRequest->requested_at->format('M j, Y') }}</span>
                            @endif
                        </div>
                        <span class="status-pill {{ $labPillClass }}">{{ $lab['status_label'] }}</span>
                    </div>

                    @if($lab['tests']->isNotEmpty())
                    <div class="cs-table-wrap">
                        <table class="cs-table">
                            <thead>
                                <tr>
                                    <th>Requested Test</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lab['tests'] as $test)
                                <tr>
                                    <td data-label="Requested Test"><strong>{{ $test['name'] }}</strong></td>
                                    <td data-label="Result">
                                        @if($test['file'])
                                            <a href="{{ $test['file']->url }}" target="_blank" rel="noopener noreferrer" class="cs-file-link">View result</a>
                                            <span class="cs-file-name">{{ $test['file']->original_filename }}</span>
                                        @else
                                            <span class="cs-muted">No result uploaded yet</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if($lab['review_notes'])
                        <div class="cs-sublabel">Physician's Review</div>
                        <p class="cs-text">{{ $lab['review_notes'] }}</p>
                    @endif

                    @if($labRequest->isReviewed() && $labRequest->reviewed_at)
                        <div class="cs-lab-reviewed">
                            Reviewed {{ $labRequest->reviewed_at->format('M j, Y') }}
                            @if($labRequest->reviewedBy)
                                by Dr. {{ $labRequest->reviewedBy->first_name }} {{ $labRequest->reviewedBy->last_name }}
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="info-card-footer">
            <a href="{{ route('patient.laboratory-results.index') }}" class="card-view-btn">
                Open Laboratory Results <i class='bx bx-chevron-right'></i>
            </a>
        </div>
    </div>
    @endif

    {{-- ================= FOLLOW-UP INSTRUCTIONS ================= --}}
    @if($hasFollowUp)
    <div class="info-card">
        <div class="info-card-header">
            <div class="info-card-title">
                <span class="info-card-icon info-card-icon--purple"><i class='bx bx-calendar-plus'></i></span>
                <h3>Follow-up Instructions</h3>
            </div>
        </div>
        <div class="info-card-body">
            @if($followUpDate)
                <div class="followup-row" style="margin-bottom: {{ $followUpMessage ? '12px' : '0' }};">
                    <span class="followup-icon"><i class='bx bx-calendar'></i></span>
                    <span class="info-card-main-text" style="margin:0;">Follow-up on {{ $followUpDate }}</span>
                </div>
            @endif
            @if($followUpMessage)
                <p class="cs-text">{{ $followUpMessage }}</p>
            @endif
        </div>
    </div>
    @endif

</div>

@endsection