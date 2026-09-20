@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-show-appointment.css') }}">
@endsection

@section('content')

<div class="sa-wrapper">

    {{-- HEADER --}}
    <div class="sa-header sa-header--back">
        <a href="{{ route('doctor.appointments.index') }}" class="sa-back-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back
        </a>
    </div>

    <div class="sa-header sa-header--titlebar">
        <div class="sa-header__title-row">
            <h1 class="sa-header__title">Consultation</h1>
            @php
                $statusMap = [
                    'Approved'    => 'approved',
                    'Checked In'  => 'checkedin',
                    'In Progress' => 'inprogress',
                    'Completed'   => 'completed',
                    'Pending'     => 'pending',
                    'Rejected'    => 'rejected',
                    'Cancelled'   => 'cancelled',
                    'Rescheduled' => 'rescheduled',
                    'No Show'     => 'noshow',
                ];
                $statusKey = $statusMap[$appointment->status] ?? 'pending';
            @endphp
            <span class="sa-badge sa-badge--{{ $statusKey }}">{{ $appointment->status }}</span>
        </div>
        <a href="{{ route('doctor.prescription.print', $appointment->id) }}"
           class="sa-btn sa-btn--outline" target="_blank">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Prescription
        </a>
    </div>

    {{-- ALERTS --}}
    @if(session('success'))
        <div class="sa-alert sa-alert--success">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="sa-alert sa-alert--error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="sa-alert sa-alert--error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <ul class="sa-alert__list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- STEP NAVIGATION — decide which step to open first. Defaults to
         Step 1, but if a validation error landed on a field that belongs
         to a later step, open that step so the doctor sees it immediately. --}}
    @php
        $sa_stepFieldMap = [
            2 => ['chief_complaint','blood_pressure','temperature','weight','height','history_of_present_illness','physical_examination','diagnosis','treatment','notes'],
            3 => ['medicine_id','manual_medicine_name','dosage','frequency','duration','quantity_prescribed','instructions','next_review_date','message'],
            4 => ['service_ids','service_qty','medicine_rx_ids','medicine_rx_qty'],
        ];

        // Default to whichever step the Doctor last had open (session
        // only — never written to the medical record), so a reload/F5
        // or any save/redirect never bounces back to Step 1.
        $saInitialStep = (int) session('consultation_step_' . $appointment->id, 1);
        if ($saInitialStep < 1 || $saInitialStep > 4) {
            $saInitialStep = 1;
        }

        // A validation error always wins over the remembered step, so
        // the Doctor immediately sees whichever step the problem is on.
        if ($errors->any()) {
            foreach ($sa_stepFieldMap as $sa_stepNum => $sa_fields) {
                foreach ($sa_fields as $sa_field) {
                    if ($errors->has($sa_field) || $errors->has($sa_field . '.*')) {
                        $saInitialStep = $sa_stepNum;
                        break 2;
                    }
                }
            }
        }

        session(['consultation_step_' . $appointment->id => $saInitialStep]);
    @endphp

    {{-- PROGRESS BAR --}}
    <nav class="sa-progressbar" aria-label="Consultation steps">
        <div class="sa-progressbar__track">
            @foreach([
                1 => ['Patient & Records', 'Info & history'],
                2 => ['Assessment', 'Vitals & diagnosis'],
                3 => ['Prescription & Review', 'Meds & follow-up'],
                4 => ['Charges & Completion', 'Billing & finish'],
            ] as $sa_num => $sa_step)
                @if($sa_num > 1)
                    <span class="sa-progress-line"></span>
                @endif
                <button type="button"
                        class="sa-progress-step {{ $saInitialStep == $sa_num ? 'is-active' : '' }} {{ $sa_num < $saInitialStep ? 'is-completed' : '' }}"
                        data-step-btn="{{ $sa_num }}">
                    <span class="sa-progress-step__circle">
                        <span class="sa-progress-step__num">{{ $sa_num }}</span>
                        <svg class="sa-progress-step__check" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <span class="sa-progress-step__text">
                        <span class="sa-progress-step__title">{{ $sa_step[0] }}</span>
                        <span class="sa-progress-step__desc">{{ $sa_step[1] }}</span>
                    </span>
                </button>
            @endforeach
        </div>
    </nav>

    {{-- PAGE CONTENT (strict order: Patient/Appointment Info -> Previous Records -> Consultation Info -> Prescription -> Review Schedule -> Services/Charges -> Bottom Actions) --}}
    <div class="sa-stack" id="consultationStepper" data-current-step="{{ $saInitialStep }}"
         data-set-step-url="{{ route('doctor.appointments.setStep', $appointment->id) }}">

        <div class="step-panel {{ $saInitialStep == 1 ? 'is-active' : '' }}" data-step="1">

        {{-- 1. PATIENT AND APPOINTMENT INFORMATION --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon sa-card__icon--blue">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </span>
                <h2 class="sa-card__title">Patient and Appointment Information</h2>
            </div>

            @php
                $isWalkIn   = $appointment->walkin_patient_id !== null;
                $patientObj = $isWalkIn ? $appointment->walkinPatient : $appointment->patient;
                $fullName   = $patientObj
                    ? trim(($patientObj->first_name ?? '') . ' ' . ($patientObj->last_name ?? ''))
                    : 'N/A';
                $initials   = collect(explode(' ', $fullName))
                                ->map(fn($w) => strtoupper(substr($w,0,1)))
                                ->take(2)
                                ->implode('');

                $emergencyContactNumber = $isWalkIn
                    ? ($patientObj->emergency_contact ?? null)
                    : ($patientObj->emergency_contact_number ?? null);
            @endphp
            <div class="sa-patient-hero">
                <div class="sa-patient-avatar">{{ $initials ?: 'P' }}</div>
                <div>
                    <div class="sa-patient-name">{{ $fullName }}</div>
                    <div class="sa-patient-meta">
                        {{ $isWalkIn ? 'Walk-in Patient' : 'Registered Patient' }}
                        @if($patientObj?->contact_number)
                            <span class="sa-dot">·</span> {{ $patientObj->contact_number }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="sa-pai-grid">

                {{-- Patient Information --}}
                <div class="sa-pai-section">
                    <div class="sa-pai-section__title">Patient Information</div>
                    <dl class="sa-info-list">
                        <div class="sa-info-row">
                            <dt>Patient ID</dt>
                            <dd>{{ $appointment->patientDisplayId() }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Full Name</dt>
                            <dd>{{ $fullName }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Age</dt>
                            <dd>
                                @if($patientObj?->birthdate)
                                    {{ $patientObj->age ?? '—' }} yrs old
                                    ({{ \Carbon\Carbon::parse($patientObj->birthdate)->format('F d, Y') }})
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Gender</dt>
                            <dd>{{ $patientObj?->gender ?? '—' }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Contact Number</dt>
                            <dd>{{ $patientObj?->contact_number ?? '—' }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Address</dt>
                            <dd>{{ $patientObj?->address ?? '—' }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Blood Type</dt>
                            <dd>{{ $patientObj?->blood_type ?? '—' }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Allergies</dt>
                            <dd>{{ $patientObj?->allergies ?? 'None recorded' }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Emergency Contact</dt>
                            <dd>
                                @if($patientObj?->emergency_name || $emergencyContactNumber)
                                    {{ $patientObj?->emergency_name ?? '—' }}
                                    @if($emergencyContactNumber)
                                        ({{ $emergencyContactNumber }})
                                    @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Appointment Information --}}
                <div class="sa-pai-section">
                    <div class="sa-pai-section__title">Appointment Information</div>
                    <dl class="sa-info-list">
                        <div class="sa-info-row">
                            <dt>Appointment Date</dt>
                            <dd>{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Appointment Time</dt>
                            <dd>{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Reason for Visit</dt>
                            <dd>{{ $appointment->reason ?? '—' }}</dd>
                        </div>
                        <div class="sa-info-row">
                            <dt>Appointment Status</dt>
                            <dd><span class="sa-badge sa-badge--{{ $statusKey }}">{{ $appointment->status }}</span></dd>
                        </div>
                        @if($appointment->status === 'Rescheduled' && !empty($appointment->reschedule_reason))
                            <div class="sa-info-row">
                                <dt>Reschedule Reason</dt>
                                <dd>{{ $appointment->reschedule_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

            </div>
        </div>

        {{-- 2. PREVIOUS MEDICAL RECORDS --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon sa-card__icon--teal">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span>
                <h2 class="sa-card__title">Previous Medical Records</h2>
                @if($previousRecords->isNotEmpty())
                    <span class="sa-count-chip">{{ $previousRecords->count() }}</span>
                @endif
                <div class="sa-card__header-actions">
                    <a href="{{ route('doctor.medical-records.index') }}" class="sa-btn sa-btn--outline sa-btn--sm">View All</a>
                </div>
            </div>

            @php $latestRecords = $previousRecords->take(3); @endphp

            @if($latestRecords->isNotEmpty())
                <div class="sa-table-wrap">
                    <table class="sa-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Diagnosis</th>
                                <th>Doctor</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestRecords as $record)
                                <tr>
                                    <td>
                                        {{ $record->appointment ? \Carbon\Carbon::parse($record->appointment->appointment_date)->format('M d, Y') : $record->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="sa-table__diagnosis">{{ $record->diagnosis ?? '—' }}</td>
                                    <td>
                                        @if($record->doctor)
                                            Dr. {{ $record->doctor->first_name }} {{ $record->doctor->last_name }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('doctor.medical-records.show', $record->appointment_id) }}" class="sa-btn sa-btn--outline sa-btn--sm">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="sa-table-note">Showing {{ $latestRecords->count() }} latest record{{ $latestRecords->count() > 1 ? 's' : '' }}.</p>
            @else
                <div class="sa-empty-state">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <p>No previous medical records available.</p>
                </div>
            @endif
        </div>

        <div class="sa-step-footer sa-step-footer--first">
            <button type="button" class="sa-btn sa-btn--primary" data-step-target="2">
                Next: Assessment
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        </div>{{-- /step-panel 1 --}}

        <div class="step-panel {{ $saInitialStep == 2 ? 'is-active' : '' }}" data-step="2">

        {{-- 3. CONSULTATION INFORMATION --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon sa-card__icon--teal">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9S3 16.97 3 12 7.03 3 12 3s9 4.03 9 9z"/></svg>
                </span>
                <h2 class="sa-card__title">Consultation Information</h2>
            </div>

            @if($appointment->status === 'Checked In')
                <p class="sa-empty">
                    The patient has been checked in. Click <strong>Start Consultation</strong> below to begin recording the medical record.
                </p>

                <form action="{{ route('doctor.appointments.startConsultation', $appointment->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="sa-btn sa-btn--primary sa-btn--full">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        Start Consultation
                    </button>
                </form>

            @elseif($appointment->status === 'Completed')
                <div class="sa-notes-stack">
                    <div class="sa-note-block sa-note-block--complaint">
                        <div class="sa-note-label">Chief Complaint</div>
                        <div class="sa-note-content">{{ $medicalRecord->chief_complaint ?? '—' }}</div>
                    </div>
                </div>

                <div class="sa-vitals-grid">
                    <div class="sa-vital-tile">
                        <div class="sa-vital-icon sa-vital-icon--bp">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        </div>
                        <div>
                            <div class="sa-vital-val">{{ $medicalRecord->blood_pressure ?? '—' }}</div>
                            <div class="sa-vital-label">Blood Pressure</div>
                        </div>
                    </div>
                    <div class="sa-vital-tile">
                        <div class="sa-vital-icon sa-vital-icon--temp">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"/></svg>
                        </div>
                        <div>
                            <div class="sa-vital-val">{{ $medicalRecord->temperature ?? '—' }}</div>
                            <div class="sa-vital-label">Temperature</div>
                        </div>
                    </div>
                    <div class="sa-vital-tile">
                        <div class="sa-vital-icon sa-vital-icon--weight">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"/><path d="M6.5 8a2 2 0 0 0-1.905 1.46L2.1 18.5A2 2 0 0 0 4 21h16a2 2 0 0 0 1.9-2.54L19.4 9.46A2 2 0 0 0 17.5 8z"/></svg>
                        </div>
                        <div>
                            <div class="sa-vital-val">{{ $medicalRecord->weight ?? '—' }}</div>
                            <div class="sa-vital-label">Weight</div>
                        </div>
                    </div>
                    <div class="sa-vital-tile">
                        <div class="sa-vital-icon sa-vital-icon--height">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="22"/><polyline points="17 7 12 2 7 7"/><polyline points="17 17 12 22 7 17"/></svg>
                        </div>
                        <div>
                            <div class="sa-vital-val">{{ $medicalRecord->height ?? '—' }}</div>
                            <div class="sa-vital-label">Height</div>
                        </div>
                    </div>
                </div>

                <div class="sa-notes-stack" style="margin-top:16px;">
                    <div class="sa-note-block">
                        <div class="sa-note-label">History of Present Illness</div>
                        <div class="sa-note-content">{{ $medicalRecord->history_of_present_illness ?? '—' }}</div>
                    </div>
                    <div class="sa-note-block">
                        <div class="sa-note-label">Physical Examination</div>
                        <div class="sa-note-content">{{ $medicalRecord->physical_examination ?? '—' }}</div>
                    </div>
                    <div class="sa-note-block sa-note-block--diagnosis">
                        <div class="sa-note-label">Diagnosis</div>
                        <div class="sa-note-content">{{ $medicalRecord->diagnosis ?? '—' }}</div>
                    </div>
                    <div class="sa-note-block sa-note-block--treatment">
                        <div class="sa-note-label">Treatment / Plan</div>
                        <div class="sa-note-content">{{ $medicalRecord->treatment ?? '—' }}</div>
                    </div>
                    @if($medicalRecord?->notes)
                    <div class="sa-note-block sa-note-block--notes">
                        <div class="sa-note-label">Additional Notes</div>
                        <div class="sa-note-content">{{ $medicalRecord->notes }}</div>
                    </div>
                    @endif
                </div>

            @else
                <form id="consultationForm" data-autosave="true"
                      action="{{ route('doctor.appointments.saveDraft', $appointment->id) }}"
                      method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="sa-form-group" style="margin-top:0;">
                        <label class="sa-label">Chief Complaint / Reason for Visit <span class="sa-required">*</span></label>
                        <textarea name="chief_complaint" class="sa-textarea" rows="3"
                            data-required-label="Chief Complaint"
                            placeholder="Patient's main complaint...">{{ old('chief_complaint', $medicalRecord->chief_complaint ?? '') }}</textarea>
                    </div>

                    <div class="sa-section-divider" style="margin-top:6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Vital Signs
                    </div>

                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-label">Blood Pressure</label>
                            <input type="text" name="blood_pressure" class="sa-input"
                                placeholder="e.g. 120/80 mmHg"
                                value="{{ old('blood_pressure', $medicalRecord->blood_pressure ?? '') }}">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Temperature</label>
                            <input type="text" name="temperature" class="sa-input"
                                placeholder="e.g. 36.6 °C"
                                value="{{ old('temperature', $medicalRecord->temperature ?? '') }}">
                        </div>
                    </div>
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-label">Weight</label>
                            <input type="text" name="weight" class="sa-input"
                                placeholder="e.g. 65 kg"
                                value="{{ old('weight', $medicalRecord->weight ?? '') }}">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Height</label>
                            <input type="text" name="height" class="sa-input"
                                placeholder="e.g. 170 cm"
                                value="{{ old('height', $medicalRecord->height ?? '') }}">
                        </div>
                    </div>

                    <div class="sa-section-divider">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9S3 16.97 3 12 7.03 3 12 3s9 4.03 9 9z"/></svg>
                        Clinical Notes
                    </div>

                    <div class="sa-form-group">
                        <label class="sa-label">History of Present Illness / Clinical Notes</label>
                        <textarea name="history_of_present_illness" class="sa-textarea" rows="3"
                            placeholder="Onset, duration, progression of the current complaint...">{{ old('history_of_present_illness', $medicalRecord->history_of_present_illness ?? '') }}</textarea>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-label">Physical Examination</label>
                        <textarea name="physical_examination" class="sa-textarea" rows="3"
                            placeholder="Examination findings...">{{ old('physical_examination', $medicalRecord->physical_examination ?? '') }}</textarea>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-label">Diagnosis / Assessment <span class="sa-required">*</span></label>
                        <textarea name="diagnosis" class="sa-textarea" rows="4"
                            data-required-label="Diagnosis"
                            placeholder="Enter diagnosis, findings, or clinical notes...">{{ old('diagnosis', $medicalRecord->diagnosis ?? '') }}</textarea>
                    </div>
                    <div class="sa-form-group">
                        <label class="sa-label">Treatment / Plan <span class="sa-required">*</span></label>
                        <textarea name="treatment" class="sa-textarea" rows="3"
                            data-required-label="Treatment Plan"
                            placeholder="Describe the treatment plan or procedures...">{{ old('treatment', $medicalRecord->treatment ?? '') }}</textarea>
                    </div>
                    <div class="sa-form-group" style="margin-bottom:0;">
                        <label class="sa-label">Notes</label>
                        <textarea name="notes" class="sa-textarea" rows="3"
                            placeholder="Any other clinical notes or observations...">{{ old('notes', $medicalRecord->notes ?? '') }}</textarea>
                    </div>

                    <p class="sa-required-note">
                        <span class="sa-required">*</span> required before continuing to the next step. Your progress saves automatically when you click Next.
                    </p>
                </form>
            @endif
        </div>

        <div class="sa-step-footer">
            <button type="button" class="sa-btn sa-btn--ghost" data-step-target="1">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </button>
            <button type="button" class="sa-btn sa-btn--primary" data-step-target="3">
                Next: Prescription & Review
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        </div>{{-- /step-panel 2 --}}

        <div class="step-panel {{ $saInitialStep == 3 ? 'is-active' : '' }}" data-step="3">

        {{-- 4. PRESCRIPTION --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon sa-card__icon--purple">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </span>
                <h2 class="sa-card__title">Prescription</h2>
                @if($prescriptions->isNotEmpty())
                    <span class="sa-count-chip">{{ $prescriptions->count() }}</span>
                @endif
                @if($appointment->status === 'In Progress')
                    <div class="sa-card__header-actions">
                        <button type="button" class="sa-btn sa-btn--primary sa-btn--sm" id="addRxToggleBtn">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Prescription
                        </button>
                    </div>
                @endif
            </div>

            @forelse($prescriptions as $prescription)
            <div class="sa-rx-item">
                <div class="sa-rx-pill-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 20H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H20a2 2 0 0 1 2 2v3"/><circle cx="18" cy="18" r="3"/><path d="m22 22-1.5-1.5"/></svg>
                </div>
                <div class="sa-rx-item__body">
                    <p class="sa-rx-item__name">
                        {{ $prescription->medicine->medicine_name
                            ?? $prescription->manual_medicine_name
                            ?? 'N/A' }}
                    </p>
                    <div class="sa-rx-tags">
                        @if($prescription->dosage)
                            <span class="sa-rx-tag">{{ $prescription->dosage }}</span>
                        @endif
                        @if($prescription->frequency)
                            <span class="sa-rx-tag">{{ $prescription->frequency }}</span>
                        @endif
                        @if($prescription->duration)
                            <span class="sa-rx-tag">{{ $prescription->duration }}</span>
                        @endif
                        @if($prescription->quantity_prescribed)
                            <span class="sa-rx-tag sa-rx-tag--qty">Qty: {{ $prescription->quantity_prescribed }}</span>
                        @endif
                    </div>
                    @if($prescription->instructions)
                        <p class="sa-rx-item__instructions">{{ $prescription->instructions }}</p>
                    @endif
                </div>
                @if($appointment->status === 'In Progress' && $prescription->dispense_status !== 'Dispensed')
                <form action="{{ route('doctor.prescriptions.destroy', $prescription->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sa-btn-icon sa-btn-icon--danger"
                        onclick="return confirm('Delete this prescription?')"
                        title="Delete prescription">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </form>
                @endif
            </div>
            @empty
            <div class="sa-empty-state">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <p>No medicines prescribed yet.</p>
            </div>
            @endforelse

            {{-- ADD PRESCRIPTION (one top-level toggle button above opens this;
                 no second "Add Prescription" control here — only the actual
                 form submit button below) --}}
            @if($appointment->status === 'In Progress')
            <div class="sa-add-rx" id="addRxPanel" style="display:none;">
                <div class="sa-add-rx__body">
                    <form action="{{ route('doctor.prescriptions.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">

                        <div class="sa-form-group">
                            <label class="sa-label">Medicine <span class="sa-required">*</span></label>
                            <select name="medicine_id" id="medicineSelect" class="sa-input">
                                <option value="">— Select Medicine —</option>
                                @foreach($medicines as $medicine)
                                    @if($medicine->status !== 'Out of Stock')
                                        <option value="{{ $medicine->id }}"
                                                data-dosage="{{ $medicine->dosage ?? '' }}">
                                            {{ $medicine->medicine_name }}
                                            ({{ $medicine->quantity }} {{ $medicine->unit }} available)
                                        </option>
                                    @endif
                                @endforeach
                                <option value="manual" data-dosage="">Other (Manual Input)</option>
                            </select>
                        </div>

                        <div class="sa-form-group" id="manualMedicineField" style="display:none;">
                            <label class="sa-label">Medicine Name (Manual)</label>
                            <input type="text" name="manual_medicine_name" class="sa-input"
                                   placeholder="Enter medicine name">
                        </div>

                        <div class="sa-form-row">
                            <div class="sa-form-group">
                                <label class="sa-label">Dosage <span class="sa-required">*</span></label>
                                <input type="text" name="dosage" id="dosageInput"
                                       class="sa-input" placeholder="e.g. 500mg" required>
                            </div>
                            <div class="sa-form-group">
                                <label class="sa-label">Frequency <span class="sa-required">*</span></label>
                                <input type="text" name="frequency" class="sa-input"
                                       placeholder="e.g. 2x a day" required>
                            </div>
                        </div>

                        <div class="sa-form-row">
                            <div class="sa-form-group">
                                <label class="sa-label">Duration <span class="sa-required">*</span></label>
                                <input type="text" name="duration" class="sa-input"
                                       placeholder="e.g. 5 days" required>
                            </div>
                            <div class="sa-form-group" id="qtyGroup">
                                <label class="sa-label">Qty to Prescribe <span class="sa-required">*</span></label>
                                <input type="number" name="quantity_prescribed" id="qtyInput"
                                       class="sa-input" min="1" required>
                            </div>
                        </div>

                        <div class="sa-form-group">
                            <label class="sa-label">Instructions</label>
                            <input type="text" name="instructions" class="sa-input"
                                   placeholder="e.g. Take after meals">
                        </div>

                        <button type="submit" class="sa-btn sa-btn--primary sa-btn--full">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add Prescription
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        {{-- 5. REVIEW SCHEDULE --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon sa-card__icon--green">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </span>
                <h2 class="sa-card__title">Review Schedule</h2>
                @if($appointment->status === 'In Progress')
                    <span class="sa-complete-note">Optional — does not complete the consultation</span>
                @endif
            </div>

            @if($appointment->status === 'In Progress')
                <form action="{{ route('doctor.review.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-label">Next Review On</label>
                            <input type="date" name="next_review_date" class="sa-input"
                                value="{{ $review->next_review_date ?? '' }}">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Message / Notes</label>
                            <textarea name="message" class="sa-textarea" rows="3"
                                placeholder="Instructions or notes for the patient...">{{ $review->message ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="sa-form-actions">
                        <button type="submit" class="sa-btn sa-btn--primary">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Save Review
                        </button>
                    </div>
                </form>
            @elseif($review)
                <dl class="sa-info-list">
                    @if($review->next_review_date)
                    <div class="sa-info-row">
                        <dt>Next Review</dt>
                        <dd>{{ \Carbon\Carbon::parse($review->next_review_date)->format('F d, Y') }}</dd>
                    </div>
                    @endif
                    @if($review->message)
                    <div class="sa-info-row">
                        <dt>Notes</dt>
                        <dd>{{ $review->message }}</dd>
                    </div>
                    @endif
                </dl>
            @else
                <p class="sa-empty">No review notes recorded.</p>
            @endif
        </div>

        <div class="sa-step-footer">
            <button type="button" class="sa-btn sa-btn--ghost" data-step-target="2">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </button>
            <button type="button" class="sa-btn sa-btn--primary" data-step-target="4">
                Next: Charges & Completion
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        </div>{{-- /step-panel 3 --}}

        <div class="step-panel {{ $saInitialStep == 4 ? 'is-active' : '' }}" data-step="4">

        {{-- 6. SERVICES / CHARGES --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon sa-card__icon--orange">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
                <h2 class="sa-card__title">Services / Charges</h2>
                @if($invoice)
                    <span class="sa-billing-badge sa-billing-badge--{{ $invoice->statusBadgeClass() }}">{{ $invoice->status }}</span>
                @endif
            </div>

            @if($invoice && $invoice->amount_paid > 0)
                <p class="sa-billing-note">
                    A payment has already been recorded for this consultation
                    (Invoice {{ $invoice->invoice_no }}) — charges can no longer be edited here.
                </p>
                <ul class="sa-billing-item-list">
                    @foreach($invoice->items as $item)
                        <li>
                            <span>{{ $item->description }} @if($item->quantity > 1) × {{ $item->quantity }} @endif</span>
                            <span>₱{{ number_format($item->amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="sa-billing-total">
                    <span>Total</span>
                    <span>₱{{ number_format($invoice->total_amount, 2) }}</span>
                </div>
            @else
                @php
                    $purchasableRx = $prescriptions->whereNotNull('medicine_id')->values();
                    $existingByMedicineRx = $invoice
                        ? $invoice->items->where('item_type', 'medicine')->keyBy('medicine_id')
                        : collect();
                @endphp
                @if(!(auth()->user()->hasPermission('create_invoice') || auth()->user()->hasPermission('edit_invoice')))
                    <p class="sa-empty">You do not have permission to create or edit billing charges. Contact your Admin if you need this access.</p>
                @else
                <form action="{{ route('doctor.appointments.saveCharges', $appointment->id) }}" method="POST" id="chargesForm">
                    @csrf
                    @method('PATCH')

                    @if($services->isEmpty())
                        <p class="sa-empty">No active services configured yet. Ask Admin to add one under Services / Fees.</p>
                    @else
                        @php
                            $existingByService = $invoice ? $invoice->items->keyBy('service_id') : collect();
                        @endphp
                        <div class="sa-charge-list">
                            @foreach($services as $service)
                                @php $existing = $existingByService->get($service->id); @endphp
                                <label class="sa-charge-row">
                                    <input type="checkbox" name="service_ids[]" value="{{ $service->id }}"
                                           data-price="{{ $service->price }}" class="sa-charge-check"
                                           {{ $existing ? 'checked' : '' }}>
                                    <span class="sa-charge-name">{{ $service->name }}</span>
                                    <span class="sa-charge-price">₱{{ number_format($service->price, 2) }}</span>
                                    <input type="number" name="service_qty[{{ $service->id }}]" min="1"
                                           value="{{ $existing->quantity ?? 1 }}" class="sa-charge-qty">
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div class="sa-charge-medicines">
                        <div class="sa-charge-medicines__label">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Clinic Medicines — check what the patient is buying
                        </div>

                        @forelse($purchasableRx as $rx)
                            @if($rx->medicine)
                                @php $existingRxItem = $existingByMedicineRx->get($rx->medicine_id); @endphp
                                <label class="sa-charge-row">
                                    <input type="checkbox" name="medicine_rx_ids[]" value="{{ $rx->id }}"
                                           data-price="{{ $rx->medicine->price }}" class="sa-charge-check"
                                           {{ $existingRxItem ? 'checked' : '' }}>
                                    <span class="sa-charge-name">{{ $rx->medicine->medicine_name }}</span>
                                    <span class="sa-charge-price">₱{{ number_format($rx->medicine->price, 2) }}</span>
                                    <input type="number" name="medicine_rx_qty[{{ $rx->id }}]" min="1"
                                           max="{{ $rx->quantity_prescribed }}"
                                           value="{{ $existingRxItem->quantity ?? $rx->quantity_prescribed }}" class="sa-charge-qty">
                                </label>
                            @endif
                        @empty
                            <p class="sa-charge-medicines__empty">No clinic-inventory medicine has been prescribed yet. Manually prescribed (outside-pharmacy) items are never billed.</p>
                        @endforelse

                        @if($purchasableRx->isNotEmpty())
                            <p class="sa-charge-medicines__empty" style="margin-top:8px;">Leave a medicine unchecked if the patient is not buying it from the clinic (e.g. taking the prescription outside, or deciding later) — it stays ₱0 and no stock is affected here.</p>
                        @endif
                    </div>

                    <div class="sa-charge-est-total">
                        <span>Estimated Total</span>
                        <span id="chargesEstTotal">₱0.00</span>
                    </div>

                    <button type="submit" class="sa-btn sa-btn--primary sa-btn--full" {{ $services->isEmpty() && $purchasableRx->isEmpty() ? 'disabled' : '' }}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Save Charges
                    </button>
                </form>

                <script>
                (function () {
                    const form    = document.getElementById('chargesForm');
                    if (!form) return;
                    const totalEl = document.getElementById('chargesEstTotal');

                    function recompute() {
                        let sum = 0;
                        form.querySelectorAll('.sa-charge-check').forEach(function (chk) {
                            if (!chk.checked) return;
                            const row = chk.closest('.sa-charge-row');
                            const qtyInput = row ? row.querySelector('.sa-charge-qty') : null;
                            const qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                            sum += parseFloat(chk.dataset.price || 0) * qty;
                        });
                        totalEl.textContent = '₱' + sum.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }

                    form.querySelectorAll('.sa-charge-check, .sa-charge-qty').forEach(function (el) {
                        el.addEventListener('input', recompute);
                        el.addEventListener('change', recompute);
                    });

                    recompute();
                })();
                </script>
                @endif
            @endif
        </div>

        {{-- 7. BOTTOM ACTION BUTTONS --}}
        <div class="sa-step-footer sa-step-footer--last">
            <button type="button" class="sa-btn sa-btn--ghost" data-step-target="3">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </button>
            @if(!in_array($appointment->status, ['Checked In', 'Completed'], true))
            <div class="sa-bottom-actions">
                <button type="submit"
                        form="consultationForm"
                        formaction="{{ route('doctor.appointments.completeConsultation', $appointment->id) }}"
                        class="sa-btn sa-btn--success"
                        onclick="return confirm('Complete this consultation? Once completed, it can no longer be edited.')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Complete Consultation
                </button>
            </div>
            @endif
        </div>

        </div>{{-- /step-panel 4 --}}

    </div>
</div>

<script>
document.getElementById('medicineSelect')?.addEventListener('change', function () {
    const isManual      = this.value === 'manual';
    const selectedOpt   = this.options[this.selectedIndex];
    const dosage        = selectedOpt.getAttribute('data-dosage') ?? '';

    document.getElementById('manualMedicineField').style.display = isManual ? 'block' : 'none';

    const dosageInput       = document.getElementById('dosageInput');
    dosageInput.value       = dosage;
    dosageInput.readOnly    = (!isManual && dosage !== '');

    const qtyInput = document.getElementById('qtyInput');
    if (isManual) {
        qtyInput.removeAttribute('required');
        qtyInput.value = '';
    } else {
        qtyInput.setAttribute('required', 'required');
    }
});
</script>

<script>
document.getElementById('addRxToggleBtn')?.addEventListener('click', function () {
    const panel = document.getElementById('addRxPanel');
    if (!panel) return;
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
</script>

<script>
(function () {
    // Step wizard: only the active .step-panel is shown, and the
    // progress bar reflects it. Every form, route and field stays
    // exactly where it already was in the DOM.
    //
    // On top of the original display-only toggle, this also:
    //   - remembers the active step server-side (session only) so a
    //     reload/F5 or any save/redirect reopens the same step;
    //   - validates the current step's required fields before letting
    //     Next proceed, showing every missing-field error at once and
    //     auto-scrolling to the first one;
    //   - auto-saves the current step's form (when it has one) before
    //     advancing, so there is no separate manual "Save" step.
    const stepper = document.getElementById('consultationStepper');
    if (!stepper) return;

    const panels        = stepper.querySelectorAll('.step-panel');
    const progressBtns  = document.querySelectorAll('.sa-progress-step');
    const setStepUrl    = stepper.dataset.setStepUrl;
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken      = csrfTokenMeta ? csrfTokenMeta.content : '';

    function showPanel(target) {
        target = String(target);

        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.dataset.step === target);
        });

        progressBtns.forEach(function (btn) {
            const stepNum = btn.dataset.stepBtn;
            btn.classList.toggle('is-active', stepNum === target);
            btn.classList.toggle('is-completed', Number(stepNum) < Number(target));
        });

        stepper.dataset.currentStep = target;
        stepper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function persistStep(target) {
        if (!setStepUrl) return;
        fetch(setStepUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ step: Number(target) }),
            keepalive: true,
        }).catch(function () {
            // Non-fatal — worst case a reload reopens the previous step.
        });
    }

    function goToStep(target) {
        showPanel(target);
        persistStep(target);
    }

    // ── Field-level validation helpers ─────────────────────────────
    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        const msg = field.parentElement.querySelector('.sa-field-error');
        if (msg) msg.remove();
    }

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        let msg = field.parentElement.querySelector('.sa-field-error');
        if (!msg) {
            msg = document.createElement('p');
            msg.className = 'sa-field-error';
            field.insertAdjacentElement('afterend', msg);
        }
        msg.textContent = message;
    }

    // Validates every [data-required-label] field inside the given
    // step panel. Returns the list of invalid fields (empty = valid).
    // All invalid fields get their error shown at once — not just the
    // first one.
    function validatePanel(panel) {
        const invalidFields = [];
        panel.querySelectorAll('[data-required-label]').forEach(function (field) {
            clearFieldError(field);
            if (!field.value || !field.value.trim()) {
                showFieldError(field, field.dataset.requiredLabel + ' is required.');
                invalidFields.push(field);
            }
        });
        return invalidFields;
    }

    // ── Next / Back / progress-bar navigation ──────────────────────
    document.querySelectorAll('[data-step-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetStep   = btn.dataset.stepTarget;
            const currentPanel = stepper.querySelector('.step-panel.is-active');
            const isForward    = currentPanel
                ? Number(targetStep) > Number(currentPanel.dataset.step)
                : true;

            // Only moving FORWARD triggers validation — Back is always free.
            if (isForward && currentPanel) {
                const invalids = validatePanel(currentPanel);
                if (invalids.length) {
                    invalids[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    invalids[0].focus({ preventScroll: true });
                    return;
                }
            }

            // If the current step has an auto-save form, save it first
            // and only advance once the save succeeds.
            const autosaveForm = isForward && currentPanel
                ? currentPanel.querySelector('form[data-autosave]')
                : null;

            if (autosaveForm) {
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.textContent = 'Saving...';

                fetch(autosaveForm.getAttribute('action'), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(autosaveForm),
                })
                .then(function (res) {
                    if (!res.ok) throw new Error('save-failed');
                    return res.json().catch(function () { return {}; });
                })
                .then(function () {
                    goToStep(targetStep);
                })
                .catch(function () {
                    alert('Could not save your progress. Please check your connection and try again.');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
                return;
            }

            goToStep(targetStep);
        });
    });

    progressBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            goToStep(btn.dataset.stepBtn);
        });
    });

    // Sync the progress bar to whichever step is active on load — the
    // remembered session step, or a step a validation error landed on
    // — without re-persisting it (nothing changed, just re-rendering).
    showPanel(stepper.dataset.currentStep || '1');
})();
</script>

@endsection