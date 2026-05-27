@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-show-appointment.css') }}">
@endsection

@section('content')

<div class="sa-wrapper">

    {{-- ══ HEADER ══ --}}
    <div class="sa-header">
        <div class="sa-header__left">
            <a href="{{ route('doctor.appointments.index') }}" class="sa-back-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </a>
            <div>
                <div class="sa-header__title-row">
                    <h1 class="sa-header__title">Consultation</h1>
                    @php
                        $statusMap = [
                            'Approved'    => 'approved',
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
                <p class="sa-header__sub">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                    <span class="sa-dot">·</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                </p>
            </div>
        </div>
        <a href="{{ route('doctor.appointments.report', $appointment->id) }}"
           class="sa-btn sa-btn--outline" target="_blank">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Report
        </a>
    </div>

    {{-- ══ ALERTS ══ --}}
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

    {{-- ══ MAIN GRID ══ --}}
    <div class="sa-grid">

        {{-- ── LEFT COLUMN ──────────────────────────────── --}}
        <div class="sa-col-left">

            {{-- APPOINTMENT INFO --}}
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--blue">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    <h2 class="sa-card__title">Appointment Information</h2>
                </div>

                {{-- Patient hero --}}
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

                <dl class="sa-info-list">
                    <div class="sa-info-row">
                        <dt>Date</dt>
                        <dd>{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</dd>
                    </div>
                    <div class="sa-info-row">
                        <dt>Time</dt>
                        <dd>{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</dd>
                    </div>
                    <div class="sa-info-row">
                        <dt>Reason</dt>
                        <dd>{{ $appointment->reason ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- MEDICAL INFORMATION --}}
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--teal">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9S3 16.97 3 12 7.03 3 12 3s9 4.03 9 9z"/></svg>
                    </span>
                    <h2 class="sa-card__title">Medical Information</h2>
                </div>

                @if($appointment->status === 'Completed')
                    {{-- READ-ONLY --}}
                    <div class="sa-notes-stack">
                        <div class="sa-note-block sa-note-block--complaint">
                            <div class="sa-note-label">Chief Complaint</div>
                            <div class="sa-note-content">{{ $medicalRecord->chief_complaint ?? '—' }}</div>
                        </div>
                        <div class="sa-note-block sa-note-block--diagnosis">
                            <div class="sa-note-label">Diagnosis</div>
                            <div class="sa-note-content">{{ $medicalRecord->diagnosis ?? '—' }}</div>
                        </div>
                        <div class="sa-note-block sa-note-block--treatment">
                            <div class="sa-note-label">Treatment</div>
                            <div class="sa-note-content">{{ $medicalRecord->treatment ?? '—' }}</div>
                        </div>
                        @if($medicalRecord?->notes)
                        <div class="sa-note-block sa-note-block--notes">
                            <div class="sa-note-label">Additional Notes</div>
                            <div class="sa-note-content">{{ $medicalRecord->notes }}</div>
                        </div>
                        @endif
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

                @else
                    {{-- EDITABLE FORM --}}
                    <form action="{{ route('doctor.appointments.saveDiagnosis', $appointment->id) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="sa-form-group">
                            <label class="sa-label">Chief Complaint</label>
                            <textarea name="chief_complaint" class="sa-textarea" rows="3"
                                placeholder="Patient's main complaint...">{{ old('chief_complaint', $medicalRecord->chief_complaint ?? '') }}</textarea>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Diagnosis <span class="sa-required">*</span></label>
                            <textarea name="diagnosis" class="sa-textarea" rows="4"
                                placeholder="Enter diagnosis, findings, or clinical notes...">{{ old('diagnosis', $medicalRecord->diagnosis ?? '') }}</textarea>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Treatment</label>
                            <textarea name="treatment" class="sa-textarea" rows="3"
                                placeholder="Describe the treatment plan or procedures...">{{ old('treatment', $medicalRecord->treatment ?? '') }}</textarea>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Additional Notes</label>
                            <textarea name="notes" class="sa-textarea" rows="3"
                                placeholder="Any other clinical notes or observations...">{{ old('notes', $medicalRecord->notes ?? '') }}</textarea>
                        </div>

                        <div class="sa-section-divider">
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

                        <button type="submit" class="sa-btn sa-btn--primary sa-btn--full">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Medical Record
                        </button>
                    </form>
                @endif
            </div>

            {{-- REVIEW SCHEDULE --}}
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--green">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                    </span>
                    <h2 class="sa-card__title">Review Schedule</h2>
                    @if($appointment->status !== 'Completed')
                        <span class="sa-complete-note">Submitting completes this consultation</span>
                    @endif
                </div>

                @if($appointment->status === 'Completed')
                    @if($review)
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
                @else
                    <form action="{{ route('doctor.review.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                        <div class="sa-form-group">
                            <label class="sa-label">Next Review On</label>
                            <input type="date" name="next_review_date" class="sa-input"
                                value="{{ $review->next_review_date ?? '' }}">
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Message / Notes</label>
                            <textarea name="message" class="sa-textarea" rows="4"
                                placeholder="Instructions or notes for the patient...">{{ $review->message ?? '' }}</textarea>
                        </div>
                        <div class="sa-form-actions">
                            <button type="submit" class="sa-btn sa-btn--success">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Submit &amp; Complete Consultation
                            </button>
                            <a href="{{ url()->previous() }}" class="sa-btn sa-btn--ghost">Cancel</a>
                        </div>
                    </form>
                @endif
            </div>

        </div>{{-- /col-left --}}

        {{-- ── RIGHT COLUMN ──────────────────────────────── --}}
        <div class="sa-col-right">

            {{-- PRESCRIBED MEDICINES --}}
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--purple">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </span>
                    <h2 class="sa-card__title">Prescribed Medicines</h2>
                    @if($prescriptions->isNotEmpty())
                        <span class="sa-count-chip">{{ $prescriptions->count() }}</span>
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
                    </div>
                    @if($appointment->status !== 'Completed')
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
            </div>

            {{-- ADD PRESCRIPTION --}}
            @if($appointment->status !== 'Completed')
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--orange">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </span>
                    <h2 class="sa-card__title">Add Prescription</h2>
                </div>

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

                    <button type="submit" class="sa-btn sa-btn--primary sa-btn--full">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Prescription
                    </button>
                </form>
            </div>
            @endif

        </div>{{-- /col-right --}}

    </div>{{-- /sa-grid --}}
</div>{{-- /sa-wrapper --}}

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

@endsection