@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-appointment-show.css') }}">
@endsection

@section('content')

<div class="appointment-details-page">

    <a href="{{ route('staff.appointments.index') }}" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Appointments</span>
    </a>

    @php
        $patient = $appointment->walkinPatient ?? $appointment->patient;

        $fullName = trim($appointment->patientName());
        $nameParts = collect(explode(' ', $fullName))->filter();
        $initials = $nameParts->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $initials = $initials !== '' ? $initials : '?';

        $statusSlug = str_replace(' ', '-', strtolower($appointment->status));
    @endphp

    <div class="page-heading">
        <div class="page-heading-icon">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div>
            <h1>Appointment Details</h1>
            <p>View the patient's appointment and basic information.</p>
        </div>
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
                    <span class="label"><i class="fa-regular fa-calendar"></i> Appointment Date</span>
                    <span class="value">{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-clock"></i> Appointment Time</span>
                    <span class="value">{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</span>
                </div>

                <div class="detail-row">
                    <span class="label"><i class="fa-solid fa-user-doctor"></i> Doctor</span>
                    <span class="value">
                        Dr. {{ ($appointment->doctor->first_name ?? '') . ' ' . ($appointment->doctor->last_name ?? '') }}
                    </span>
                </div>
            </div>
        </section>

    </div>

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
                    <p>Review prescribed medicines and release them to the patient.</p>
                </div>
            </div>
        </div>

        <div class="prescription-list">
            @foreach($prescriptions as $prescription)
                <div class="prescription-item">
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

                            <div class="prescription-quantity">
                                <span>Qty Prescribed: <strong>{{ $prescription->quantity_prescribed }}</strong></span>
                                @if($prescription->medicine)
                                    <span>Current Stock: <strong>{{ $prescription->medicine->quantity }} {{ $prescription->medicine->unit }}</strong></span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="prescription-action">
                        @if($prescription->dispense_status === 'Dispensed')
                            <div class="dispensed-info">
                                <div class="dispensed-check">
                                    <i class="fa-solid fa-check"></i>
                                </div>
                                <div>
                                    <strong>Released to patient</strong>
                                    <span>
                                        {{ $prescription->dispensed_quantity }} unit(s)
                                        @if($prescription->dispensedBy)
                                            by {{ $prescription->dispensedBy->first_name }}
                                        @endif
                                        @if($prescription->dispensed_at)
                                            · {{ $prescription->dispensed_at->format('M d, Y h:i A') }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @elseif($canDispense)
                            <form action="{{ route('staff.prescriptions.dispense', $prescription->id) }}"
                                  method="POST"
                                  class="dispense-form"
                                  onsubmit="return confirm('Confirm releasing this medicine to the patient? Inventory will be deducted.');">
                                @csrf
                                @method('PATCH')
                                <label for="dispensed_quantity_{{ $prescription->id }}">Release Qty</label>
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

@endsection