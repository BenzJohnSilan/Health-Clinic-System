@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-show-appointment.css') }}">
@endsection

@section('content')

<div class="sa-wrapper">

    <!-- ── HEADER ────────────────────────────────────── -->
    <div class="sa-header">
        <div class="sa-header__left">
            <a href="{{ route('doctor.appointments.index') }}" class="sa-back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 5l-7 7 7 7"/></svg>
                Back
            </a>
            <div>
                <h1 class="sa-header__title">Appointment Details</h1>
                <p class="sa-header__sub">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                    &nbsp;·&nbsp;
                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                </p>
            </div>
        </div>
        <a href="{{ route('doctor.appointments.report', $appointment->id) }}"
           class="sa-btn sa-btn--outline" target="_blank">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Report
        </a>
    </div>

    <!-- ── ALERTS ─────────────────────────────────────── -->
    @if(session('success'))
        <div class="sa-alert sa-alert--success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="sa-alert sa-alert--error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- ── MAIN GRID ───────────────────────────────────── -->
    <div class="sa-grid">

        <!-- ── LEFT COLUMN ────────────────────────────── -->
        <div class="sa-col-left">

            <!-- Appointment Info -->
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--blue">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    <h2 class="sa-card__title">Appointment Information</h2>
                </div>

                <dl class="sa-info-list">
                    <div class="sa-info-row">
                        <dt>Patient</dt>
                        <dd class="sa-patient-name">
                            {{ $appointment->patient->first_name }}
                            {{ $appointment->patient->last_name }}
                        </dd>
                    </div>
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
                    <div class="sa-info-row">
                        <dt>Status</dt>
                        <dd>
                            <span class="sa-badge sa-badge--{{ strtolower($appointment->status) }}">
                                {{ $appointment->status }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Diagnosis -->
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--teal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9S3 16.97 3 12 7.03 3 12 3s9 4.03 9 9z"/></svg>
                    </span>
                    <h2 class="sa-card__title">Diagnosis</h2>
                </div>

                @if($appointment->status === 'Completed')
                    @if($appointment->diagnosis)
                        <p class="sa-diagnosis-text">{{ $appointment->diagnosis }}</p>
                    @else
                        <p class="sa-empty">No diagnosis recorded.</p>
                    @endif
                @else
                    <form action="{{ route('doctor.appointments.saveDiagnosis', $appointment->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="sa-form-group">
                            <label class="sa-label">Doctor's Diagnosis</label>
                            <textarea
                                name="diagnosis"
                                class="sa-textarea"
                                rows="5"
                                placeholder="Enter diagnosis, findings, or clinical notes...">{{ old('diagnosis', $appointment->diagnosis) }}</textarea>
                        </div>
                        <button type="submit" class="sa-btn sa-btn--primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Diagnosis
                        </button>
                    </form>
                @endif
            </div>

            <!-- Review Schedule -->
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--green">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <h2 class="sa-card__title">Review Schedule</h2>
                    @if($appointment->status !== 'Completed')
                        <span class="sa-complete-note">Submitting this completes the consultation</span>
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
                            <textarea name="message" class="sa-textarea" rows="4">{{ $review->message ?? '' }}</textarea>
                        </div>

                        <div class="sa-form-actions">
                            <button type="submit" class="sa-btn sa-btn--success">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                Submit &amp; Complete Consultation
                            </button>
                            <a href="{{ url()->previous() }}" class="sa-btn sa-btn--ghost">Cancel</a>
                        </div>
                    </form>
                @endif
            </div>

        </div><!-- /LEFT -->

        <!-- ── RIGHT COLUMN ───────────────────────────── -->
        <div class="sa-col-right">

            <!-- Prescribed Medicines -->
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--purple">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </span>
                    <h2 class="sa-card__title">Prescribed Medicines</h2>
                </div>

                @forelse($prescriptions as $prescription)
                <div class="sa-rx-item">
                    <div class="sa-rx-item__body">
                        <p class="sa-rx-item__name">
                            {{ $prescription->medicine->medicine_name
                                ?? $prescription->manual_medicine_name
                                ?? 'N/A' }}
                        </p>
                        <p class="sa-rx-item__meta">
                            <span>{{ $prescription->dosage }}</span>
                            <span class="sa-rx-sep">·</span>
                            <span>{{ $prescription->frequency }}</span>
                            <span class="sa-rx-sep">·</span>
                            <span>{{ $prescription->duration }}</span>
                            @if($prescription->quantity_prescribed)
                                <span class="sa-rx-sep">·</span>
                                <span>Qty: {{ $prescription->quantity_prescribed }}</span>
                            @endif
                        </p>
                    </div>
                    @if($appointment->status !== 'Completed')
                    <form action="{{ route('doctor.prescriptions.destroy', $prescription->id) }}"
                          method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sa-btn-icon sa-btn-icon--danger"
                            onclick="return confirm('Delete this prescription?')"
                            title="Delete prescription">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                    </form>
                    @endif
                </div>
                @empty
                <p class="sa-empty">No medicines prescribed yet.</p>
                @endforelse
            </div>

            <!-- Add Prescription -->
            @if($appointment->status !== 'Completed')
            <div class="sa-card">
                <div class="sa-card__header">
                    <span class="sa-card__icon sa-card__icon--orange">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </span>
                    <h2 class="sa-card__title">Add Prescription</h2>
                </div>

                <form action="{{ route('doctor.prescriptions.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">

                    <div class="sa-form-group">
                        <label class="sa-label">Medicine</label>
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
                            <label class="sa-label">Dosage</label>
                            <input type="text" name="dosage" id="dosageInput"
                                   class="sa-input" placeholder="e.g. 500mg" required>
                        </div>
                        <div class="sa-form-group">
                            <label class="sa-label">Frequency</label>
                            <input type="text" name="frequency" class="sa-input"
                                   placeholder="e.g. 2x a day" required>
                        </div>
                    </div>

                    <div class="sa-form-row">
                        <div class="sa-form-group">
                            <label class="sa-label">Duration</label>
                            <input type="text" name="duration" class="sa-input"
                                   placeholder="e.g. 5 days" required>
                        </div>
                        <div class="sa-form-group" id="qtyGroup">
                            <label class="sa-label">Qty to Prescribe</label>
                            <input type="number" name="quantity_prescribed" id="qtyInput"
                                   class="sa-input" min="1" required>
                        </div>
                    </div>

                    <button type="submit" class="sa-btn sa-btn--primary sa-btn--full">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Prescription
                    </button>
                </form>
            </div>
            @endif

        </div><!-- /RIGHT -->

    </div><!-- /sa-grid -->

</div><!-- /sa-wrapper -->

<script>
document.getElementById('medicineSelect')?.addEventListener('change', function () {
    const isManual = this.value === 'manual';
    const selectedOption = this.options[this.selectedIndex];
    const dosage = selectedOption.getAttribute('data-dosage') ?? '';

    // Toggle manual name field
    document.getElementById('manualMedicineField').style.display = isManual ? 'block' : 'none';

    // Auto-fill dosage from medicine data (editable by doctor)
    const dosageInput = document.getElementById('dosageInput');
    dosageInput.value = dosage;
    // Make it readonly only when a medicine is selected with a known dosage
    dosageInput.readOnly = (!isManual && dosage !== '');

    // Toggle qty required
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