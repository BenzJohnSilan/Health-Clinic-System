@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-show-appointment.css') }}">
@endsection

@section('content')

<div class="sa-wrapper">

    {{-- HERO — same banner style as the dashboard --}}
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
    <div class="sa-hero">
        <div class="sa-hero__main">
            <div class="sa-hero__icon"><i class='bx bx-plus-medical'></i></div>
            <div>
                <div class="sa-hero__title-row">
                    <h1 class="sa-hero__title">Consultation</h1>
                    <span class="sa-badge sa-badge--{{ $statusKey }}">{{ $appointment->status }}</span>
                    @if($appointment->isFollowUp())
                        <span class="sa-badge sa-badge--followup">Follow-up Consultation</span>
                    @endif
                </div>
                <p class="sa-hero__sub">
                    <span><i class='bx bx-calendar'></i> {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</span>
                    <span><i class='bx bx-time-five'></i> {{ $appointment->timeLabel('h:i A') }}</span>
                </p>
            </div>
        </div>
        <div class="sa-hero__actions">
            <a href="{{ route('doctor.appointments.index') }}" class="sa-btn sa-btn--hero-ghost">
                <i class='bx bx-chevron-left'></i> Back
            </a>
            <a href="{{ route('doctor.prescription.print', $appointment->id) }}"
               class="sa-btn sa-btn--hero" target="_blank">
                <i class='bx bx-printer'></i> Print Prescription
            </a>
        </div>
    </div>

    {{-- FOLLOW-UP CONSULTATION — the laboratory result that caused this
         appointment, one click away. The normal consultation steps below
         are unchanged. --}}
    @if($appointment->isFollowUp())
        @php $fuLab = $appointment->followUpLaboratoryRequest; @endphp
        <div class="sa-followup">
            <div class="sa-followup__title"><i class='bx bx-link-alt'></i> Follow-up Consultation</div>
            @if($fuLab)
                <div class="sa-followup__grid">
                    <div>
                        <span class="sa-followup__label">Related Laboratory Result</span>
                        <span class="sa-followup__value">{{ $fuLab->reference_no }}</span>
                    </div>
                    <div>
                        <span class="sa-followup__label">Tests</span>
                        <span class="sa-followup__value">{{ $fuLab->testsDisplay() }}</span>
                    </div>
                    <div>
                        <span class="sa-followup__label">Reviewed</span>
                        <span class="sa-followup__value">{{ $fuLab->reviewed_at?->format('M d, Y') ?? '—' }}</span>
                    </div>
                </div>
                @if($fuLab->review_notes)
                    <p class="sa-followup__text"><strong>Interpretation / Remarks:</strong> {{ $fuLab->review_notes }}</p>
                @endif
                @if($fuLab->follow_up_instructions)
                    <p class="sa-followup__text"><strong>Follow-up Instructions:</strong> {{ $fuLab->follow_up_instructions }}</p>
                @endif
                <a href="{{ route('doctor.laboratory-requests.show', $fuLab->id) }}" target="_blank" class="sa-btn sa-btn--outline">
                    View Laboratory Result
                </a>
            @else
                <p class="sa-followup__text">The related laboratory request is no longer available.</p>
            @endif
        </div>
    @endif

    {{-- ALERTS --}}
    @if(session('success'))
        <div class="sa-alert sa-alert--success">
            <i class='bx bx-check-circle'></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="sa-alert sa-alert--error">
            <i class='bx bx-error-circle'></i>
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="sa-alert sa-alert--error">
            <i class='bx bx-error-circle'></i>
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
            2 => ['chief_complaint','history_of_present_illness','physical_examination','diagnosis','treatment','notes'],
            3 => ['medicine_id','manual_medicine_name','dosage','frequency','duration','quantity_prescribed','instructions','next_review_date','message','tests','lab_diagnosis','other_test_description'],
            4 => ['service_ids','service_qty','fee_waiver','waiver_reason','waiver_notes'],
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
                2 => ['Assessment', 'Diagnosis & notes'],
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
                        <i class='bx bx-check sa-progress-step__check'></i>
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
                <span class="sa-card__icon"><i class='bx bx-user'></i></span>
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
                            <dd>{{ $appointment->timeLabel('h:i A') }}</dd>
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
                    <span class="sa-card__icon"><i class='bx bx-history'></i></span>
                    <h2 class="sa-card__title">Previous Medical Records</h2>
                    @if($previousRecords->isNotEmpty())
                        <span class="sa-count-chip">{{ $previousRecords->count() }}</span>
                    @endif
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
                                            <a href="{{ route('doctor.medical-records.show', $record->appointment_id) }}" target="_blank" rel="noopener noreferrer" class="sa-btn sa-btn--outline sa-btn--sm">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="sa-table-note">Showing {{ $latestRecords->count() }} latest record{{ $latestRecords->count() > 1 ? 's' : '' }}.</p>
                @else
                    <div class="sa-empty-state">
                        <i class='bx bx-file'></i>
                        <p>No previous medical records available.</p>
                    </div>
                @endif
            </div>

        <div class="sa-step-footer sa-step-footer--first">
            <button type="button" class="sa-btn sa-btn--primary" data-step-target="2">
                Next: Assessment
                <i class='bx bx-chevron-right'></i>
            </button>
        </div>

        </div>{{-- /step-panel 1 --}}

        <div class="step-panel {{ $saInitialStep == 2 ? 'is-active' : '' }}" data-step="2">

        {{-- 3. CONSULTATION INFORMATION --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon"><i class='bx bx-notepad'></i></span>
                <h2 class="sa-card__title">Consultation Information</h2>
            </div>

            @if($appointment->status === 'Checked In')
                <p class="sa-empty">
                    The patient has been checked in. Click <strong>Start Consultation</strong> below to begin recording the medical record.
                </p>

                <form action="{{ route('doctor.appointments.startConsultation', $appointment->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="sa-btn sa-btn--primary sa-btn--full">
                        <i class='bx bx-play'></i>
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

                @include('doctor.partials.vital-signs-readonly', ['medicalRecord' => $medicalRecord])

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
                        <i class='bx bx-pulse'></i>
                        Vital Signs <span class="sa-readonly-tag">Read-only · recorded by Staff</span>
                    </div>

                    @include('doctor.partials.vital-signs-readonly', ['medicalRecord' => $medicalRecord])

                    <div class="sa-section-divider">
                        <i class='bx bx-notepad'></i>
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
                <i class='bx bx-chevron-left'></i>
                Back
            </button>
            <button type="button" class="sa-btn sa-btn--primary" data-step-target="3">
                Next: Prescription & Review
                <i class='bx bx-chevron-right'></i>
            </button>
        </div>

        </div>{{-- /step-panel 2 --}}

        <div class="step-panel {{ $saInitialStep == 3 ? 'is-active' : '' }}" data-step="3">

        {{-- 3.5 LABORATORY REQUEST --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon"><i class='bx bxs-flask'></i></span>
                <h2 class="sa-card__title">Laboratory Request</h2>
                @if($laboratoryRequests->isNotEmpty())
                    <span class="sa-count-chip">{{ $laboratoryRequests->count() }}</span>
                @endif
            </div>

            {{-- Requests already saved for this consultation --}}
            @forelse($laboratoryRequests as $labReq)
                @php
                    $labBadgeMap = [
                        'Awaiting Result' => 'labawaiting',
                        'Result Received' => 'labreceived',
                        'Reviewed'         => 'labreviewed',
                    ];
                    $labBadgeClass = $labBadgeMap[$labReq->status] ?? 'labawaiting';

                    // Edit/Delete only while the consultation is In Progress
                    // (same window as Prescriptions) and the request has not
                    // entered the result workflow. Printing never locks it.
                    $labCanModify = $appointment->status === 'In Progress' && $labReq->isEditable();

                    // After a failed update, re-open THIS request's edit
                    // panel with the doctor's submitted values.
                    $labIsOldEdit = (string) old('lab_edit_id') === (string) $labReq->id;
                    $labEditTests = $labIsOldEdit
                        ? old('tests', [])
                        : $labReq->tests->pluck('test_name')->all();
                @endphp
                <div class="sa-lab-request-item">
                    <div class="sa-lab-request-item__body">
                        <p class="sa-lab-request-item__ref">
                            {{ $labReq->reference_no }}
                            <span class="sa-badge sa-badge--{{ $labBadgeClass }}">{{ $labReq->status }}</span>
                        </p>
                        <p class="sa-lab-request-item__tests">{{ $labReq->testsDisplay() }}</p>
                    </div>
                    <div class="sa-lab-request-item__actions">
                        @if($labCanModify)
                            <button type="button" class="sa-btn sa-btn--outline sa-btn--sm"
                                    data-lab-edit-toggle="labEditPanel{{ $labReq->id }}">
                                <i class='bx bx-edit'></i>
                                Edit
                            </button>
                            <form action="{{ route('doctor.laboratory-requests.destroy', $labReq->id) }}" method="POST"
                                  id="labDeleteForm{{ $labReq->id }}">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="sa-btn sa-btn--outline sa-btn--sm sa-btn--danger-outline"
                                        onclick="openLabDeleteModal('labDeleteForm{{ $labReq->id }}')">
                                    <i class='bx bx-trash'></i>
                                    Delete
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('doctor.laboratory-requests.print', $labReq->id) }}"
                           class="sa-btn sa-btn--outline sa-btn--sm" target="_blank">
                            <i class='bx bx-printer'></i>
                            Print Laboratory Request
                        </a>
                        @if(in_array($labReq->status, ['Result Received', 'Reviewed'], true))
                            <a href="{{ route('doctor.laboratory-requests.show', $labReq->id) }}"
                               class="sa-btn sa-btn--primary sa-btn--sm">
                                {{ $labReq->status === 'Reviewed' ? 'View Result' : 'Review Result' }}
                            </a>
                        @endif
                    </div>

                    @if($labCanModify)
                    {{-- Edit panel — same fields as the New Laboratory Request form,
                         pre-checked with this request's current tests. --}}
                    <div class="sa-lab-request-form sa-lab-edit-panel" id="labEditPanel{{ $labReq->id }}"
                         style="{{ $labIsOldEdit ? '' : 'display:none;' }}">
                        <form action="{{ route('doctor.laboratory-requests.update', $labReq->id) }}" method="POST"
                              data-lab-form>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="lab_edit_id" value="{{ $labReq->id }}">

                            <div class="sa-lab-tests-grid">
                                @foreach(\App\Models\LaboratoryRequest::TEST_CATALOG as $labTest)
                                    <label class="sa-lab-test-item">
                                        <input type="checkbox" name="tests[]" value="{{ $labTest }}"
                                               {{ $labTest === 'Others' ? 'data-lab-others' : '' }}
                                               {{ in_array($labTest, $labEditTests, true) ? 'checked' : '' }}>
                                        <span>{{ $labTest }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="sa-form-group" data-lab-other-group style="display:none; margin-top:10px;">
                                <label class="sa-label">Other Test Description</label>
                                <input type="text" name="other_test_description" class="sa-input"
                                       placeholder="Describe the other test"
                                       value="{{ $labIsOldEdit ? old('other_test_description') : $labReq->other_test_description }}">
                            </div>

                            <div class="sa-form-group" style="margin-top:10px; margin-bottom:0;">
                                <label class="sa-label">Diagnosis</label>
                                <textarea name="lab_diagnosis" class="sa-textarea" rows="2"
                                    placeholder="Diagnosis / clinical notes for this laboratory request...">{{ $labIsOldEdit ? old('lab_diagnosis') : $labReq->diagnosis }}</textarea>
                            </div>

                            <p class="sa-field-error" data-lab-empty-error style="display:none;">
                                Please select at least one laboratory test.
                            </p>

                            <div class="sa-lab-request-actions">
                                <button type="button" class="sa-btn sa-btn--ghost sa-btn--sm"
                                        data-lab-edit-toggle="labEditPanel{{ $labReq->id }}">
                                    Cancel
                                </button>
                                <button type="submit" class="sa-btn sa-btn--primary sa-btn--sm">
                                    <i class='bx bx-check'></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>
            @empty
                <p class="sa-empty">No laboratory tests requested yet.</p>
            @endforelse

            {{-- New Laboratory Request — same In Progress window as Prescriptions.
                 Only shown when this consultation has NO laboratory request yet;
                 once one exists, its summary (and Edit panel, when active) is
                 shown instead — never both at the same time. --}}
            @if($appointment->status === 'In Progress' && $laboratoryRequests->isEmpty())
            @php
                // Old input from a failed EDIT belongs to that request's
                // edit panel, not to this "new request" form.
                $labCreateOld = old('lab_edit_id') === null;
            @endphp
            <div class="sa-lab-request-form">
                <form action="{{ route('doctor.laboratory-requests.store', $appointment->id) }}" method="POST" id="labRequestForm"
                      data-lab-form>
                    @csrf

                    <div class="sa-lab-tests-grid">
                        @foreach(\App\Models\LaboratoryRequest::TEST_CATALOG as $labTest)
                            <label class="sa-lab-test-item">
                                <input type="checkbox" name="tests[]" value="{{ $labTest }}"
                                       {!! $labTest === 'Others' ? 'id="labTestOthersCheckbox" data-lab-others' : '' !!}
                                       {{ $labCreateOld && in_array($labTest, old('tests', []), true) ? 'checked' : '' }}>
                                <span>{{ $labTest }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="sa-form-group" id="labOtherDescGroup" data-lab-other-group style="display:none; margin-top:10px;">
                        <label class="sa-label">Other Test Description</label>
                        <input type="text" name="other_test_description" class="sa-input"
                               placeholder="Describe the other test"
                               value="{{ $labCreateOld ? old('other_test_description') : '' }}">
                    </div>

                    <div class="sa-form-group" style="margin-top:10px; margin-bottom:0;">
                        <label class="sa-label">Diagnosis</label>
                        <textarea name="lab_diagnosis" class="sa-textarea" rows="2"
                            placeholder="Diagnosis / clinical notes for this laboratory request...">{{ $labCreateOld ? old('lab_diagnosis') : '' }}</textarea>
                    </div>

                    <div class="sa-lab-request-actions">
                        <button type="submit" class="sa-btn sa-btn--primary sa-btn--sm">
                            <i class='bx bx-plus'></i>
                            Save Laboratory Request
                        </button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Delete confirmation modal + the shared JS below must render
                 unconditionally — NOT only when the create form is shown —
                 since Edit/Delete/Cancel live on the SAVED request item,
                 which is exactly the case where the create form is hidden. --}}

            {{-- Delete confirmation — reuses the layout's existing confirmation
                 dialog styling (.logout-modal) so no new modal UI is introduced. --}}
            <div class="logout-modal" id="labDeleteModal">
                <div class="logout-box">
                    <div class="logout-icon">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <h3>Delete Laboratory Request?</h3>
                    <p>This laboratory request has not been processed yet. Are you sure you want to delete it?</p>
                    <div class="logout-buttons">
                        <button type="button" class="btn-cancel" onclick="closeLabDeleteModal()">Cancel</button>
                        <button type="button" class="btn-confirm" id="labDeleteConfirmBtn">Delete</button>
                    </div>
                </div>
            </div>

            <script>
            (function () {
                // "Others" description toggle — per form, so it works for the
                // New Laboratory Request form and every Edit panel.
                document.querySelectorAll('[data-lab-form]').forEach(function (form) {
                    var othersChk  = form.querySelector('[data-lab-others]');
                    var otherGroup = form.querySelector('[data-lab-other-group]');

                    function syncOtherField() {
                        if (othersChk && otherGroup) {
                            otherGroup.style.display = othersChk.checked ? 'block' : 'none';
                        }
                    }
                    if (othersChk) othersChk.addEventListener('change', syncOtherField);
                    form.addEventListener('reset', function () { setTimeout(syncOtherField, 0); });
                    syncOtherField();

                    // Edit panels: block saving an empty request client-side
                    // (the server enforces the same rule).
                    var emptyError = form.querySelector('[data-lab-empty-error]');
                    if (emptyError) {
                        form.addEventListener('submit', function (e) {
                            if (!form.querySelector('input[name="tests[]"]:checked')) {
                                e.preventDefault();
                                emptyError.style.display = 'block';
                            }
                        });
                        form.addEventListener('change', function () {
                            emptyError.style.display = 'none';
                        });
                    }
                });

                // Edit / Cancel toggle. Cancel discards unsaved changes.
                document.querySelectorAll('[data-lab-edit-toggle]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var panel = document.getElementById(btn.dataset.labEditToggle);
                        if (!panel) return;
                        var opening = panel.style.display === 'none';
                        if (!opening) {
                            var form = panel.querySelector('form');
                            if (form) form.reset();
                            var err = panel.querySelector('[data-lab-empty-error]');
                            if (err) err.style.display = 'none';
                        }
                        panel.style.display = opening ? 'block' : 'none';
                        if (opening) panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                });

                // Delete confirmation dialog
                var labDeleteModal = document.getElementById('labDeleteModal');
                var labDeleteFormId = null;

                window.openLabDeleteModal = function (formId) {
                    labDeleteFormId = formId;
                    labDeleteModal.classList.add('active');
                };
                window.closeLabDeleteModal = function () {
                    labDeleteFormId = null;
                    labDeleteModal.classList.remove('active');
                };
                document.getElementById('labDeleteConfirmBtn').addEventListener('click', function () {
                    var form = labDeleteFormId ? document.getElementById(labDeleteFormId) : null;
                    if (form) {
                        this.disabled = true;
                        form.submit();
                    }
                });
                labDeleteModal.addEventListener('click', function (e) {
                    if (e.target === labDeleteModal) window.closeLabDeleteModal();
                });
            })();
            </script>
        </div>


        {{-- 4. PRESCRIPTION --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon"><i class='bx bx-capsule'></i></span>
                <h2 class="sa-card__title">Prescription</h2>
                @if($prescriptions->isNotEmpty())
                    <span class="sa-count-chip">{{ $prescriptions->count() }}</span>
                @endif
                @if($appointment->status === 'In Progress')
                    <div class="sa-card__header-actions">
                        <button type="button" class="sa-btn sa-btn--primary sa-btn--sm" id="addRxToggleBtn">
                            <i class='bx bx-plus'></i>
                            Add Prescription
                        </button>
                    </div>
                @endif
            </div>

            @forelse($prescriptions as $prescription)
            <div class="sa-rx-item">
                <div class="sa-rx-pill-icon">
                    <i class='bx bx-capsule'></i>
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
                        <i class='bx bx-trash'></i>
                    </button>
                </form>
                @endif
            </div>
            @empty
            <div class="sa-empty-state">
                <i class='bx bx-shield-alt-2'></i>
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
                            <i class='bx bx-plus'></i>
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
                <span class="sa-card__icon"><i class='bx bx-calendar-check'></i></span>
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
                            <i class='bx bx-check'></i>
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
                <i class='bx bx-chevron-left'></i>
                Back
            </button>
            <button type="button" class="sa-btn sa-btn--primary" data-step-target="4">
                Next: Charges & Completion
                <i class='bx bx-chevron-right'></i>
            </button>
        </div>

        </div>{{-- /step-panel 3 --}}

        <div class="step-panel {{ $saInitialStep == 4 ? 'is-active' : '' }}" data-step="4">

        {{-- 6. SERVICES / CHARGES --}}
        <div class="sa-card">
            <div class="sa-card__header">
                <span class="sa-card__icon"><i class='bx bx-receipt'></i></span>
                <h2 class="sa-card__title">Services / Charges</h2>
                @if($invoice)
                    <span class="sa-billing-badge sa-billing-badge--{{ $invoice->statusBadgeClass() }}">{{ $invoice->status }}</span>
                @endif
            </div>

            @php
                $purchasableRx    = $prescriptions->whereNotNull('medicine_id')->values();
                $consultFee       = $consultationService ? (float) $consultationService->price : 0.0;
                $waiverReasons    = config('billing.waiver_reasons', []);
                $waiverChecked    = (bool) old('fee_waiver', $currentWaiver ? 1 : 0);
                $waiverReasonVal  = old('waiver_reason', $currentWaiver['reason'] ?? '');
                $waiverNotesVal   = old('waiver_notes', $currentWaiver['notes'] ?? '');
            @endphp

            @if($invoice && $invoice->amount_paid > 0)
                <p class="sa-billing-note">
                    A payment has already been recorded for this consultation
                    (Invoice {{ $invoice->invoice_no }}) — charges can no longer be edited here.
                </p>
                <ul class="sa-billing-item-list">
                    @foreach($invoice->items as $item)
                        <li>
                            <span>
                                {{ $item->description }} @if($item->quantity > 1) × {{ $item->quantity }} @endif
                                @if($item->isWaiver())
                                    <br><small style="color:#6b7280;">Waived — {{ $item->waiverReasonLabel() }}@if($item->waiver_notes): {{ $item->waiver_notes }}@endif</small>
                                @endif
                            </span>
                            <span>
                                ₱{{ number_format($item->amount, 2) }}
                                @if($item->isWaiver())
                                    <br><small style="color:#6b7280;">−₱{{ number_format($item->discount_amount, 2) }} waived</small>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <div class="sa-billing-total">
                    <span>Total</span>
                    <span>₱{{ number_format($invoice->total_amount, 2) }}</span>
                </div>
            @else
                @if(!(auth()->user()->hasPermission('create_invoice') || auth()->user()->hasPermission('edit_invoice')))
                    {{-- No billing permission: the Consultation fee is still charged automatically on completion. --}}
                    <p class="sa-empty">
                        @if($consultationService)
                            The Consultation fee (₱{{ number_format($consultFee, 2) }}) is charged automatically when you complete the consultation.
                        @endif
                        You do not have permission to waive the fee or add other charges. Contact your Admin if you need this access.
                    </p>
                @else
                <form action="{{ route('doctor.appointments.saveCharges', $appointment->id) }}" method="POST" id="chargesForm">
                    @csrf
                    @method('PATCH')

                    {{-- CONSULTATION — automatic, never a checkbox --}}
                    <div class="sa-charge-list">
                        @if($consultationService)
                            <div class="sa-charge-row sa-charge-row--fixed">
                                <span class="sa-charge-name">
                                    {{ $consultationService->name }}
                                    <small style="color:#9ca3af;font-weight:400;">· charged automatically</small>
                                </span>
                                <span class="sa-charge-price">₱{{ number_format($consultFee, 2) }}</span>
                            </div>
                        @else
                            <p class="sa-empty">No active "Consultation" service is configured, so no consultation fee will be charged. Ask Admin to add it under Services / Fees.</p>
                        @endif
                    </div>

                    {{-- FEE WAIVER / NO CHARGE --}}
                    @if($consultationService && $consultFee > 0)
                        <div class="sa-waiver">
                            <label class="sa-waiver__toggle">
                                <input type="checkbox" name="fee_waiver" value="1" id="feeWaiverToggle" {{ $waiverChecked ? 'checked' : '' }}>
                                <span><strong>Fee Waiver / No Charge</strong> — waive the consultation fee</span>
                            </label>

                            <div class="sa-waiver__panel" id="feeWaiverPanel" style="{{ $waiverChecked ? '' : 'display:none;' }}">
                                <label class="sa-label" for="waiverReason">Waiver Reason <span class="sa-required">*</span></label>
                                <select name="waiver_reason" id="waiverReason" class="sa-input {{ $errors->has('waiver_reason') ? 'is-invalid' : '' }}">
                                    <option value="">— Select a reason —</option>
                                    @foreach($waiverReasons as $key => $reasonCfg)
                                        <option value="{{ $key }}" data-notes-required="{{ $reasonCfg['notes_required'] ? '1' : '0' }}"
                                                {{ $waiverReasonVal === $key ? 'selected' : '' }}>{{ $reasonCfg['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('waiver_reason')<p class="sa-field-error">{{ $message }}</p>@enderror

                                <label class="sa-label" for="waiverNotes" style="margin-top:10px;">
                                    Reason / Notes <span class="sa-required" id="waiverNotesStar" style="display:none;">*</span>
                                </label>
                                <textarea name="waiver_notes" id="waiverNotes" rows="2" maxlength="500"
                                          class="sa-textarea {{ $errors->has('waiver_notes') ? 'is-invalid' : '' }}"
                                          placeholder="Required when the reason is &quot;Other&quot;">{{ $waiverNotesVal }}</textarea>
                                @error('waiver_notes')<p class="sa-field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endif

                    {{-- OPTIONAL EXTRA SERVICES (everything except Consultation / Medical Certificate) --}}
                    @if($services->isNotEmpty())
                        @php $existingByService = $invoice ? $invoice->items->keyBy('service_id') : collect(); @endphp
                        <div class="sa-charge-medicines__label" style="margin:4px 0 8px;">Other services (optional)</div>
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

                    {{-- CLINIC MEDICINES — information only. A prescription is not a purchase. --}}
                    <div class="sa-charge-medicines">
                        <div class="sa-charge-medicines__label">
                            <i class='bx bx-shield-alt-2'></i>
                            Clinic Medicines — prescribed
                        </div>

                        @forelse($purchasableRx as $rx)
                            @if($rx->medicine)
                                <div class="sa-charge-row sa-charge-row--fixed">
                                    <span class="sa-charge-name">
                                        {{ $rx->medicine->medicine_name }}
                                        <small style="color:#9ca3af;font-weight:400;">· prescribed {{ $rx->quantity_prescribed }}</small>
                                    </span>
                                    <span class="sa-charge-price">
                                        @if($rx->dispense_status === 'Dispensed')
                                            Bought {{ $rx->dispensed_quantity }} of {{ $rx->quantity_prescribed }}
                                            @if($rx->invoiceItem) · ₱{{ number_format($rx->invoiceItem->amount, 2) }} @endif
                                        @else
                                            Not purchased yet
                                        @endif
                                    </span>
                                </div>
                            @endif
                        @empty
                            <p class="sa-charge-medicines__empty">No clinic-inventory medicine has been prescribed.</p>
                        @endforelse

                        <p class="sa-charge-medicines__empty" style="margin-top:8px;">
                            A prescription is not a purchase: nothing here is charged or taken from stock.
                            If the patient decides to buy a medicine from the clinic, Staff dispenses the quantity
                            actually bought, and only that quantity is added to the invoice.
                            Medicines prescribed under "Other (Manual Input)" are for an outside pharmacy and are never billed.
                        </p>
                    </div>

                    {{-- LIVE SUMMARY --}}
                    <div class="sa-charge-summary">
                        <div><span>Consultation Fee</span><span id="sumConsultFee">₱{{ number_format($consultFee, 2) }}</span></div>
                        <div id="sumWaiverRow" style="display:none;"><span>Consultation Waiver</span><span id="sumWaiver">−₱{{ number_format($consultFee, 2) }}</span></div>
                        <div><span>Net Consultation</span><span id="sumNetConsult">₱{{ number_format($consultFee, 2) }}</span></div>
                    </div>

                    <div class="sa-charge-est-total">
                        <span>Estimated Total <small style="font-weight:400;color:#9ca3af;">(before any medicine bought from the clinic)</small></span>
                        <span id="chargesEstTotal">₱0.00</span>
                    </div>

                    <button type="submit" class="sa-btn sa-btn--primary sa-btn--full">
                        <i class='bx bx-check'></i>
                        Save Charges
                    </button>
                </form>

                <script>
                (function () {
                    const form    = document.getElementById('chargesForm');
                    if (!form) return;

                    const FEE       = {{ json_encode($consultFee) }};
                    const totalEl   = document.getElementById('chargesEstTotal');
                    const toggle    = document.getElementById('feeWaiverToggle');
                    const panel     = document.getElementById('feeWaiverPanel');
                    const reasonSel = document.getElementById('waiverReason');
                    const notesEl   = document.getElementById('waiverNotes');
                    const star      = document.getElementById('waiverNotesStar');
                    const money     = n => '₱' + n.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                    function waived() { return !!(toggle && toggle.checked); }

                    function syncWaiverUi() {
                        if (!toggle) return;
                        panel.style.display = waived() ? '' : 'none';
                        const opt = reasonSel.options[reasonSel.selectedIndex];
                        const needsNotes = waived() && opt && opt.dataset.notesRequired === '1';
                        star.style.display = needsNotes ? '' : 'none';
                        if (needsNotes) { notesEl.setAttribute('required', 'required'); } else { notesEl.removeAttribute('required'); }
                        reasonSel.toggleAttribute('required', waived());
                    }

                    function recompute() {
                        let sum = 0;
                        form.querySelectorAll('.sa-charge-check').forEach(function (chk) {
                            if (!chk.checked) return;
                            const row = chk.closest('.sa-charge-row');
                            const qtyInput = row ? row.querySelector('.sa-charge-qty') : null;
                            const qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                            sum += parseFloat(chk.dataset.price || 0) * qty;
                        });

                        const net = waived() ? 0 : FEE;
                        document.getElementById('sumWaiverRow').style.display = waived() ? '' : 'none';
                        document.getElementById('sumNetConsult').textContent = money(net);
                        totalEl.textContent = money(sum + net);
                    }

                    form.querySelectorAll('.sa-charge-check, .sa-charge-qty, #feeWaiverToggle, #waiverReason').forEach(function (el) {
                        el.addEventListener('input', function () { syncWaiverUi(); recompute(); });
                        el.addEventListener('change', function () { syncWaiverUi(); recompute(); });
                    });

                    syncWaiverUi();
                    recompute();

                    // "Complete Consultation" lives in the other form. Send the waiver
                    // choice along with it so what is on screen is what gets applied,
                    // even if "Save Charges" was not pressed first.
                    const consultationForm = document.getElementById('consultationForm');
                    if (consultationForm) {
                        consultationForm.addEventListener('submit', function (e) {
                            const submitter = e.submitter ? (e.submitter.getAttribute('formaction') || '') : '';
                            if (submitter.indexOf('complete-consultation') === -1) return;

                            consultationForm.querySelectorAll('input[data-mirrored-charge]').forEach(n => n.remove());
                            const add = (name, value) => {
                                const i = document.createElement('input');
                                i.type = 'hidden'; i.name = name; i.value = value; i.dataset.mirroredCharge = '1';
                                consultationForm.appendChild(i);
                            };
                            add('charges_mirrored', '1');
                            if (waived()) {
                                add('fee_waiver', '1');
                                add('waiver_reason', reasonSel.value);
                                add('waiver_notes', notesEl.value);
                            }
                        });
                    }
                })();
                </script>
                @endif
            @endif
        </div>

        {{-- 7. BOTTOM ACTION BUTTONS --}}
        <div class="sa-step-footer sa-step-footer--last">
            <button type="button" class="sa-btn sa-btn--ghost" data-step-target="3">
                <i class='bx bx-chevron-left'></i>
                Back
            </button>
            @if($appointment->status === 'Completed')
            <a href="{{ route('doctor.medical-certificates.create', ['appointment_id' => $appointment->id]) }}"
               class="sa-btn sa-btn--ghost">
                <i class='bx bx-file-blank'></i>
                Create Medical Certificate
            </a>
            @endif
            @if(!in_array($appointment->status, ['Checked In', 'Completed'], true))
            <div class="sa-bottom-actions">
                <button type="submit"
                        form="consultationForm"
                        formaction="{{ route('doctor.appointments.completeConsultation', $appointment->id) }}"
                        class="sa-btn sa-btn--success"
                        onclick="return confirm('Complete this consultation? Once completed, it can no longer be edited.')">
                    <i class='bx bx-check'></i>
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