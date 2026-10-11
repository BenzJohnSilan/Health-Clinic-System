@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-medical-certificate-create.css') }}">
@endsection

@section('content')

@php
    // Patient / consultation info for every selectable consultation, so the
    // summary panel can show it as soon as one is chosen.
    $consultationInfo = $appointments->mapWithKeys(function ($appointment) {
        $isWalkIn = $appointment->walkin_patient_id !== null;
        $p        = $isWalkIn ? $appointment->walkinPatient : $appointment->patient;

        return [$appointment->id => [
            'patient'   => trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')),
            'last_name' => strtoupper($p->last_name ?? ''),
            'first_name'=> $p->first_name ?? '',
            'birthdate' => $p?->birthdate ? \Carbon\Carbon::parse($p->birthdate)->format('F d, Y') : '—',
            'address'   => $p->address ?? '—',
            'date'      => \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y'),
            'reference' => $appointment->reference_no ?? '—',
            'walk_in'   => $isWalkIn,
        ]];
    });
@endphp

<a href="{{ route('doctor.medical-certificates.index') }}" class="mc-back">
    <i class='bx bx-arrow-back'></i> Back to Medical Certificates
</a>

<!-- ================= HERO ================= -->
<div class="mc-hero">
    <div class="mc-hero-badge"><i class='bx bx-file-blank'></i></div>
    <div class="mc-hero-text">
        <h2 class="mc-hero-title">Create Medical Certificate</h2>
        <p class="mc-hero-subtitle">Issue a certificate for a completed consultation.</p>
    </div>
</div>

@if(session('error'))
    <div class="mc-alert mc-alert--error">
        <i class='bx bx-error-circle'></i> {{ session('error') }}
    </div>
@endif

@if($appointments->isEmpty())
    <div class="info-card">
        <div class="info-card-empty">
            <i class='bx bx-calendar-x'></i>
            <p>You don't have any completed consultations available for a new medical certificate right now.</p>
        </div>
    </div>
@else
<form action="{{ route('doctor.medical-certificates.store') }}" method="POST" id="certForm" class="mc-grid">
    @csrf

    <!-- ================= MAIN COLUMN ================= -->
    <div class="mc-main">

        <!-- ============ CONSULTATION ============ -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="info-card-icon"><i class='bx bx-calendar-check'></i></div>
                    <h3>Consultation</h3>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label for="appointmentSelect">Completed Consultation <span class="required">*</span></label>
                <select name="appointment_id" id="appointmentSelect" required onchange="showConsultationInfo()">
                    <option value="" disabled {{ old('appointment_id', $selectedAppointmentId) ? '' : 'selected' }}>Choose a consultation</option>
                    @foreach($appointments as $appointment)
                        @php $info = $consultationInfo[$appointment->id]; @endphp
                        <option value="{{ $appointment->id }}"
                            {{ (string) old('appointment_id', $selectedAppointmentId) === (string) $appointment->id ? 'selected' : '' }}>
                            {{ $info['date'] }} — {{ $info['patient'] }}{{ $info['walk_in'] ? ' (Walk-in)' : '' }} ({{ $info['reference'] }})
                        </option>
                    @endforeach
                </select>
                @error('appointment_id')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>

        <!-- ============ CERTIFICATE DETAILS ============ -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="info-card-icon"><i class='bx bx-file'></i></div>
                    <h3>Medical Certificate Details</h3>
                </div>
            </div>

            <div class="form-group">
                <label for="purposeSelect">Purpose <span class="required">*</span></label>
                <select name="purpose" id="purposeSelect" required onchange="toggleOtherPurpose()">
                    <option value="" disabled {{ old('purpose') ? '' : 'selected' }}>Select purpose</option>
                    @foreach(['School', 'Work', 'Sick Leave', 'Other'] as $purpose)
                        <option value="{{ $purpose }}" {{ old('purpose') === $purpose ? 'selected' : '' }}>{{ $purpose }}</option>
                    @endforeach
                </select>
                @error('purpose')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group" id="otherPurposeGroup" style="{{ old('purpose') === 'Other' ? '' : 'display:none;' }}">
                <label for="otherPurpose">Other Purpose <span class="required">*</span></label>
                <input type="text" name="other_purpose" id="otherPurpose" value="{{ old('other_purpose') }}" placeholder="Please specify">
                @error('other_purpose')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label for="requestDetails">Purpose / Additional Details</label>
                <textarea name="request_details" id="requestDetails" rows="2" placeholder="Optional — extra context about the purpose.">{{ old('request_details') }}</textarea>
                @error('request_details')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label for="medicalStatement">Final Diagnosis <span class="required">*</span></label>
                <textarea name="medical_statement" id="medicalStatement" rows="3" required
                    placeholder="e.g. Essentially Well Adult">{{ old('medical_statement') }}</textarea>
                @error('medical_statement')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label for="recommendation">Remarks / Recommendation <span class="required">*</span></label>
                <textarea name="recommendation" id="recommendation" rows="3" required
                    placeholder="e.g. Fit to work. / Advised rest for 3 days.">{{ old('recommendation') }}</textarea>
                @error('recommendation')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="restStart">Rest Period — Start Date</label>
                    <input type="date" name="rest_start_date" id="restStart" value="{{ old('rest_start_date') }}">
                    @error('rest_start_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label for="restEnd">Rest Period — End Date</label>
                    <input type="date" name="rest_end_date" id="restEnd" value="{{ old('rest_end_date') }}">
                    @error('rest_end_date')<span class="field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-group">
                <label for="additionalRemarks">Additional Remarks</label>
                <textarea name="additional_remarks" id="additionalRemarks" rows="2" placeholder="Optional">{{ old('additional_remarks') }}</textarea>
                @error('additional_remarks')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="info-card-footer">
                <a href="{{ route('doctor.medical-certificates.index') }}" class="mc-btn mc-btn--cancel">Cancel</a>
                <button type="button" class="mc-btn mc-btn--outline" onclick="openPreview()">
                    <i class='bx bx-show'></i> Preview
                </button>
                <button type="submit" class="mc-btn mc-btn--primary">
                    <i class='bx bx-check-shield'></i> Create Medical Certificate
                </button>
            </div>
        </div>

    </div>

    <!-- ================= SIDE COLUMN ================= -->
    <aside class="mc-side">

        <!-- ============ CONSULTATION SUMMARY ============ -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="info-card-icon"><i class='bx bx-user-circle'></i></div>
                    <h3>Patient &amp; Consultation</h3>
                </div>
                <span class="status-pill" id="infoWalkIn" style="display:none;">Walk-in</span>
            </div>

            <div class="info-card-empty" id="consultationEmpty">
                <i class='bx bx-calendar-check'></i>
                <p>Choose a completed consultation to see the patient's details here.</p>
            </div>

            <div class="mc-summary-list" id="consultationInfo" style="display:none;">
                <div class="mc-summary-row">
                    <span class="mc-summary-label">Patient Name</span>
                    <span class="mc-summary-value" id="infoPatient">—</span>
                </div>
                <div class="mc-summary-row">
                    <span class="mc-summary-label">Date of Birth</span>
                    <span class="mc-summary-value" id="infoBirthdate">—</span>
                </div>
                <div class="mc-summary-row">
                    <span class="mc-summary-label">Address</span>
                    <span class="mc-summary-value" id="infoAddress">—</span>
                </div>
                <div class="mc-summary-row">
                    <span class="mc-summary-label">Date Examined</span>
                    <span class="mc-summary-value" id="infoDate">—</span>
                </div>
                <div class="mc-summary-row">
                    <span class="mc-summary-label">Consultation Reference</span>
                    <span class="mc-summary-value" id="infoReference">—</span>
                </div>
                <div class="mc-summary-row">
                    <span class="mc-summary-label">Doctor</span>
                    <span class="mc-summary-value">
                        Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}
                        @if($doctor->specialization) — {{ $doctor->specialization }} @endif
                    </span>
                </div>
            </div>
        </div>

        <!-- ============ WHAT HAPPENS NEXT ============ -->
        <div class="info-card">
            <div class="info-card-header">
                <div class="info-card-title">
                    <div class="info-card-icon"><i class='bx bx-info-circle'></i></div>
                    <h3>What happens next</h3>
                </div>
            </div>

            <ol class="mc-steps">
                <li>
                    <span class="mc-step-num">1</span>
                    <p><strong>Fee is billed automatically.</strong> It joins the consultation invoice if that is still unpaid, otherwise it gets its own invoice.</p>
                </li>
                <li>
                    <span class="mc-step-num">2</span>
                    <p><strong>Print and sign.</strong> After the fee is paid you can print the certificate and sign it by hand.</p>
                </li>
                <li>
                    <span class="mc-step-num">3</span>
                    <p><strong>Staff releases it</strong> to the patient.</p>
                </li>
            </ol>
        </div>

    </aside>
</form>
@endif

<!-- ================= PREVIEW MODAL ================= -->
<div id="certPreviewModal" onclick="if (event.target === this) closePreview()">
    <div class="preview-modal-box">

        <div class="preview-modal-head">
            <div class="info-card-title">
                <div class="info-card-icon"><i class='bx bx-show'></i></div>
                <h3 style="font-size:15.5px; font-weight:800; margin:0;">Certificate Preview</h3>
            </div>
            <button type="button" class="preview-modal-close" onclick="closePreview()" aria-label="Close preview">&times;</button>
        </div>

        <div class="cert-paper">

            <div class="cert-clinic-name">Health Medical Clinic</div>
            <div class="cert-clinic-address">Brgy. Dayap Calauan, Laguna</div>

            <hr class="cert-rule">
            <h2 class="cert-title">Medical Certificate</h2>

            <p class="cert-intro">This is to certify that the patient whose information appears below has been examined and found to be under medical consultation as described hereunder.</p>

            <p><strong>Patient's Full Name:</strong> <span id="previewPatient">—</span></p>
            <p><strong>Date Examined:</strong> <span id="previewDate">—</span></p>
            <p><strong>Attending Physician:</strong> Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}</p>
            <p class="cert-gap"><strong>Final Diagnosis:</strong> <span id="previewStatement">—</span></p>
            <p><strong>Remarks:</strong> <span id="previewRecommendation">—</span></p>
            <p id="previewRestWrap" style="display:none;"><strong>Advised Rest Period:</strong> <span id="previewRest"></span></p>
            <p id="previewRemarksWrap" style="display:none;"><strong>Additional Remarks:</strong> <span id="previewRemarks"></span></p>
            <p class="cert-purpose">This certificate is issued for: <strong id="previewPurpose">—</strong></p>

            <hr class="cert-rule cert-rule--light">
            <p class="cert-signature">
                ______________________________<br>
                Doctor's Signature<br>
                Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}, MD — Lic. No. {{ $doctor->license_number ?? '___________' }}
            </p>
        </div>
    </div>
</div>

<script>
const consultationInfo = @json($consultationInfo);

function currentInfo() {
    const select = document.getElementById('appointmentSelect');
    return select ? (consultationInfo[select.value] || null) : null;
}

function showConsultationInfo() {
    const info    = currentInfo();
    const panel   = document.getElementById('consultationInfo');
    const empty   = document.getElementById('consultationEmpty');
    const walkIn  = document.getElementById('infoWalkIn');
    if (!panel || !empty) return;

    if (!info) {
        panel.style.display  = 'none';
        empty.style.display  = 'flex';
        walkIn.style.display = 'none';
        return;
    }

    document.getElementById('infoPatient').textContent   = info.patient   || '—';
    document.getElementById('infoBirthdate').textContent = info.birthdate || '—';
    document.getElementById('infoAddress').textContent   = info.address   || '—';
    document.getElementById('infoDate').textContent      = info.date      || '—';
    document.getElementById('infoReference').textContent = info.reference || '—';

    walkIn.style.display = info.walk_in ? 'inline-flex' : 'none';
    empty.style.display  = 'none';
    panel.style.display  = 'flex';
}

function toggleOtherPurpose() {
    const purpose = document.getElementById('purposeSelect').value;
    document.getElementById('otherPurposeGroup').style.display = purpose === 'Other' ? 'block' : 'none';
}

function openPreview() {
    const info = currentInfo();
    document.getElementById('previewPatient').textContent = info
        ? (info.last_name + (info.first_name ? ', ' + info.first_name : '')) : '—';
    document.getElementById('previewDate').textContent = info ? info.date : '—';

    const purpose = document.getElementById('purposeSelect').value;
    document.getElementById('previewPurpose').textContent =
        purpose === 'Other' ? (document.getElementById('otherPurpose').value || 'Other') : (purpose || '—');

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

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePreview();
});

showConsultationInfo();
</script>

@endsection