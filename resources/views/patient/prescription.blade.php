@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-prescription.css') }}">
<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
@endsection

@section('content')

{{-- ── Action bar (hidden on print) ── --}}
<div class="rx-action-bar no-print">
    <button class="btn-rx-print" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" width="15" height="15">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M9 16h6v5H9v-5z"/>
        </svg>
        Print / Save as PDF
    </button>
</div>

{{-- ══════════════════════════════════════════
     PRESCRIPTION SLIP  (this is what prints)
══════════════════════════════════════════ --}}
<div class="rx-page" id="rx-printable">

    {{-- ── CLINIC HEADER ── --}}
    <div class="rx-clinic-header">
        <div class="rx-clinic-name">Medical Clinic</div>
        <div class="rx-clinic-specialty">General Practice</div>
        <div class="rx-clinic-address">
            {{-- Put your clinic address here --}}
            Brgy. Dayap Calauan, Laguna
        </div>

        <div class="rx-clinic-hours">
            <span>Mon – Sat &nbsp;9:00 AM – 12:00 PM &nbsp;|&nbsp; 1:00 – 5:00 PM</span>
            <span>Sun – Closed</span>
        </div>
    </div>

    <div class="rx-divider-thick"></div>

    {{-- ── PATIENT INFO ROW ── --}}
    <div class="rx-patient-row">
        <div class="rx-patient-field">
            <span class="rx-field-label">Patient Name:</span>
            <span class="rx-field-value rx-field-underline">
                {{ $appointment->patient->first_name }}
                {{ $appointment->patient->middle_name ? $appointment->patient->middle_name . ' ' : '' }}
                {{ $appointment->patient->last_name }}
                {{ $appointment->patient->suffix ? ', ' . $appointment->patient->suffix : '' }}
            </span>
        </div>
        <div class="rx-patient-field rx-field-short">
            <span class="rx-field-label">Date:</span>
            <span class="rx-field-value rx-field-underline">
                {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('m/d/Y') }}
            </span>
        </div>
    </div>

    <div class="rx-patient-row rx-patient-row--second">
        <div class="rx-patient-field">
            <span class="rx-field-label">Address:</span>
            <span class="rx-field-value rx-field-underline">
                {{ $appointment->patient->address ?? '' }}
            </span>
        </div>
        <div class="rx-patient-field rx-field-tiny">
            <span class="rx-field-label">Age:</span>
            <span class="rx-field-value rx-field-underline">
                {{ $appointment->patient->birthdate
                    ? \Carbon\Carbon::parse($appointment->patient->birthdate)->age
                    : '' }}
            </span>
        </div>
        <div class="rx-patient-field rx-field-tiny">
            <span class="rx-field-label">Sex:</span>
            <span class="rx-field-value rx-field-underline">
                {{ $appointment->patient->gender ?? '' }}
            </span>
        </div>
    </div>

    <div class="rx-divider-thin"></div>

    {{-- ── Rx BODY ── --}}
    <div class="rx-body">

        {{-- Big Rx symbol --}}
        <div class="rx-symbol">&#8478;</div>

        {{-- Prescriptions list --}}
        <div class="rx-drugs">
            @forelse($prescriptions as $prescription)
                <div class="rx-drug-item">
                    <div class="rx-drug-name">
                        {{ $prescription->medicine?->medicine_name
                            ?? $prescription->manual_medicine_name
                            ?? 'N/A' }}
                        @if($prescription->dosage)
                            <span class="rx-drug-dosage">{{ $prescription->dosage }}</span>
                        @endif
                        @if($prescription->quantity_prescribed)
                            &nbsp;#{{ $prescription->quantity_prescribed }}
                        @endif
                    </div>
                    <div class="rx-drug-sig">
                        <span class="rx-sig-label">Sig:</span>
                        {{ $prescription->frequency ?? '' }}
                        @if($prescription->duration)
                            &nbsp;{{ $prescription->duration }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="rx-no-drugs">No prescriptions recorded.</div>
            @endforelse
        </div>

    </div>{{-- /.rx-body --}}

    <div class="rx-divider-thin"></div>

    {{-- ── FOOTER: signature + follow-up ── --}}
    <div class="rx-footer-row">

        <div class="rx-followup-block">
            <span class="rx-field-label">Follow-up on:</span>
            <span class="rx-field-underline rx-followup-date">
                @if($review && $review->next_review_date)
                    {{ \Carbon\Carbon::parse($review->next_review_date)->format('m/d/Y') }}
                @endif
            </span>
        </div>

        <div class="rx-sig-block">
            {{-- Signature placeholder line --}}
            <div class="rx-sig-line"></div>
            <div class="rx-sig-name">
                Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}, MD
            </div>
            <div class="rx-sig-meta">
                Lic. No. {{ $appointment->doctor->license_number ?? '___________' }}
            </div>
            <div class="rx-sig-meta">
                PTR No. {{ $appointment->doctor->ptr_number ?? '___________' }}
            </div>
        </div>

    </div>{{-- /.rx-footer-row --}}

</div>{{-- /#rx-printable --}}

@endsection