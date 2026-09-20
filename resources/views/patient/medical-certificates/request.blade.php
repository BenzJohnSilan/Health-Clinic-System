@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-medical-certificates.css') }}">
@endsection

@section('content')

<div class="container">

    <a href="{{ route('patient.medical-certificates.index') }}" class="back-link">
        <i class='bx bx-arrow-back'></i> Back to Medical Certificates
    </a>

    <div class="page-header">
        <h2>Request Medical Certificate</h2>
    </div>

    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    @if($appointments->isEmpty())
        <div class="empty-appointments">
            <p>
                You don't have any completed appointments available for a medical certificate request
                right now — or your existing requests for them are still pending.
            </p>
        </div>
    @else
        <div class="form-card">
            <form action="{{ route('patient.medical-certificates.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label>Select Completed Appointment <span class="required">*</span></label>
                    <select name="appointment_id" required>
                        <option value="" disabled selected>Choose an appointment</option>
                        @foreach($appointments as $appointment)
                            <option value="{{ $appointment->id }}"
                                {{ (old('appointment_id') ?? $selectedAppointmentId) == $appointment->id ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                                — Dr. {{ $appointment->doctor->first_name ?? '' }} {{ $appointment->doctor->last_name ?? '' }}
                                ({{ $appointment->reference_no ?? '—' }})
                            </option>
                        @endforeach
                    </select>
                    @error('appointment_id')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Purpose <span class="required">*</span></label>
                    <select name="purpose" id="purposeSelect" required onchange="toggleOtherPurpose()">
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
                    <input type="text" name="other_purpose" value="{{ old('other_purpose') }}" placeholder="Please specify">
                    @error('other_purpose')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Additional Details</label>
                    <textarea name="request_details" rows="4" placeholder="e.g. I need this medical certificate for my school absence.">{{ old('request_details') }}</textarea>
                    <p class="form-hint">Optional — any extra information the doctor should know.</p>
                    @error('request_details')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-actions">
                    <a href="{{ route('patient.medical-certificates.index') }}" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">Submit Request</button>
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
