@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-pending-accounts.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-appointments.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Pending Appointments</h1>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <!-- ================= PENDING APPOINTMENTS TABLE ================= -->
    <div class="table-container">
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Doctor</th>
                    <th>Schedule</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($appointments as $appointment)

                @php
                    // Use the model helper — works for both registered & walk-in patients
                    $patientName  = $appointment->patientName();
                    $patient      = $appointment->resolvedPatient();
                    $patientEmail = $appointment->patientEmail() ?? 'N/A';
                    $patientPhone = $patient?->contact_number ?? 'N/A';

                    $doctorName   = $appointment->doctor
                        ? $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name
                        : 'N/A';
                @endphp

                <tr>
                    <td>{{ $patientName }}</td>
                    <td>{{ $doctorName }}</td>
                    <td>
                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') }}
                        at
                        {{ $appointment->formatted_time }}
                    </td>
                    <td>{{ $appointment->reason ?? '-' }}</td>
                    <td>
                        <!-- APPROVE -->
                        <form action="{{ route('admin.appointments.approve', $appointment->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn-approve" onclick="return confirm('Approve this appointment?')">
                                Approve
                            </button>
                        </form>

                        <!-- REJECT BUTTON -->
                        <button
                            class="btn-reject openRejectModal"
                            data-id="{{ $appointment->id }}"
                            data-name="{{ $patientName }}"
                            data-email="{{ $patientEmail }}"
                            data-phone="{{ $patientPhone }}"
                        >
                            Reject
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center; font-style:italic; color:#555;">
                        No pending appointments found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<!-- ================= REJECT MODAL ================= -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h2>Reject Appointment</h2>

        <p><strong>Name:</strong> <span id="modalName"></span></p>
        <p><strong>Email:</strong> <span id="modalEmail"></span></p>
        <p><strong>Phone:</strong> <span id="modalPhone"></span></p>

        <form id="rejectForm" method="POST" data-url="{{ route('admin.appointments.reject', ':id') }}">
            @csrf

            <label>Reason for Rejection:</label>
            <textarea name="reason" required></textarea>

            <div class="modal-actions">
                <button type="submit" class="btn-reject">Confirm Reject</button>
                <button type="button" id="closeModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= JS ================= -->
<script>
document.querySelectorAll('.openRejectModal').forEach(button => {
    button.addEventListener('click', function () {
        document.getElementById('modalName').innerText  = this.dataset.name;
        document.getElementById('modalEmail').innerText = this.dataset.email;
        document.getElementById('modalPhone').innerText = this.dataset.phone;

        const form = document.getElementById('rejectForm');
        form.action = form.dataset.url.replace(':id', this.dataset.id);

        document.getElementById('rejectModal').style.display = 'flex';
    });
});

document.getElementById('closeModal').addEventListener('click', function () {
    document.getElementById('rejectModal').style.display = 'none';
});

window.addEventListener('click', function (e) {
    const modal = document.getElementById('rejectModal');
    if (e.target === modal) modal.style.display = 'none';
});
</script>

<!-- ================= CSS ================= -->
<style>
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}
.modal-content {
    background: #fff;
    padding: 20px;
    width: 400px;
    border-radius: 8px;
}
.modal textarea {
    width: 100%;
    height: 80px;
    margin-top: 5px;
}
.modal-actions {
    margin-top: 15px;
    display: flex;
    justify-content: space-between;
}
</style>

@endsection