@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-certificates.css') }}">
@endsection

@section('content')

<div class="container">

    <a href="{{ route('doctor.medical-certificates.index') }}" class="back-link">
        <i class='bx bx-arrow-back'></i> Back to Medical Certificates
    </a>

    <div class="page-header">
        <h1 class="page-title">Medical Certificate</h1>
        <span class="status {{ $medicalCertificate->status }}">
            {{ $medicalCertificate->isCorrectionRequested() ? 'Correction Requested' : ucfirst($medicalCertificate->status) }}
        </span>
    </div>

    <div class="mc-card">
        <h3><i class='bx bx-id-card'></i> Request Details</h3>

        <div class="detail-row">
            <span class="detail-label">Certificate No.</span>
            <span class="detail-value">{{ $medicalCertificate->certificate_number ?? '—' }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Patient</span>
            <span class="detail-value">
                {{ $medicalCertificate->resolvedPatientName() }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Appointment Date</span>
            <span class="detail-value">
                {{ optional($medicalCertificate->appointment)->appointment_date
                    ? \Carbon\Carbon::parse($medicalCertificate->appointment->appointment_date)->format('F d, Y')
                    : '—' }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Purpose</span>
            <span class="detail-value">{{ $medicalCertificate->displayPurpose() }}</span>
        </div>
    </div>

    @if($medicalCertificate->isIssued())
        <div class="mc-card">
            <h3><i class='bx bx-file-blank'></i> Issued Certificate Content</h3>

            <div class="detail-row">
                <span class="detail-label">Medical Statement</span>
                <span class="detail-value">{{ $medicalCertificate->medical_statement }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Recommendation</span>
                <span class="detail-value">{{ $medicalCertificate->recommendation }}</span>
            </div>
            @if($medicalCertificate->rest_start_date && $medicalCertificate->rest_end_date)
            <div class="detail-row">
                <span class="detail-label">Rest Period</span>
                <span class="detail-value">
                    {{ $medicalCertificate->rest_start_date->format('F d, Y') }} to {{ $medicalCertificate->rest_end_date->format('F d, Y') }}
                </span>
            </div>
            @endif
            @if($medicalCertificate->additional_remarks)
            <div class="detail-row">
                <span class="detail-label">Additional Remarks</span>
                <span class="detail-value">{{ $medicalCertificate->additional_remarks }}</span>
            </div>
            @endif
            <div class="detail-row">
                <span class="detail-label">Issued On</span>
                <span class="detail-value">{{ $medicalCertificate->issued_at?->format('F d, Y g:i A') ?? '—' }}</span>
            </div>

            <div class="mc-actions" style="margin-top:18px;">
                <a href="{{ route('doctor.medical-certificates.print', $medicalCertificate->id) }}"
                   target="_blank" class="btn-create">
                    <i class='bx bx-printer'></i> Print Medical Certificate
                </a>
            </div>
        </div>

        @if($medicalCertificate->latestCorrection && $medicalCertificate->latestCorrection->isRejected())
            <div class="mc-card">
                <h3><i class='bx bx-history'></i> Previous Correction Request</h3>
                <div class="detail-row">
                    <span class="detail-label">Reason Given</span>
                    <span class="detail-value">{{ $medicalCertificate->latestCorrection->reason }}</span>
                </div>
                <div class="rejection-box">
                    <strong>This correction request was rejected.</strong>
                    @if($medicalCertificate->latestCorrection->rejection_reason)
                        <br>{{ $medicalCertificate->latestCorrection->rejection_reason }}
                    @endif
                </div>
            </div>
        @endif
    @elseif($medicalCertificate->isRejected())
        <div class="mc-card">
            <h3><i class='bx bx-x-circle'></i> Rejection Details</h3>
            <div class="rejection-box">
                {{ $medicalCertificate->rejection_reason }}
            </div>
            <div class="detail-row" style="margin-top:10px;">
                <span class="detail-label">Rejected On</span>
                <span class="detail-value">{{ $medicalCertificate->rejected_at?->format('F d, Y g:i A') ?? '—' }}</span>
            </div>
        </div>
    @endif

</div>

@endsection
