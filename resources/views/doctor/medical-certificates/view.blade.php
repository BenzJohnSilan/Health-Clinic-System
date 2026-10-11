@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-certificate-show.css') }}">
@endsection

@section('content')

@php
    $mc          = $medicalCertificate;
    $hasContent  = $mc->isIssued() || $mc->isReleased();
    $statusLabel = $mc->isCorrectionRequested() ? 'Correction Requested' : ucfirst($mc->status);
    $statusIcon  = match (true) {
        $mc->isReleased()            => 'bx-check-double',
        $mc->isIssued()              => 'bx-check-circle',
        $mc->isCorrectionRequested() => 'bx-edit',
        $mc->status === 'rejected'   => 'bx-x-circle',
        default                      => 'bx-time-five',
    };

    $invoice       = $mc->invoice;
    $invoiceStatus = $invoice ? str_replace('_', ' ', strtolower($invoice->status)) : null;
    $invoicePillCl = $invoiceStatus === 'paid' ? 'status-pill--green' : 'status-pill--amber';

    $appointmentDate = optional($mc->appointment)->appointment_date
        ? \Carbon\Carbon::parse($mc->appointment->appointment_date)->format('F d, Y')
        : '—';

    $restPeriod = ($mc->rest_start_date && $mc->rest_end_date)
        ? $mc->rest_start_date->format('F d, Y') . ' to ' . $mc->rest_end_date->format('F d, Y')
        : null;
@endphp

<a href="{{ route('doctor.medical-certificates.index') }}" class="mc-back">
    <i class='bx bx-arrow-back'></i> Back to Medical Certificates
</a>

<!-- ================= HERO ================= -->
<div class="mc-hero">
    <div class="mc-hero-left">
        <div class="mc-hero-badge"><i class='bx bx-file-blank'></i></div>
        <div>
            <h2 class="mc-hero-title">Medical Certificate</h2>
            <p class="mc-hero-subtitle">
                {{ $mc->certificate_number ?? 'No certificate number yet' }}
                @if($mc->resolvedPatientName()) · {{ $mc->resolvedPatientName() }} @endif
            </p>
        </div>
    </div>

    <span class="mc-hero-status {{ $mc->status }}">
        <i class='bx {{ $statusIcon }}'></i> {{ $statusLabel }}
    </span>
</div>

@if(session('success'))
    <div class="mc-alert mc-alert--success">
        <i class='bx bx-check-circle'></i> {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mc-alert mc-alert--error">
        <i class='bx bx-error-circle'></i> {{ session('error') }}
    </div>
@endif

<div class="mc-grid {{ $hasContent ? '' : 'mc-grid--single' }}">

    <!-- ================= MAIN COLUMN ================= -->
    <div class="mc-main">

        <!-- ============ CERTIFICATE DETAILS ============ -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="info-card-icon"><i class='bx bx-id-card'></i></div>
                    <h3>Certificate Details</h3>
                </div>
            </div>

            <div class="mc-detail-list">
                <div class="mc-detail-row">
                    <span class="mc-detail-label">Certificate No.</span>
                    <span class="mc-detail-value mc-detail-value--mono">{{ $mc->certificate_number ?? '—' }}</span>
                </div>
                <div class="mc-detail-row">
                    <span class="mc-detail-label">Patient</span>
                    <span class="mc-detail-value">{{ $mc->resolvedPatientName() }}</span>
                </div>
                <div class="mc-detail-row">
                    <span class="mc-detail-label">Appointment Date</span>
                    <span class="mc-detail-value">{{ $appointmentDate }}</span>
                </div>
                <div class="mc-detail-row">
                    <span class="mc-detail-label">Purpose</span>
                    <span class="mc-detail-value">{{ $mc->displayPurpose() }}</span>
                </div>
                @if($mc->request_details)
                <div class="mc-detail-row">
                    <span class="mc-detail-label">Additional Details</span>
                    <span class="mc-detail-value mc-pre">{{ $mc->request_details }}</span>
                </div>
                @endif
            </div>
        </div>

        @if($hasContent)

            <!-- ============ ISSUED CERTIFICATE CONTENT ============ -->
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-title">
                        <div class="info-card-icon"><i class='bx bx-file-blank'></i></div>
                        <h3>Issued Certificate Content</h3>
                    </div>
                </div>

                <div class="mc-detail-list">
                    <div class="mc-detail-row">
                        <span class="mc-detail-label">Final Diagnosis</span>
                        <span class="mc-detail-value mc-pre">{{ $mc->medical_statement }}</span>
                    </div>
                    <div class="mc-detail-row">
                        <span class="mc-detail-label">Remarks</span>
                        <span class="mc-detail-value mc-pre">{{ $mc->recommendation }}</span>
                    </div>
                    @if($restPeriod)
                    <div class="mc-detail-row">
                        <span class="mc-detail-label">Rest Period</span>
                        <span class="mc-detail-value">{{ $restPeriod }}</span>
                    </div>
                    @endif
                    @if($mc->additional_remarks)
                    <div class="mc-detail-row">
                        <span class="mc-detail-label">Additional Remarks</span>
                        <span class="mc-detail-value mc-pre">{{ $mc->additional_remarks }}</span>
                    </div>
                    @endif
                </div>
            </div>

            @if($mc->latestCorrection && $mc->latestCorrection->isRejected())
                <!-- ============ PREVIOUS CORRECTION REQUEST ============ -->
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <div class="info-card-icon info-card-icon--red"><i class='bx bx-history'></i></div>
                            <h3>Previous Correction Request</h3>
                        </div>
                    </div>

                    <div class="mc-detail-list">
                        <div class="mc-detail-row">
                            <span class="mc-detail-label">Reason Given</span>
                            <span class="mc-detail-value mc-pre">{{ $mc->latestCorrection->reason }}</span>
                        </div>
                    </div>

                    <div class="mc-rejection">
                        <strong>This correction request was rejected.</strong>
                        @if($mc->latestCorrection->rejection_reason)
                            <br>{{ $mc->latestCorrection->rejection_reason }}
                        @endif
                    </div>
                </div>
            @endif

        @endif

    </div>

    @if($hasContent)
    <!-- ================= SIDE COLUMN ================= -->
    <aside class="mc-side">

        <!-- ============ BILLING & RELEASE ============ -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="info-card-icon"><i class='bx bx-receipt'></i></div>
                    <h3>Billing &amp; Release</h3>
                </div>
            </div>

            <div class="mc-side-row">
                <span class="mc-detail-label">Invoice</span>
                <span class="mc-detail-value">
                    @if($invoice)
                        <span>{{ $invoice->invoice_no }}</span>
                        <span class="status-pill {{ $invoicePillCl }}">{{ $invoiceStatus }}</span>
                    @else
                        <span>No invoice</span>
                    @endif
                </span>
            </div>
            <div class="mc-side-row">
                <span class="mc-detail-label">Issued On</span>
                <span class="mc-detail-value">{{ $mc->issued_at?->format('F d, Y g:i A') ?? '—' }}</span>
            </div>
            @if($mc->isReleased())
            <div class="mc-side-row">
                <span class="mc-detail-label">Released On</span>
                <span class="mc-detail-value">{{ $mc->released_at?->format('F d, Y g:i A') ?? '—' }}</span>
            </div>
            @endif
        </div>

        @if($mc->isIssued())
        <!-- ============ PRINT ============ -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="info-card-icon"><i class='bx bx-printer'></i></div>
                    <h3>Print Certificate</h3>
                </div>
            </div>

            @if($mc->canBePrinted())
                <a href="{{ route('doctor.medical-certificates.print', $mc->id) }}"
                   target="_blank" class="mc-btn mc-btn--primary">
                    <i class='bx bx-printer'></i> Print Medical Certificate
                </a>
                <p class="mc-note">
                    <i class='bx bx-info-circle'></i>
                    <span>Printing does not release the certificate. Sign the printed copy by hand, then Staff releases it.</span>
                </p>
            @else
                <p class="mc-note mc-note--warn">
                    <i class='bx bx-time-five'></i>
                    <span>This certificate can be printed once its invoice is fully paid at the clinic counter.</span>
                </p>
            @endif
        </div>
        @endif

    </aside>
    @endif

</div>

@endsection