@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-certificates.css') }}">
@endsection

@section('content')

<div class="container">

    @php
        $isCorrection = $medicalCertificate->isCorrectionRequested();
    @endphp

    <a href="{{ $isCorrection
            ? route('doctor.medical-certificates.correction.review', $medicalCertificate->id)
            : route('doctor.medical-certificates.show', $medicalCertificate->id) }}" class="back-link">
        <i class='bx bx-arrow-back'></i> {{ $isCorrection ? 'Back to Correction Request' : 'Back to Request' }}
    </a>

    <div class="page-header">
        <h1 class="page-title">{{ $isCorrection ? 'Edit & Correct Medical Certificate' : 'Create Medical Certificate' }}</h1>
    </div>

    @if($isCorrection && $medicalCertificate->pendingCorrection)
        <div class="mc-card" style="border-left:4px solid #3b82f6;">
            <h3><i class='bx bx-message-square-edit'></i> Patient's Correction Request</h3>
            <div class="detail-row">
                <span class="detail-label">Reason for Correction</span>
                <span class="detail-value">{{ $medicalCertificate->pendingCorrection->reason }}</span>
            </div>
        </div>
    @endif

    @if(!$doctor->signature)
        <div class="no-signature-warning">
            <i class='bx bx-error'></i>
            You haven't uploaded a digital signature yet. Please add one in
            <a href="{{ route('doctor.account-settings') }}">Account Settings</a> before you can sign and issue a certificate.
        </div>
    @endif

    <!-- ================= AUTO-POPULATED INFO ================= -->
    <div class="mc-card">
        <h3><i class='bx bx-id-card'></i> Certificate Information (auto-filled)</h3>

        <div class="detail-row">
            <span class="detail-label">Patient Name</span>
            <span class="detail-value">
                {{ $medicalCertificate->resolvedPatientName() }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Consultation Date</span>
            <span class="detail-value">
                {{ optional($medicalCertificate->appointment)->appointment_date
                    ? \Carbon\Carbon::parse($medicalCertificate->appointment->appointment_date)->format('F d, Y')
                    : '—' }}
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Doctor</span>
            <span class="detail-value">
                Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}
                @if($doctor->specialization) — {{ $doctor->specialization }} @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Clinic</span>
            <span class="detail-value">Health Medical Clinic, Brgy. Dayap Calauan, Laguna</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Purpose</span>
            <span class="detail-value">{{ $medicalCertificate->displayPurpose() }}</span>
        </div>
    </div>

    <!-- ================= EDITABLE FORM ================= -->
    <form action="{{ route('doctor.medical-certificates.issue', $medicalCertificate->id) }}" method="POST" id="certForm">
        @csrf

        <div class="mc-card">
            <h3><i class='bx bx-edit-alt'></i> Certificate Content</h3>

            <div class="form-group">
                <label>Medical Statement <span class="required">*</span></label>
                <textarea name="medical_statement" id="medicalStatement" rows="3" required
                    placeholder="Patient was examined and consulted for __________.">{{ old('medical_statement', $medicalCertificate->medical_statement) }}</textarea>
                @error('medical_statement')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label>Recommendation <span class="required">*</span></label>
                <textarea name="recommendation" id="recommendation" rows="3" required
                    placeholder="Patient is advised to rest for __________.">{{ old('recommendation', $medicalCertificate->recommendation) }}</textarea>
                @error('recommendation')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Rest Period — Start Date</label>
                    <input type="date" name="rest_start_date" id="restStart"
                        value="{{ old('rest_start_date', optional($medicalCertificate->rest_start_date)->format('Y-m-d')) }}">
                    @error('rest_start_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Rest Period — End Date</label>
                    <input type="date" name="rest_end_date" id="restEnd"
                        value="{{ old('rest_end_date', optional($medicalCertificate->rest_end_date)->format('Y-m-d')) }}">
                    @error('rest_end_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-group">
                <label>Additional Remarks</label>
                <textarea name="additional_remarks" id="additionalRemarks" rows="2" placeholder="Optional">{{ old('additional_remarks', $medicalCertificate->additional_remarks) }}</textarea>
                @error('additional_remarks')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-actions">
                <a href="{{ $isCorrection
                        ? route('doctor.medical-certificates.correction.review', $medicalCertificate->id)
                        : route('doctor.medical-certificates.show', $medicalCertificate->id) }}" class="btn-cancel">Cancel</a>
                <button type="button" class="btn-preview" onclick="openPreview()">
                    <i class='bx bx-show'></i> Preview
                </button>
                <button type="submit" class="btn-sign-issue" {{ $doctor->signature ? '' : 'disabled' }}>
                    <i class='bx bx-check-shield'></i> {{ $isCorrection ? 'Save Correction & Re-Issue' : 'Sign & Issue' }}
                </button>
            </div>
        </div>

    </form>

</div>

<!-- ================= PREVIEW MODAL ================= -->
<div id="certPreviewModal">
    <div class="preview-modal-box">
        <button class="preview-modal-close" onclick="closePreview()">&times;</button>
        <div id="previewContent" style="font-family: 'DM Sans', sans-serif; font-size: 13px; color:#1a1a2e; line-height:1.7;">

            <div style="text-align:center; margin-bottom:10px;">
                <div style="font-family: 'EB Garamond', serif; font-size:24px; font-weight:600;">Health Medical Clinic</div>
                <div style="font-size:10px; color:#666; font-style:italic;">Brgy. Dayap Calauan, Laguna</div>
            </div>
            <hr style="border:none; border-top:2px solid #1a1a2e; margin:10px 0;">
            <div style="text-align:center; margin-bottom:10px;">
                <div style="font-size:10px; text-transform:uppercase; letter-spacing:1.5px; color:#888;">Republic of the Philippines</div>
                <h2 style="font-family:'EB Garamond', serif; font-size:20px; margin-top:4px;">Medical Certificate</h2>
            </div>
            <p style="margin-bottom:10px;">This is to certify that the patient whose information appears below has been examined and found to be under medical consultation as described hereunder.</p>

            <p><strong>Patient's Full Name:</strong> {{ strtoupper($medicalCertificate->resolvedPatient()?->last_name ?? '') }}, {{ $medicalCertificate->resolvedPatient()?->first_name ?? '' }}</p>
            <p><strong>Date of Consultation:</strong> {{ optional($medicalCertificate->appointment)->appointment_date ? \Carbon\Carbon::parse($medicalCertificate->appointment->appointment_date)->format('F d, Y') : '—' }}</p>
            <p><strong>Attending Physician:</strong> Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}</p>
            <p style="margin-top:8px;"><strong>Medical Statement:</strong> <span id="previewStatement">—</span></p>
            <p><strong>Recommendation:</strong> <span id="previewRecommendation">—</span></p>
            <p id="previewRestWrap" style="display:none;"><strong>Advised Rest Period:</strong> <span id="previewRest"></span></p>
            <p id="previewRemarksWrap" style="display:none;"><strong>Additional Remarks:</strong> <span id="previewRemarks"></span></p>
            <p style="margin-top:10px; font-style:italic;">This certificate is issued for: <strong>{{ $medicalCertificate->displayPurpose() }}</strong></p>

            <hr style="border:none; border-top:1px solid #ccc; margin:14px 0;">
            <p style="font-size:11px; color:#666;">
                Signature: {{ $doctor->signature ? 'On file (will be applied upon issuance)' : 'Not uploaded yet' }}<br>
                Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}, MD — Lic. No. {{ $doctor->license_number ?? '___________' }}
            </p>
        </div>
    </div>
</div>

<script>
function openPreview() {
    document.getElementById('previewStatement').textContent = document.getElementById('medicalStatement').value || '—';
    document.getElementById('previewRecommendation').textContent = document.getElementById('recommendation').value || '—';

    const start = document.getElementById('restStart').value;
    const end   = document.getElementById('restEnd').value;
    const restWrap = document.getElementById('previewRestWrap');
    if (start && end) {
        document.getElementById('previewRest').textContent = start + ' to ' + end;
        restWrap.style.display = 'block';
    } else {
        restWrap.style.display = 'none';
    }

    const remarks = document.getElementById('additionalRemarks').value;
    const remarksWrap = document.getElementById('previewRemarksWrap');
    if (remarks) {
        document.getElementById('previewRemarks').textContent = remarks;
        remarksWrap.style.display = 'block';
    } else {
        remarksWrap.style.display = 'none';
    }

    document.getElementById('certPreviewModal').classList.add('show');
}
function closePreview() {
    document.getElementById('certPreviewModal').classList.remove('show');
}
</script>

@endsection
