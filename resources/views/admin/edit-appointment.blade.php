@extends('layouts.admin')

@section('content')
<div class="container">

    <div class="page-header">
        <h1 class="page-title">Edit Appointment</h1>
        <a href="{{ route('admin.appointments.index') }}" class="btn-secondary">
            ← Back
        </a>
    </div>

    @if ($errors->any())
        <div class="alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="form-container">
        <form action="{{ route('admin.appointments.update', $appointment->id) }}" method="POST">
            @csrf
            @method('PUT')

            <label>Patient</label>
            <select name="patient_id" required>
                @foreach($patients as $patient)
                    <option value="{{ $patient->id }}"
                        {{ $appointment->patient_id == $patient->id ? 'selected' : '' }}>
                        {{ $patient->first_name }} {{ $patient->last_name }}
                    </option>
                @endforeach
            </select>

            <label>Doctor</label>
            <select name="doctor_id" required>
                @foreach($doctors as $doctor)
                    <option value="{{ $doctor->id }}"
                        {{ $appointment->doctor_id == $doctor->id ? 'selected' : '' }}>
                        {{ $doctor->first_name }} {{ $doctor->last_name }}
                    </option>
                @endforeach
            </select>

            <label>Date</label>
            <input type="date" name="appointment_date"
                value="{{ $appointment->appointment_date }}" required>

            <label>Time</label>
            <input type="time" name="appointment_time"
                value="{{ $appointment->appointment_time }}" required>

            <label>Status</label>
            <select name="status" required>
                <option value="Pending" {{ $appointment->status == 'Pending' ? 'selected' : '' }}>Pending</option>
                <option value="Approved" {{ $appointment->status == 'Approved' ? 'selected' : '' }}>Approved</option>
                <option value="Rejected" {{ $appointment->status == 'Rejected' ? 'selected' : '' }}>Rejected</option>
                <option value="Completed" {{ $appointment->status == 'Completed' ? 'selected' : '' }}>Completed</option>
                <option value="Cancelled" {{ $appointment->status == 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>

            <label>Reason</label>
            <textarea name="reason" rows="3">{{ $appointment->reason }}</textarea>

            <button type="submit" class="btn-primary">
                Update Appointment
            </button>
        </form>
    </div>

</div>
@endsection
