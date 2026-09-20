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
        <h1 class="page-title">Medical Certificate Request</h1>
    </div>

    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <div class="mc-card">
        <h3><i class='bx bx-file'></i> Request Details</h3>

        <div class="detail-row">
            <span class="detail-label">Patient</span>
            <span class="detail-value">
                {{ $medicalCertificate->patient->first_name ?? '' }} {{ $medicalCertificate->patient->last_name ?? '' }}
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

        @if($medicalCertificate->request_details)
        <div class="detail-row">
            <span class="detail-label">Patient's Additional Details</span>
            <span class="detail-value">{{ $medicalCertificate->request_details }}</span>
        </div>
        @endif

        <div class="detail-row">
            <span class="detail-label">Requested On</span>
            <span class="detail-value">{{ $medicalCertificate->requested_at?->format('F d, Y g:i A') ?? '—' }}</span>
        </div>

        <div class="mc-actions">
            @if($medicalCertificate->appointment)
                <a href="{{ route('doctor.medical-records.show', $medicalCertificate->appointment->id) }}"
                   class="btn-link-record" target="_blank">
                    <i class='bx bx-folder-open'></i> View Medical Record
                </a>
            @endif
        </div>

        <div class="mc-actions" style="margin-top:22px;">
            <button type="button" class="btn-reject" onclick="openRejectModal()">
                <i class='bx bx-x-circle'></i> Reject
            </button>
            <a href="{{ route('doctor.medical-certificates.create', $medicalCertificate->id) }}" class="btn-create">
                <i class='bx bx-edit'></i> Create Medical Certificate
            </a>
        </div>
    </div>

</div>

<!-- ================= REJECT MODAL ================= -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3>Reject Certificate Request</h3>
        <form action="{{ route('doctor.medical-certificates.reject', $medicalCertificate->id) }}" method="POST">
            @csrf
            <label style="display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:8px;">
                Rejection Reason <span style="color:#ef4444;">*</span>
            </label>
            <textarea name="rejection_reason" rows="4" required placeholder="Explain why this request is being rejected..."></textarea>
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
