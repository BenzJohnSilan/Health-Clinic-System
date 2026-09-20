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
        <h1 class="page-title">Correction Request</h1>
        <span class="status correction_requested">Correction Requested</span>
    </div>

    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= PATIENT / CERTIFICATE INFO ================= -->
    <div class="mc-card">
        <h3><i class='bx bx-id-card'></i> Patient Information</h3>

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
            <span class="detail-label">Appointment</span>
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

    <!-- ================= CORRECTION REQUEST DETAILS ================= -->
    <div class="mc-card">
        <h3><i class='bx bx-message-square-edit'></i> Correction Request Details</h3>

        @if($medicalCertificate->pendingCorrection)
            <div class="detail-row">
                <span class="detail-label">Reason for Correction</span>
                <span class="detail-value">{{ $medicalCertificate->pendingCorrection->reason }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Requested On</span>
                <span class="detail-value">
                    {{ $medicalCertificate->pendingCorrection->created_at?->format('F d, Y g:i A') ?? '—' }}
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status correction_requested">Pending Review</span>
                </span>
            </div>
        @else
            <p style="color:#9ca3af; font-style:italic;">No correction details found.</p>
        @endif
    </div>

    <!-- ================= CURRENT MEDICAL CERTIFICATE ================= -->
    <div class="mc-card">
        <h3><i class='bx bx-file-blank'></i> Current Medical Certificate</h3>

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
            <button type="button" class="btn-reject" onclick="openRejectModal()">
                <i class='bx bx-x-circle'></i> Reject Correction
            </button>
            <a href="{{ route('doctor.medical-certificates.create', $medicalCertificate->id) }}" class="btn-create">
                <i class='bx bx-edit'></i> Edit &amp; Correct Certificate
            </a>
            <a href="{{ route('doctor.medical-certificates.index') }}" class="btn-cancel">Back</a>
        </div>
    </div>

</div>

<!-- ================= REJECT CORRECTION MODAL ================= -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3>Reject Correction Request</h3>
        <form action="{{ route('doctor.medical-certificates.correction.reject', $medicalCertificate->id) }}" method="POST">
            @csrf
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">
                Rejection Reason <span style="color:#9ca3af; font-weight:400;">(optional)</span>
            </label>
            <textarea name="rejection_reason" rows="4" placeholder="Explain why this correction request is being rejected..."></textarea>
            @error('rejection_reason')<span class="field-error">{{ $message }}</span>@enderror
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn-reject">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal()  { document.getElementById('rejectModal').classList.add('show'); }
function closeRejectModal() { document.getElementById('rejectModal').classList.remove('show'); }
</script>

@endsection
