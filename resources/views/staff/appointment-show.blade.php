@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-appointment-details.css') }}">
@endsection

@section('content')

<div class="appointment-details-page">

    @php
        $patient = $appointment->walkinPatient ?? $appointment->patient;

        $fullName = trim($appointment->patientName());
        $nameParts = collect(explode(' ', $fullName))->filter();
        $initials = $nameParts->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $initials = $initials !== '' ? $initials : '?';

        $statusSlug = str_replace(' ', '-', strtolower($appointment->status));
    @endphp

    @if(session('success'))
        <div class="alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ================= HERO (same banner style as the Dashboard) ================= --}}
    <div class="page-hero">
        <div class="page-hero-group">
            <div class="hero-icon-badge">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <h1>Appointment Details</h1>
                <p>View the patient's appointment and basic information.</p>
            </div>
        </div>

        <a href="{{ route('staff.appointments.index') }}" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Appointments</span>
        </a>
    </div>

    <div class="details-columns">

        {{-- ================= PATIENT INFORMATION ================= --}}
        <section class="details-card patient-card">
            <div class="card-header">
                <div class="card-title-wrap">
                    <div class="card-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div>
                        <h2>Patient</h2>
                        <p>Basic information of the patient</p>
                    </div>
                </div>
            </div>

            <div class="identity-block">
                <div class="avatar">{{ $initials }}</div>
                <div class="identity-text">
                    <span class="identity-name">{{ $appointment->patientName() }}</span>
                    <span class="patient-type-badge {{ $appointment->walkin_patient_id ? 'walkin' : 'registered' }}">
                        <i class="fa-solid {{ $appointment->walkin_patient_id ? 'fa-shoe-prints' : 'fa-id-card' }}"></i>
                        {{ $appointment->walkin_patient_id ? 'Walk-in' : 'Registered' }}
                    </span>
                </div>
            </div>

            <div class="detail-list">
                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-cake-candles"></i> Age</span>
                    <span class="value">{{ $patient->age ?? '-' }}</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-venus-mars"></i> Gender</span>
                    <span class="value">{{ $patient->gender ?? '-' }}</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-phone"></i> Contact Number</span>
                    <span class="value">{{ $patient->contact_number ?? '-' }}</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-location-dot"></i> Address</span>
                    <span class="value">{{ $patient->address ?? '-' }}</span>
                </div>
            </div>
        </section>

        {{-- ================= APPOINTMENT INFORMATION ================= --}}
        <section class="details-card schedule-card">
            <div class="card-header">
                <div class="card-title-wrap">
                    <div class="card-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <h2>Appointment Schedule</h2>
                        <p>Schedule and appointment details</p>
                    </div>
                </div>

                <span class="status {{ $statusSlug }}">
                    <i class="fa-solid fa-circle"></i>
                    {{ $appointment->status }}
                </span>
            </div>

            <div class="detail-list">
                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-hashtag"></i> Reference No.</span>
                    <span class="value">{{ $appointment->reference_no ?? '-' }}</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-regular fa-clock"></i> Requested On</span>
                    <span class="value">{{ $appointment->created_at?->format('F d, Y g:i A') ?? '-' }}</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-regular fa-calendar"></i> {{ $appointment->isWalkIn() ? 'Visit Date' : 'Appointment Date' }}</span>
                    <span class="value">{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</span>
                </div>

                @if($appointment->isWalkIn())
                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-person-walking"></i> Visit Type</span>
                    <span class="value">Walk-in &middot; Queue {{ $appointment->queue_number }} &middot; no reserved appointment time</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-clock"></i> Arrival</span>
                    <span class="value">{{ $appointment->arrived_at?->format('h:i A') ?? '-' }}</span>
                </div>
                @else
                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-clock"></i> Appointment Time</span>
                    <span class="value">{{ $appointment->timeLabel('h:i A') }}</span>
                </div>
                @endif

                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-user-doctor"></i> Doctor</span>
                    <span class="value">
                        Dr. {{ ($appointment->doctor->first_name ?? '') . ' ' . ($appointment->doctor->last_name ?? '') }}
                    </span>
                </div>
            </div>
        </section>

    </div>


    {{-- ================= VITAL SIGNS ================= --}}
    @php
        $vitalsEditable = $canRecordVitals
            && in_array($appointment->status, ['Approved', 'Rescheduled', 'Checked In'], true);
        $vitalsVisible  = $vitalsEditable
            || (!in_array($appointment->status, ['Pending', 'Cancelled', 'Rejected', 'No Show'], true));

        $vitalMeta       = \App\Models\MedicalRecord::VITALS;
        $notTakenSaved   = $medicalRecord?->vitals_not_taken ?? [];
        $hasOldInput     = session()->hasOldInput();
        $vitalsRecorded  = $medicalRecord && $medicalRecord->vitals_recorded_at;
        $canCheckInNow   = $canCheckIn && $appointment->canBeCheckedIn();
    @endphp

    @if($vitalsVisible)
    <section class="vitals-card" id="vital-signs">
        <div class="section-header">
            <div class="section-title-wrap">
                <div class="section-icon">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
                <div>
                    <h2>Vital Signs</h2>
                    <p>
                        @if($vitalsEditable)
                            Record the patient's vital signs before check-in. The Doctor will see them read-only.
                        @elseif($appointment->status === 'Waiting')
                            Check in this walk-in patient first, then record the vital signs here.
                        @else
                            Vital signs recorded for this appointment.
                        @endif
                    </p>
                </div>
            </div>

            @if($vitalsRecorded)
                <span class="vitals-saved-badge">
                    <i class="fa-solid fa-circle-check"></i>
                    Saved {{ $medicalRecord->vitals_recorded_at->format('M d, g:i A') }}
                </span>
            @elseif($vitalsEditable)
                <span class="vitals-saved-badge vitals-saved-badge--pending">
                    <i class="fa-regular fa-clock"></i>
                    Not recorded yet
                </span>
            @endif
        </div>

        @if($vitalsEditable)
            <form action="{{ route('staff.appointments.vitalSigns.save', $appointment->id) }}"
                  method="POST" class="vitals-form" id="vitals-form" novalidate>
                @csrf

                @if($errors->any())
                    <div class="alert-error vitals-errors" role="alert">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="vitals-grid">
                    @foreach($vitalMeta as $key => $meta)
                        @php
                            $savedNotTaken = $notTakenSaved[$key] ?? null;
                            $isNotTaken = $hasOldInput
                                ? (bool) old("not_taken.$key")
                                : (bool) $savedNotTaken;
                            $reasonVal  = $hasOldInput ? old("reason.$key") : ($savedNotTaken['reason'] ?? '');
                            $otherVal   = $hasOldInput ? old("reason_other.$key") : ($savedNotTaken['note'] ?? '');
                            $value      = $hasOldInput ? old("vital.$key") : ($medicalRecord->{$key} ?? '');
                            $isBp       = $key === 'blood_pressure';
                        @endphp

                        <div class="vital-field {{ $isNotTaken ? 'is-not-taken' : '' }}" data-vital="{{ $key }}">
                            <div class="vital-field-head">
                                <label for="vital_{{ $key }}">{{ $meta['label'] }}</label>
                                <label class="not-taken-toggle">
                                    <input type="checkbox"
                                           name="not_taken[{{ $key }}]"
                                           value="1"
                                           class="js-not-taken"
                                           {{ $isNotTaken ? 'checked' : '' }}>
                                    <span>Not taken</span>
                                </label>
                            </div>

                            <div class="vital-input-group">
                                @if($isBp)
                                    <input type="text" id="vital_{{ $key }}" name="vital[{{ $key }}]"
                                           value="{{ $isNotTaken ? '' : $value }}"
                                           placeholder="{{ $meta['placeholder'] }}"
                                           inputmode="numeric" autocomplete="off" maxlength="7"
                                           pattern="\d{2,3}/\d{2,3}"
                                           {{ $isNotTaken ? 'disabled' : '' }}>
                                @else
                                    <input type="number" id="vital_{{ $key }}" name="vital[{{ $key }}]"
                                           value="{{ $isNotTaken ? '' : $value }}"
                                           placeholder="{{ $meta['placeholder'] }}"
                                           inputmode="decimal" step="any" min="0"
                                           {{ $isNotTaken ? 'disabled' : '' }}>
                                @endif
                                <span class="vital-unit" aria-hidden="true">{{ $meta['unit'] }}</span>
                            </div>

                            <div class="vital-reason" {{ $isNotTaken ? '' : 'hidden' }}>
                                <select name="reason[{{ $key }}]" class="js-reason"
                                        aria-label="{{ $meta['label'] }} — reason not taken"
                                        {{ $isNotTaken ? '' : 'disabled' }}>
                                    <option value="">Select reason…</option>
                                    @foreach(\App\Models\MedicalRecord::NOT_TAKEN_REASONS as $r)
                                        <option value="{{ $r }}" {{ $reasonVal === $r ? 'selected' : '' }}>{{ $r }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="reason_other[{{ $key }}]"
                                       class="js-reason-other"
                                       value="{{ $otherVal }}" maxlength="120"
                                       placeholder="Please specify"
                                       aria-label="{{ $meta['label'] }} — other reason"
                                       {{ ($isNotTaken && $reasonVal === 'Other') ? '' : 'hidden' }}
                                       {{ $isNotTaken ? '' : 'disabled' }}>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="vitals-actions">
                    <p class="vitals-hint">
                        <i class="fa-solid fa-circle-info"></i>
                        Units are fixed. Mark a measurement “Not taken” with a reason instead of entering a placeholder value.
                    </p>

                    <div class="vitals-buttons">
                        <button type="submit" class="vitals-btn vitals-btn--primary">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Vital Signs
                        </button>

                        @if($canCheckInNow)
                            <button type="submit"
                                    form="vitals-checkin-form"
                                    class="vitals-btn vitals-btn--secondary"
                                    data-vitals-saved="{{ $vitalsRecorded ? '1' : '0' }}">
                                <i class="fa-solid fa-clipboard-check"></i>
                                Check In Patient
                            </button>
                        @endif
                    </div>
                </div>
            </form>

            {{-- Separate form (forms cannot nest) — posts to the EXISTING check-in route. --}}
            @if($canCheckInNow)
                <form action="{{ route('staff.appointments.checkIn', $appointment->id) }}"
                      method="POST" id="vitals-checkin-form">
                    @csrf
                </form>
            @endif
        @else
            @if($appointment->status === 'Waiting' && $canCheckInNow)
                <form action="{{ route('staff.appointments.checkIn', $appointment->id) }}" method="POST" style="margin:0 0 14px;">
                    @csrf
                    <button type="submit" class="vitals-btn vitals-btn--secondary">
                        <i class="fa-solid fa-clipboard-check"></i>
                        Check In Patient
                    </button>
                </form>
            @endif
            @php $vitalRows = $medicalRecord ? $medicalRecord->vitalsForDisplay() : (new \App\Models\MedicalRecord())->vitalsForDisplay(); @endphp
            <div class="vitals-grid vitals-grid--readonly">
                @foreach($vitalRows as $row)
                    <div class="vital-readonly">
                        <span class="vital-readonly-label">{{ $row['label'] }}</span>
                        @if($row['status'] === 'recorded')
                            <span class="vital-readonly-value">{{ $row['value'] }}@if($row['unit']) <small>{{ $row['unit'] }}</small>@endif</span>
                        @elseif($row['status'] === 'not_taken')
                            <span class="vital-readonly-value is-missing">Not taken <small>{{ $row['reason'] }}</small></span>
                        @else
                            <span class="vital-readonly-value is-missing">Not recorded</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
    @endif

    {{-- ================= PRESCRIPTIONS & DISPENSING ================= --}}
    @if($appointment->status === 'Completed' && $prescriptions->isNotEmpty())
    <section class="prescriptions-card">
        <div class="section-header">
            <div class="section-title-wrap">
                <div class="section-icon">
                    <i class="fa-solid fa-pills"></i>
                </div>
                <div>
                    <h2>Prescriptions &amp; Dispensing</h2>
                    <p>A prescription is not a purchase. Dispense only what the patient actually buys from the clinic — that quantity is deducted from stock and added to the invoice.</p>
                </div>
            </div>
        </div>

        <div class="prescription-list">
            @foreach($prescriptions as $prescription)
                <div class="prescription-item">

                    {{-- Medicine --}}
                    <div class="prescription-main">
                        <div class="medicine-icon">
                            <i class="fa-solid fa-capsules"></i>
                        </div>

                        <div class="medicine-info">
                            <div class="medicine-name">
                                {{ $prescription->medicine->medicine_name ?? $prescription->manual_medicine_name ?? 'N/A' }}
                            </div>

                            <div class="prescription-meta">
                                <span>{{ $prescription->dosage ?: 'Dosage not specified' }}</span>
                                <span class="meta-dot">•</span>
                                <span>{{ $prescription->frequency ?: 'Frequency not specified' }}</span>
                                <span class="meta-dot">•</span>
                                <span>{{ $prescription->duration ?: 'Duration not specified' }}</span>
                            </div>

                            @if($prescription->instructions)
                                <div class="prescription-instructions">
                                    <i class="fa-solid fa-circle-info"></i>
                                    {{ $prescription->instructions }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Quantities --}}
                    <div class="prescription-stats">
                        <div class="stat-tile">
                            <span class="stat-label">Qty Prescribed</span>
                            <span class="stat-value">{{ $prescription->quantity_prescribed }}</span>
                        </div>

                        @if($prescription->medicine)
                            <div class="stat-tile">
                                <span class="stat-label">Current Stock</span>
                                <span class="stat-value">{{ $prescription->medicine->quantity }} <small>{{ $prescription->medicine->unit }}</small></span>
                            </div>
                        @endif
                    </div>

                    {{-- Action / status --}}
                    <div class="prescription-action">
                        @if($prescription->dispense_status === 'Dispensed')
                            <div class="dispensed-info">
                                <div class="dispensed-check">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <div class="dispensed-text">
                                    <strong>Released to patient</strong>
                                    <span class="dispensed-line">
                                        {{ $prescription->dispensed_quantity }} of {{ $prescription->quantity_prescribed }} prescribed unit(s)
                                    </span>
                                    @if($prescription->invoiceItem)
                                        <span class="dispensed-line">
                                            Billed <b>₱{{ number_format($prescription->invoiceItem->amount, 2) }}</b>
                                            on {{ $prescription->invoiceItem->invoice->invoice_no ?? 'invoice' }}
                                        </span>
                                    @endif
                                    @if($prescription->dispensedBy || $prescription->dispensed_at)
                                        <span class="dispensed-line dispensed-line--muted">
                                            @if($prescription->dispensedBy)by {{ $prescription->dispensedBy->first_name }}@endif
                                            @if($prescription->dispensedBy && $prescription->dispensed_at) · @endif
                                            @if($prescription->dispensed_at){{ $prescription->dispensed_at->format('M d, Y h:i A') }}@endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @elseif($prescription->isOutsidePharmacy())
                            {{-- "Other (Manual Input)": bought at an outside pharmacy. Never dispensed, stocked or billed by the clinic. --}}
                            <div class="dispensed-info dispensed-info--outside">
                                <div class="dispensed-check">
                                    <i class="fa-solid fa-shop"></i>
                                </div>
                                <div class="dispensed-text">
                                    <strong>Outside pharmacy</strong>
                                    <span class="dispensed-line">The patient buys this elsewhere — not dispensed or billed by the clinic.</span>
                                </div>
                            </div>
                        @elseif($canDispense)
                            <form action="{{ route('staff.prescriptions.dispense', $prescription->id) }}"
                                  method="POST"
                                  class="dispense-form"
                                  onsubmit="return confirm('Dispense this quantity? It will be deducted from inventory and added to the invoice.');">
                                @csrf
                                @method('PATCH')
                                <label for="dispensed_quantity_{{ $prescription->id }}">Qty Patient Is Buying</label>
                                <div class="dispense-controls">
                                    <input id="dispensed_quantity_{{ $prescription->id }}"
                                           type="number"
                                           name="dispensed_quantity"
                                           value="{{ $prescription->quantity_prescribed }}"
                                           min="1"
                                           max="{{ $prescription->quantity_prescribed }}"
                                           required>
                                    <button type="submit" class="dispense-btn">
                                        <i class="fa-solid fa-hand-holding-medical"></i>
                                        Dispense
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ================= REASON FOR APPOINTMENT ================= --}}
    <section class="reason-card">
        <div class="reason-icon">
            <i class="fa-solid fa-notes-medical"></i>
        </div>
        <div class="reason-body">
            <span class="reason-label">Reason for Appointment</span>
            <p class="reason-text">{{ $appointment->reason ?? 'No reason provided.' }}</p>
        </div>
    </section>

</div>

<script>
(function () {
    var form = document.getElementById('vitals-form');
    if (!form) return;

    function sync(field) {
        var box    = field.querySelector('.js-not-taken');
        var input  = field.querySelector('.vital-input-group input');
        var reason = field.querySelector('.vital-reason');
        var select = field.querySelector('.js-reason');
        var other  = field.querySelector('.js-reason-other');
        var off    = box.checked;

        field.classList.toggle('is-not-taken', off);
        input.disabled = off;
        if (off) input.value = '';

        reason.hidden   = !off;
        select.disabled = !off;
        var showOther = off && select.value === 'Other';
        other.hidden   = !showOther;
        other.disabled = !off;
    }

    form.querySelectorAll('.vital-field').forEach(function (field) {
        field.querySelector('.js-not-taken').addEventListener('change', function () { sync(field); });
        field.querySelector('.js-reason').addEventListener('change', function () { sync(field); });
        sync(field);
    });

    var dirty = false;
    form.addEventListener('input',  function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });

    // Encourage (never block) recording vitals before Check In.
    var checkInBtn = document.querySelector('[data-vitals-saved]');
    if (checkInBtn) {
        checkInBtn.addEventListener('click', function (e) {
            var msg;
            if (dirty) {
                msg = 'You have unsaved changes to the vital signs. Click Cancel and press "Save Vital Signs" first, or OK to check in without saving them.';
            } else if (checkInBtn.getAttribute('data-vitals-saved') === '1') {
                msg = 'Check in this patient? This confirms they have arrived at the clinic.';
            } else {
                msg = 'Vital signs have not been saved for this appointment yet.\n\nCheck in anyway? (You can still record them afterwards.)';
            }
            if (!confirm(msg)) e.preventDefault();
        });
    }
})();
</script>

@endsection