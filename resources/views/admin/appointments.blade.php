@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-appointments.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-users.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Manage Appointments</h1>
        <button id="openAppointmentModal" class="btn-primary">
            + Add Appointment
        </button>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- ================= APPOINTMENTS TABLE ================= -->
    <div class="table-container">
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th style="width:160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($appointments as $appointment)
                <tr>
                    <td>
                        @if($appointment->patient)
                            {{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}
                        @elseif($appointment->walkinPatient)
                            {{ $appointment->walkinPatient->first_name }} {{ $appointment->walkinPatient->last_name }}
                        @else
                            N/A
                        @endif
                    </td>
                    <td>{{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}</td>
                    <td>{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d') }}</td>
                    <td>{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i') }}</td>
                    <td>
                        @if($appointment->status === 'Completed')
                            <span class="status completed">Completed</span>
                        @elseif($appointment->status === 'Pending')
                            <span class="status pending">Pending</span>
                        @elseif($appointment->status === 'Approved')
                            <span class="status approved">Approved</span>
                        @elseif($appointment->status === 'Rejected')
                            <span class="status rejected">Rejected</span>
                        @elseif($appointment->status === 'Cancelled')
                            <span class="status cancelled">Cancelled</span>
                        @else
                            {{ $appointment->status }}
                        @endif
                    </td>
                    <td>{{ $appointment->reason ?? '-' }}</td>
                    <td>
                        <a href="{{ route('admin.appointments.edit', $appointment->id) }}" class="btn-edit">Edit</a>

                        <form action="{{ route('admin.appointments.destroy', $appointment->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-delete" onclick="return confirm('Delete this appointment?')">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; font-style:italic; color:#555;">
                        No appointments found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= ADD APPOINTMENT MODAL ================= -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" id="closeModal">&times;</button>
            <h3>Add Appointment</h3>

            <form action="{{ route('admin.appointments.store') }}" method="POST">
                @csrf

                <label>Patient</label>
                <select name="patient_id" required>
                    <option value="" disabled selected>Select Patient</option>
                    @foreach($patients as $patient)
                        <option value="{{ $patient->id }}" {{ old('patient_id') == $patient->id ? 'selected' : '' }}>
                            {{ $patient->first_name }} {{ $patient->last_name }}
                        </option>
                    @endforeach
                </select>

                <label>Doctor</label>
                <select name="doctor_id" required>
                    <option value="" disabled selected>Select Doctor</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}" {{ old('doctor_id') == $doctor->id ? 'selected' : '' }}>
                            {{ $doctor->first_name }} {{ $doctor->last_name }}
                        </option>
                    @endforeach
                </select>

                <label>Date</label>
                <input type="date" name="appointment_date" value="{{ old('appointment_date') }}" required>

                <label>Time</label>
                <input type="time" name="appointment_time" value="{{ old('appointment_time') }}" required>

                <label>Status</label>
                <select name="status" required>
                    <option value="Pending" {{ old('status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                    <option value="Approved" {{ old('status') == 'Approved' ? 'selected' : '' }}>Approved</option>
                    <option value="Rejected" {{ old('status') == 'Rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="Completed" {{ old('status') == 'Completed' ? 'selected' : '' }}>Completed</option>
                    <option value="Cancelled" {{ old('status') == 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>

                <label>Reason</label>
                <textarea name="reason" rows="3" placeholder="Enter reason for appointment">{{ old('reason') }}</textarea>

                <button type="submit" class="btn-primary">Save Appointment</button>
            </form>
        </div>
    </div>

</div>

<!-- ================= SCRIPT ================= -->
<script>
const modal = document.getElementById('appointmentModal');
const openBtn = document.getElementById('openAppointmentModal');
const closeBtn = document.getElementById('closeModal');

// Open modal
openBtn.onclick = () => modal.style.display = 'flex';

// Close modal
closeBtn.onclick = () => modal.style.display = 'none';

// Close modal when clicking outside
window.onclick = e => {
    if (e.target === modal) modal.style.display = 'none';
};
</script>
@endsection
