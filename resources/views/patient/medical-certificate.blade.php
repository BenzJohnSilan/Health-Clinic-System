@extends('layouts.patient')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/patient-medical-certificate.css') }}">
@endsection

@section('content')

<div class="mc-wrapper">
    <div class="mc-document">

        {{-- Header --}}
        <div class="mc-header">
            <div class="mc-clinic-logo">
                <div class="mc-logo-circle">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none">
                        <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="2"/>
                        <path d="M32 16v32M16 32h32" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
            <div class="mc-clinic-info">
                <h1 class="mc-clinic-name">Health Medical Clinic</h1>
                <p class="mc-clinic-address">Brgy. Dayap Calauan, Laguna</p>
                <p class="mc-clinic-contact">Tel: (02) 8123-4567 &nbsp;|&nbsp; Email: clinic@healthmedical.com</p>
            </div>
            <div class="mc-cert-no">
                <span class="mc-cert-label">Certificate No.</span>
                <span class="mc-cert-value">{{ str_pad($appointment->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
        </div>

        <div class="mc-divider-top"></div>

        {{-- Title --}}
        <div class="mc-title-section">
            <p class="mc-title-pre">Republic of the Philippines</p>
            <h2 class="mc-title">Medical Certificate</h2>
            <div class="mc-title-ornament">
                <span></span><span class="mc-diamond">◆</span><span></span>
            </div>
        </div>

        {{-- Body --}}
        <div class="mc-body">

            <p class="mc-body-text">This is to certify that the patient whose information appears below has been examined and found to be under medical consultation as described hereunder.</p>

            <div class="mc-patient-block">
                <div class="mc-patient-row">
                    <span class="mc-field-label">Patient's Full Name</span>
                    <span class="mc-field-value mc-name">
                        {{ strtoupper($appointment->patient->last_name) }}, {{ $appointment->patient->first_name }}
                    </span>
                </div>
                <div class="mc-patient-row">
                    <span class="mc-field-label">Date of Consultation</span>
                    <span class="mc-field-value">
                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                    </span>
                </div>
                <div class="mc-patient-row">
                    <span class="mc-field-label">Attending Physician</span>
                    <span class="mc-field-value">
                        Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}
                    </span>
                </div>
                @if(!empty($appointment->diagnosis))
                <div class="mc-patient-row">
                    <span class="mc-field-label">Diagnosis / Findings</span>
                    <span class="mc-field-value">{{ $appointment->diagnosis }}</span>
                </div>
                @endif
                @if(!empty($appointment->remarks))
                <div class="mc-patient-row">
                    <span class="mc-field-label">Remarks</span>
                    <span class="mc-field-value">{{ $appointment->remarks }}</span>
                </div>
                @endif
            </div>

            <p class="mc-body-text mc-purpose">
                This certificate is issued upon the request of the patient for whatever legal purpose it may serve.
            </p>

        </div>

        {{-- Signature --}}
        <div class="mc-signature-section">
            <div class="mc-signature-block">
                <div class="mc-sig-line"></div>
                <p class="mc-sig-name">
                    Dr. {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}
                </p>
                <p class="mc-sig-title">Attending Physician</p>
                <p class="mc-sig-license">License No.: _______________</p>
                <p class="mc-sig-ptr">PTR No.: _______________</p>
            </div>
            <div class="mc-date-issued">
                <p class="mc-issued-label">Date Issued:</p>
                <p class="mc-issued-value">{{ \Carbon\Carbon::now()->format('F d, Y') }}</p>
            </div>
        </div>

        <div class="mc-divider-bottom"></div>

        <div class="mc-footer">
            <p>This document is valid only when signed and stamped with the official clinic seal.</p>
            <p>For verification, contact the clinic at (02) 8123-4567.</p>
        </div>

        <div class="mc-watermark" aria-hidden="true">OFFICIAL</div>

    </div>

    {{-- Print Button --}}
    <div class="mc-actions no-print">
        <button onclick="window.print()" class="mc-print-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            Print Certificate
        </button>
    </div>

</div>

@endsection