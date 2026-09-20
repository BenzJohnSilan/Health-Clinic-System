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
        <h1 class="page-title">Create Medical Certificate</h1>
    </div>

    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <p style="color:#6b7280; font-size:14px; margin-bottom:18px; max-width:640px;">
        Use this for walk-in and face-to-face patients — or any completed consultation
        that doesn't already have an online medical certificate request.
    </p>

    @if($appointments->isEmpty())
        <div class="mc-card">
            <p style="color:#9ca3af; font-style:italic;">
                You don't have any completed consultations available for a new medical certificate right now.
            </p>
        </div>
    @else
        <div class="mc-card">
            <h3><i class='bx bx-file'></i> Certificate Request</h3>

            <form action="{{ route('doctor.medical-certificates.direct.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label>Select Consultation <span class="required">*</span></label>
                    <select name="appointment_id" required style="width:100%; padding:10px 12px; border:1.5px solid #e5d9f7; border-radius:8px; font-size:14px; font-family:inherit; color:#374151;">
                        <option value="" disabled {{ $selectedAppointmentId ? '' : 'selected' }}>Choose a consultation</option>
                        @foreach($appointments as $appointment)
                            @php
                                $isWalkIn = $appointment->walkin_patient_id !== null;
                                $p        = $isWalkIn ? $appointment->walkinPatient : $appointment->patient;
                            @endphp
                            <option value="{{ $appointment->id }}" {{ (string) $selectedAppointmentId === (string) $appointment->id ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                                — {{ $p->first_name ?? '' }} {{ $p->last_name ?? '' }}
                                {{ $isWalkIn ? '(Walk-in)' : '' }}
                                ({{ $appointment->reference_no ?? '—' }})
                            </option>
                        @endforeach
                    </select>
                    @error('appointment_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Purpose <span class="required">*</span></label>
                    <select name="purpose" id="purposeSelect" required onchange="toggleOtherPurpose()"
                        style="width:100%; padding:10px 12px; border:1.5px solid #e5d9f7; border-radius:8px; font-size:14px; font-family:inherit; color:#374151;">
                        <option value="" disabled {{ old('purpose') ? '' : 'selected' }}>Select purpose</option>
                        <option value="School" {{ old('purpose') == 'School' ? 'selected' : '' }}>School</option>
                        <option value="Work" {{ old('purpose') == 'Work' ? 'selected' : '' }}>Work</option>
                        <option value="Sick Leave" {{ old('purpose') == 'Sick Leave' ? 'selected' : '' }}>Sick Leave</option>
                        <option value="Other" {{ old('purpose') == 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                    @error('purpose')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group" id="otherPurposeGroup" style="{{ old('purpose') == 'Other' ? '' : 'display:none;' }}">
                    <label>Other Purpose <span class="required">*</span></label>
                    <input type="text" name="other_purpose" value="{{ old('other_purpose') }}" placeholder="Please specify"
                        style="width:100%; padding:10px 12px; border:1.5px solid #e5d9f7; border-radius:8px; font-size:14px; font-family:inherit; color:#374151;">
                    @error('other_purpose')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Additional Details</label>
                    <textarea name="request_details" rows="3" placeholder="Optional — any extra context for this certificate.">{{ old('request_details') }}</textarea>
                    @error('request_details')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-actions">
                    <a href="{{ route('doctor.medical-certificates.index') }}" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-create">
                        <i class='bx bx-right-arrow-alt'></i> Continue
                    </button>
                </div>
            </form>
        </div>
    @endif

</div>

<script>
function toggleOtherPurpose() {
    const purpose = document.getElementById('purposeSelect').value;
    const group   = document.getElementById('otherPurposeGroup');
    group.style.display = purpose === 'Other' ? 'block' : 'none';
}
</script>

@endsection
