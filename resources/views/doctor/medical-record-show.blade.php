@extends('layouts.doctor')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/doctor-medical-record-show.css') }}">
@endsection

@section('content')

<div class="records-wrapper">
    @php
        $isWalkIn   = $appointment->walkin_patient_id !== null;
        $patientObj = $isWalkIn ? $appointment->walkinPatient : $appointment->patient;
    @endphp

    {{-- ── PAGE HEADER ── --}}
    <div class="page-header">
        <div class="page-header-left">
            <a href="{{ route('doctor.medical-records.index') }}" class="back-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Records
            </a>
            <div class="page-title-group">
                <h1 class="page-title">Medical Record</h1>
                @if($isWalkIn)
                    <span class="walkin-tag">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                        Walk-in
                    </span>
                @endif
            </div>
        </div>
        <div class="page-header-actions">
            <a href="{{ route('doctor.prescription.print', $appointment->id) }}"
               target="_blank"
               class="action-btn action-btn--blue">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Prescription
            </a>
            <a href="{{ route('doctor.medical-certificate.print', $appointment->id) }}"
               target="_blank"
               class="action-btn action-btn--violet">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Medical Certificate
            </a>
        </div>
    </div>

    {{-- ── REFERENCE RIBBON ── --}}
    <div class="ref-ribbon">
        <div class="ref-ribbon-item">
            <span class="ref-ribbon-label">Reference No.</span>
            <span class="ref-ribbon-value">{{ $appointment->reference_no ?? '—' }}</span>
        </div>
        <div class="ref-ribbon-divider"></div>
        <div class="ref-ribbon-item">
            <span class="ref-ribbon-label">Date</span>
            <span class="ref-ribbon-value">{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</span>
        </div>
        <div class="ref-ribbon-divider"></div>
        <div class="ref-ribbon-item">
            <span class="ref-ribbon-label">Time</span>
            <span class="ref-ribbon-value">{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</span>
        </div>
        <div class="ref-ribbon-divider"></div>
        <div class="ref-ribbon-item">
            <span class="ref-ribbon-label">Status</span>
            @php
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
            <span class="badge {{ $badgeClass }}">{{ $appointment->status }}</span>
        </div>
    </div>

    {{-- ── BODY GRID ── --}}
    <div class="body-grid">

        {{-- LEFT COLUMN --}}
        <div class="col-left">

            {{-- PATIENT CARD --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-icon card-icon--violet">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <h2 class="card-title">Patient Information</h2>
                    <span class="patient-type-chip {{ $isWalkIn ? 'chip--walkin' : 'chip--registered' }}">
                        {{ $isWalkIn ? 'Walk-in' : 'Registered' }}
                    </span>
                </div>

                <div class="patient-hero">
                    <div class="patient-avatar">
                        {{ strtoupper(substr($patientObj?->first_name ?? 'P', 0, 1)) }}{{ strtoupper(substr($patientObj?->last_name ?? '', 0, 1)) }}
                    </div>
                    <div class="patient-hero-info">
                        <div class="patient-name">
                            {{ $patientObj?->first_name ?? '—' }} {{ $patientObj?->last_name ?? '' }}
                        </div>
                        @if(!$isWalkIn)
                        <div class="patient-email">{{ $patientObj?->email ?? '—' }}</div>
                        @endif
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
                        <div class="label">Contact</div>
                        <div class="value">{{ $patientObj?->contact_number ?? '—' }}</div>
                    </div>
                    <div class="info-item full-span">
                        <div class="label">Address</div>
                        <div class="value">{{ $patientObj?->address ?? '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- VITALS CARD --}}
            @if($medicalRecord)
            <div class="card">
                <div class="card-header">
                    <div class="card-icon card-icon--red">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    </div>
                    <h2 class="card-title">Vital Signs</h2>
                </div>

                <div class="vitals-grid">
                    <div class="vital-tile">
                        <div class="vital-icon vital-icon--bp">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        </div>
                        <div class="vital-data">
                            <div class="vital-val">{{ $medicalRecord->blood_pressure ?? '—' }}</div>
                            <div class="vital-label">Blood Pressure</div>
                        </div>
                    </div>
                    <div class="vital-tile">
                        <div class="vital-icon vital-icon--temp">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
                        </div>
                        <div class="vital-data">
                            <div class="vital-val">{{ $medicalRecord->temperature ?? '—' }}</div>
                            <div class="vital-label">Temperature</div>
                        </div>
                    </div>
                    <div class="vital-tile">
                        <div class="vital-icon vital-icon--weight">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"/><path d="M6.5 8a2 2 0 0 0-1.905 1.46L2.1 18.5A2 2 0 0 0 4 21h16a2 2 0 0 0 1.9-2.54L19.4 9.46A2 2 0 0 0 17.5 8z"/></svg>
                        </div>
                        <div class="vital-data">
                            <div class="vital-val">{{ $medicalRecord->weight ?? '—' }}</div>
                            <div class="vital-label">Weight</div>
                        </div>
                    </div>
                    <div class="vital-tile">
                        <div class="vital-icon vital-icon--height">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="17 7 12 2 7 7"/><polyline points="17 17 12 22 7 17"/></svg>
                        </div>
                        <div class="vital-data">
                            <div class="vital-val">{{ $medicalRecord->height ?? '—' }}</div>
                            <div class="vital-label">Height</div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- FOLLOW-UP CARD --}}
            @if($review)
            <div class="card">
                <div class="card-header">
                    <div class="card-icon card-icon--teal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </div>
                    <h2 class="card-title">Follow-up Review</h2>
                </div>
                <div class="followup-block">
                    <div class="followup-date">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
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

        </div>{{-- end col-left --}}

        {{-- RIGHT COLUMN --}}
        <div class="col-right">

            {{-- CLINICAL NOTES CARD --}}
            @if($medicalRecord)
            <div class="card">
                <div class="card-header">
                    <div class="card-icon card-icon--blue">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <h2 class="card-title">Clinical Notes</h2>
                </div>

                <div class="notes-stack">
                    <div class="notes-block notes-block--complaint">
                        <div class="notes-block-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                            Chief Complaint
                        </div>
                        <div class="notes-block-content">{{ $medicalRecord->chief_complaint ?? '—' }}</div>
                    </div>
                    <div class="notes-block notes-block--diagnosis">
                        <div class="notes-block-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                            Diagnosis
                        </div>
                        <div class="notes-block-content">{{ $medicalRecord->diagnosis ?? '—' }}</div>
                    </div>
                    <div class="notes-block notes-block--treatment">
                        <div class="notes-block-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                            Treatment
                        </div>
                        <div class="notes-block-content">{{ $medicalRecord->treatment ?? '—' }}</div>
                    </div>
                    @if($medicalRecord->notes)
                    <div class="notes-block notes-block--notes">
                        <div class="notes-block-label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                            Additional Notes
                        </div>
                        <div class="notes-block-content">{{ $medicalRecord->notes }}</div>
                    </div>
                    @endif
                </div>
            </div>
            @else
            <div class="card">
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <p>No medical record found for this appointment.</p>
                </div>
            </div>
            @endif

            {{-- PRESCRIPTIONS CARD --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-icon card-icon--green">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                    </div>
                    <h2 class="card-title">Prescriptions</h2>
                </div>

                @if($prescriptions->isNotEmpty())
                    <div class="table-wrapper">
                        <table class="record-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Medicine</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Duration</th>
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
                                    <td>{{ $prescription->quantity_prescribed ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        <p>No prescriptions recorded.</p>
                    </div>
                @endif
            </div>

        </div>{{-- end col-right --}}

    </div>{{-- end body-grid --}}
</div>

@endsection