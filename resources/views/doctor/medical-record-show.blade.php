@extends('layouts.doctor')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/doctor-medical-record-show.css') }}">
@endsection

@section('content')

<div class="records-wrapper">
    @php
        $isWalkIn   = $appointment->walkin_patient_id !== null;
        $patientObj = $isWalkIn ? $appointment->walkinPatient : $appointment->patient;

        $statusMap = [
            'Pending'     => 'badge-pending',
            'Approved'    => 'badge-approved',
            'Completed'   => 'badge-completed',
            'Cancelled'   => 'badge-cancelled',
            'Rejected'    => 'badge-rejected',
            'No Show'     => 'badge-noshow',
            'Rescheduled' => 'badge-rescheduled',
        ];
        $badgeClass = $statusMap[$appointment->status] ?? 'badge-pending';
    @endphp

    {{-- HERO — same banner style as the dashboard --}}
    <div class="page-header">
        <div class="page-header-main">
            <div class="page-header-icon"><i class='bx bx-folder-open'></i></div>
            <div>
                <div class="page-header-title-row">
                    <h1 class="page-title">Medical Record</h1>
                    <span class="badge {{ $badgeClass }}">{{ $appointment->status }}</span>
                </div>
                <p class="page-subtitle">
                    <span><i class='bx bx-user'></i> {{ $patientObj?->first_name ?? '—' }} {{ $patientObj?->last_name ?? '' }}</span>
                    <span><i class='bx bx-calendar'></i> {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</span>
                    <span><i class='bx bx-time-five'></i> {{ $appointment->timeLabel('h:i A') }}</span>
                </p>
            </div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('doctor.prescription.print', $appointment->id) }}"
               target="_blank"
               class="action-btn action-btn--violet">
                <i class='bx bx-printer'></i> Print Prescription
            </a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         LEVEL 2 — RECORD INFORMATION / PATIENT INFORMATION
    ══════════════════════════════════════════════ --}}
    <div class="top-grid">

        {{-- RECORD INFORMATION --}}
        <div class="card">
            <div class="card-header">
                <div class="card-icon"><i class='bx bx-file'></i></div>
                <h2 class="card-title">Record Information</h2>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Reference No.</div>
                    <div class="value">{{ $appointment->reference_no ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Status</div>
                    <div class="value"><span class="badge {{ $badgeClass }}">{{ $appointment->status }}</span></div>
                </div>
                <div class="info-item">
                    <div class="label">Date</div>
                    <div class="value">{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Time</div>
                    <div class="value">{{ $appointment->timeLabel('h:i A') }}</div>
                </div>
            </div>
        </div>

        {{-- PATIENT INFORMATION --}}
        <div class="card">
            <div class="card-header">
                <div class="card-icon"><i class='bx bx-user'></i></div>
                <h2 class="card-title">Patient Information</h2>
                <span class="patient-type-chip {{ $isWalkIn ? 'chip--walkin' : 'chip--registered' }}">
                    {{ $isWalkIn ? 'Walk-in' : 'Registered' }}
                </span>
            </div>
            <div class="patient-hero">
                <div class="patient-avatar">
                    {{ strtoupper(substr($patientObj?->first_name ?? 'P', 0, 1)) }}{{ strtoupper(substr($patientObj?->last_name ?? '', 0, 1)) }}
                </div>
                <div class="patient-name">
                    {{ $patientObj?->first_name ?? '—' }} {{ $patientObj?->last_name ?? '' }}
                </div>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Age</div>
                    <div class="value">{{ $patientObj?->age ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Gender</div>
                    <div class="value">{{ $patientObj?->gender ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Contact Number</div>
                    <div class="value">{{ $patientObj?->contact_number ?? '—' }}</div>
                </div>
                <div class="info-item full-span">
                    <div class="label">Address</div>
                    <div class="value">{{ $patientObj?->address ?? '—' }}</div>
                </div>
            </div>
        </div>

    </div>{{-- end top-grid --}}

    {{-- ══════════════════════════════════════════════
         LEVEL 3 — CLINICAL NOTES
    ══════════════════════════════════════════════ --}}
    <div class="card card--full">
        <div class="card-header">
            <div class="card-icon"><i class='bx bx-notepad'></i></div>
            <h2 class="card-title">Clinical Notes</h2>
        </div>

        @if($medicalRecord)
        <div class="notes-grid">
            <div class="notes-block notes-block--complaint">
                <div class="notes-block-label">Chief Complaint</div>
                <div class="notes-block-content">{{ $medicalRecord->chief_complaint ?? 'No information provided' }}</div>
            </div>
            <div class="notes-block notes-block--hpi">
                <div class="notes-block-label">History of Present Illness</div>
                <div class="notes-block-content">{{ $medicalRecord->history_of_present_illness ?? 'No information provided' }}</div>
            </div>

            <div class="notes-block notes-block--exam">
                <div class="notes-block-label">Physical Examination</div>
                <div class="notes-block-content">{{ $medicalRecord->physical_examination ?? 'No information provided' }}</div>
            </div>
            <div class="notes-block notes-block--diagnosis">
                <div class="notes-block-label">Diagnosis</div>
                <div class="notes-block-content">{{ $medicalRecord->diagnosis ?? 'No information provided' }}</div>
            </div>

            <div class="notes-block notes-block--treatment notes-block--wide">
                <div class="notes-block-label">Treatment</div>
                <div class="notes-block-content">{{ $medicalRecord->treatment ?? 'No information provided' }}</div>
            </div>

            <div class="notes-block notes-block--notes notes-block--wide">
                <div class="notes-block-label">Additional Notes</div>
                <div class="notes-block-content">{{ $medicalRecord->notes ?? 'No information provided' }}</div>
            </div>
        </div>
        @else
        <div class="empty-state">
            <i class='bx bx-file'></i>
            <p>No medical record found for this appointment.</p>
        </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════
         LEVEL 3 — VITAL SIGNS
    ══════════════════════════════════════════════ --}}
    @if($medicalRecord)
    <div class="card card--full">
        <div class="card-header">
            <div class="card-icon"><i class='bx bx-pulse'></i></div>
            <h2 class="card-title">Vital Signs</h2>
        </div>

        @php
            $mrVitals = $medicalRecord->vitalsForDisplay();
            $mrIcons = [
                'blood_pressure'    => ['vital-icon--bp',     'bx-pulse'],
                'temperature'       => ['vital-icon--temp',   'bxs-thermometer'],
                'pulse_rate'        => ['vital-icon--height', 'bx-heart'],
                'respiratory_rate'  => ['vital-icon--height', 'bx-wind'],
                'oxygen_saturation' => ['vital-icon--height', 'bx-droplet'],
                'weight'            => ['vital-icon--weight', 'bx-body'],
                'height'            => ['vital-icon--height', 'bx-ruler'],
            ];
        @endphp
        <div class="vitals-grid">
            @foreach($mrVitals as $key => $row)
            <div class="vital-tile">
                <div class="vital-icon {{ $mrIcons[$key][0] }}">
                    <i class='bx {{ $mrIcons[$key][1] }}'></i>
                </div>
                <div class="vital-data">
                    @if($row['status'] === 'recorded')
                        <div class="vital-val">{{ $row['value'] }}@if($row['unit']) <small>{{ $row['unit'] }}</small>@endif</div>
                    @elseif($row['status'] === 'not_taken')
                        <div class="vital-val">Not taken</div>
                    @else
                        <div class="vital-val">—</div>
                    @endif
                    <div class="vital-label">{{ $row['label'] }}@if($row['status'] === 'not_taken') · {{ $row['reason'] }}@endif</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         LEVEL 3 — FOLLOW-UP REVIEW (kept from original)
    ══════════════════════════════════════════════ --}}
    @if($review)
    <div class="card card--full">
        <div class="card-header">
            <div class="card-icon"><i class='bx bx-calendar-check'></i></div>
            <h2 class="card-title">Follow-up Review</h2>
        </div>
        <div class="followup-block">
            <div class="followup-date">
                <i class='bx bx-calendar'></i>
                Next Review:
                <strong>
                    {{ $review->next_review_date
                        ? \Carbon\Carbon::parse($review->next_review_date)->format('F d, Y')
                        : '—' }}
                </strong>
            </div>
            @if($review->message)
            <div class="followup-message">
                <div class="label">Instructions</div>
                <p>{{ $review->message }}</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════
         LEVEL 3 — PRESCRIPTIONS
    ══════════════════════════════════════════════ --}}
    <div class="card card--full">
        <div class="card-header">
            <div class="card-icon"><i class='bx bx-capsule'></i></div>
            <h2 class="card-title">Prescriptions</h2>
        </div>

        @if($prescriptions->isNotEmpty())
            <div class="table-wrapper">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medicine Name</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Instructions</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prescriptions as $i => $prescription)
                        <tr>
                            <td class="row-num">{{ $i + 1 }}</td>
                            <td class="medicine-name">
                                {{ $prescription->medicine?->medicine_name
                                    ?? $prescription->manual_medicine_name
                                    ?? 'Unknown' }}
                            </td>
                            <td>{{ $prescription->dosage ?? '—' }}</td>
                            <td>{{ $prescription->frequency ?? '—' }}</td>
                            <td>{{ $prescription->duration ?? '—' }}</td>
                            <td>{{ $prescription->instructions ?? '—' }}</td>
                            <td>{{ $prescription->quantity_prescribed ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <i class='bx bx-capsule'></i>
                <p>No prescriptions recorded.</p>
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════
         LEVEL 3 — LABORATORY (summary/history only —
         the full result review workspace stays under
         Doctor Sidebar → Laboratory Results)
    ══════════════════════════════════════════════ --}}
    <div class="card card--full">
        <div class="card-header">
            <div class="card-icon"><i class='bx bxs-flask'></i></div>
            <h2 class="card-title">Laboratory</h2>
        </div>

        @if($laboratoryRequests->isNotEmpty())
            @php
                $labBadgeMap = [
                    'Awaiting Result' => 'labawaiting',
                    'Result Received' => 'labreceived',
                    'Reviewed'         => 'labreviewed',
                ];
            @endphp
            <div class="table-wrapper">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>Reference No.</th>
                            <th>Requested Tests</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($laboratoryRequests as $labReq)
                        <tr>
                            <td>{{ $labReq->reference_no }}</td>
                            <td>{{ $labReq->testsDisplay() }}</td>
                            <td>
                                <span class="status {{ $labBadgeMap[$labReq->status] ?? 'labawaiting' }}">
                                    {{ $labReq->status }}
                                </span>
                            </td>
                            <td>{{ $labReq->requested_at?->format('M d, Y') ?? '—' }}</td>
                            <td>
                                <a href="{{ route('doctor.laboratory-requests.show', $labReq->id) }}" class="btn-view-record">
                                    <i class='bx bx-show'></i> View Laboratory Result
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <i class='bx bxs-flask'></i>
                <p>No laboratory tests requested for this consultation.</p>
            </div>
        @endif
    </div>

</div>

@endsection