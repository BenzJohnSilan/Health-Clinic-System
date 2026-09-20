@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-medical-certificates.css') }}">
@endsection

@section('content')

<div class="container mc-show-page">

    <a href="{{ route('patient.medical-certificates.index') }}" class="back-link">
        <i class='bx bx-arrow-back'></i> Back to Medical Certificates
    </a>

    <div class="page-header">
        <div>
            <h2>
                @if($medicalCertificate->isIssued() || $medicalCertificate->isCorrectionRequested())
                    Medical Certificate
                @else
                    Certificate Request
                @endif
            </h2>
        </div>

        <div class="header-actions-group">
            @if($medicalCertificate->hasIssuedContent() && !$medicalCertificate->isCorrectionRequested())
            <div class="cert-actions-top">
                <button type="button" class="btn-cancel" onclick="openCorrectionModal()">
                    <i class='bx bx-edit-alt'></i> Request Correction
                </button>
                <a href="{{ route('patient.medical-certificates.print', $medicalCertificate->id) }}"
                   target="_blank" class="btn-submit">
                    <i class='bx bx-printer'></i> Print Certificate
                </a>
            </div>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <div class="record-card">
        <h3 class="record-card-title">Certificate Details</h3>

        <div class="detail-grid">

            <div class="detail-item">
                <span class="detail-label">Certificate No.</span>
                <span class="detail-value">
                    {{ $medicalCertificate->certificate_number ?? 'Request #' . str_pad($medicalCertificate->id, 3, '0', STR_PAD_LEFT) }}
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Appointment Date</span>
                <span class="detail-value">
                    {{ optional($medicalCertificate->appointment)->appointment_date
                        ? \Carbon\Carbon::parse($medicalCertificate->appointment->appointment_date)->format('F d, Y')
                        : '—' }}
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Attending Physician</span>
                <span class="detail-value">
                    @if(optional($medicalCertificate->appointment)->doctor)
                        Dr. {{ $medicalCertificate->appointment->doctor->first_name }} {{ $medicalCertificate->appointment->doctor->last_name }}
                    @else
                        —
                    @endif
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Purpose</span>
                <span class="detail-value">{{ $medicalCertificate->displayPurpose() }}</span>
            </div>

            @if($medicalCertificate->request_details)
            <div class="detail-item">
                <span class="detail-label">Additional Details</span>
                <span class="detail-value">{{ $medicalCertificate->request_details }}</span>
            </div>
            @endif

            <div class="detail-item">
                <span class="detail-label">Requested On</span>
                <span class="detail-value">
                    {{ $medicalCertificate->requested_at?->format('F d, Y g:i A') ?? '—' }}
                </span>
            </div>

            @if($medicalCertificate->hasIssuedContent())
            <div class="detail-item">
                <span class="detail-label">Issued On</span>
                <span class="detail-value">{{ $medicalCertificate->issued_at?->format('F d, Y') ?? '—' }}</span>
            </div>
            @endif

        </div>
    </div>

    @if($medicalCertificate->hasIssuedContent())

        <div class="record-card">
            <h3 class="record-card-title">Medical Details</h3>

            <div class="text-block-group">
                <div class="text-block-item">
                    <span class="detail-label">Medical Statement</span>
                    <div class="text-block">{{ $medicalCertificate->medical_statement }}</div>
                </div>

                <div class="text-block-item">
                    <span class="detail-label">Recommendation</span>
                    <div class="text-block">{{ $medicalCertificate->recommendation }}</div>
                </div>
            </div>

            @if(($medicalCertificate->rest_start_date && $medicalCertificate->rest_end_date) || $medicalCertificate->additional_remarks)
            <div class="mc-medical-meta">

                @if($medicalCertificate->rest_start_date && $medicalCertificate->rest_end_date)
                <div class="detail-item">
                    <span class="detail-label">Rest Period</span>
                    <span class="detail-value">
                        {{ $medicalCertificate->rest_start_date->format('F d, Y') }}
                        to
                        {{ $medicalCertificate->rest_end_date->format('F d, Y') }}
                    </span>
                </div>
                @endif

                @if($medicalCertificate->additional_remarks)
                <div class="detail-item">
                    <span class="detail-label">Additional Remarks</span>
                    <span class="detail-value">{{ $medicalCertificate->additional_remarks }}</span>
                </div>
                @endif

            </div>
            @endif
        </div>

        @if($medicalCertificate->isCorrectionRequested())

            <div class="info-banner info-banner--pending">
                <i class='bx bx-info-circle'></i>
                <span>Your correction request has been submitted and is awaiting the doctor's review.</span>
            </div>

        @elseif($medicalCertificate->latestCorrection && $medicalCertificate->latestCorrection->isRejected())

            <div class="rejection-box">
                <strong>Previous Correction Request Rejected:</strong><br>
                {{ $medicalCertificate->latestCorrection->reason }}
                @if($medicalCertificate->latestCorrection->rejection_reason)
                    <br><br><strong>Doctor's Note:</strong><br>{{ $medicalCertificate->latestCorrection->rejection_reason }}
                @endif
            </div>

        @endif

    @elseif($medicalCertificate->isRejected())

        <div class="rejection-box">
            <strong>Rejection Reason:</strong><br>
            {{ $medicalCertificate->rejection_reason }}
        </div>

    @else

        <div class="info-banner info-banner--pending">
            <i class='bx bx-time-five'></i>
            <span>Your request is currently pending review by the doctor. You'll be notified once it has been reviewed.</span>
        </div>

    @endif

</div>

@if($medicalCertificate->isIssued())
<!-- ================= REQUEST CORRECTION MODAL ================= -->
<div class="modal-overlay" id="correctionModal">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="closeCorrectionModal()" aria-label="Close">&times;</button>
        <h3 class="modal-title">Request Correction</h3>
        <form action="{{ route('patient.medical-certificates.correction.request', $medicalCertificate->id) }}" method="POST">
            @csrf
            <div class="form-group">
                <label>Reason for Correction <span class="required">*</span></label>
                <textarea name="reason" rows="4" required
                    placeholder="e.g. My middle name is misspelled. Please correct it."></textarea>
                @error('reason')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeCorrectionModal()">Cancel</button>
                <button type="submit" class="btn-submit">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCorrectionModal()  { document.getElementById('correctionModal').classList.add('show'); }
function closeCorrectionModal() { document.getElementById('correctionModal').classList.remove('show'); }
</script>
@endif

@endsection