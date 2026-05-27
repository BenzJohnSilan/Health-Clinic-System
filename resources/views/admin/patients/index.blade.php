@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-users.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-appointments.css') }}">

<style>
/* ================= MODAL FORM STYLE ================= */
.modal-content form .input-group {
    margin-bottom: 15px;
}

.modal-content form label {
    font-weight: 600;
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.modal-content form input,
.modal-content form select,
.modal-content form textarea {
    width: 100%;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    background: #f5f5f5;
    font-size: 14px;
}

.modal-content form input[readonly] {
    background: #e9ecef;
    cursor: default;
}

/* Grid for view modal */
#viewModal .modal-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px 20px;
}

#viewModal .modal-form-grid label {
    margin-bottom: 3px;
}

#viewModal .modal-form-grid input {
    padding: 6px 10px;
    font-size: 14px;
}

.modal-content h3 {
    text-align: center;
    margin-bottom: 20px;
    font-size: 22px;
    color: #6a11cb;
}

.modal-content {
    max-height: 90vh;
    overflow-y: auto;
}
</style>
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Patient List</h1>
        <button class="btn-primary" onclick="openModal('addPatientModal')">+ Add Patient</button>
    </div>

    <!-- ================= SUCCESS MESSAGE ================= -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- ================= PATIENTS TABLE ================= -->
    <div class="table-container">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Contact Number</th>
                    <th>Address</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patients as $patient)
                <tr>
                    {{-- ✅ FIXED: $patient is array (from map()), use ['key'] not ->key --}}
                    <td>{{ $patient['first_name'] }} {{ $patient['last_name'] }}</td>
                    <td>{{ $patient['contact_number'] ?? '-' }}</td>
                    <td>{{ $patient['address'] ?? '-' }}</td>
                    <td>{{ $patient['is_walk_in'] ? 'Walk-in' : 'Registered' }}</td>
                    <td>
                        <button class="editBtn"
                            data-fname="{{ $patient['first_name'] }}"
                            data-mname="{{ $patient['middle_name'] ?? '' }}"
                            data-lname="{{ $patient['last_name'] }}"
                            data-suffix="{{ $patient['suffix'] ?? '' }}"
                            data-birthdate="{{ $patient['birthdate'] }}"
                            {{-- ✅ FIXED: age computed here, no more column dependency --}}
                            data-age="{{ $patient['age'] ?? '-' }}"
                            data-gender="{{ $patient['gender'] }}"
                            data-civil="{{ $patient['civil_status'] }}"
                            data-address="{{ $patient['address'] ?? '' }}"
                            data-contact="{{ $patient['contact_number'] ?? '' }}"
                            onclick="openViewModal(this)">
                            View
                        </button>

                        <button class="btn-primary"
                            onclick="openAppointmentModal('{{ $patient['id'] }}')">
                            Add Appointment
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center;">No patients found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- ================= ADD PATIENT MODAL ================= -->
<div id="addPatientModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('addPatientModal')">&times;</button>
        <h3>Add Patient</h3>

        <form method="POST" action="{{ route('admin.patients.store') }}">
            @csrf

            <label>First Name</label>
            <input type="text" name="first_name" required>

            <label>Middle Name</label>
            <input type="text" name="middle_name">

            <label>Last Name</label>
            <input type="text" name="last_name" required>

            <label>Suffix</label>
            <input type="text" name="suffix">

            <label>Birthdate</label>
            <?php $minBirthdate = date('Y-m-d', strtotime('-120 years')); ?>
            <input type="date"
                   name="birthdate"
                   min="{{ $minBirthdate }}"
                   max="{{ date('Y-m-d') }}"
                   id="birthdateInput"
                   required>

            {{-- ✅ Age field is display-only, NOT submitted to server --}}
            <label>Age</label>
            <input type="text" id="agePreview" readonly placeholder="Auto-computed">

            <label>Gender</label>
            <select name="gender" required>
                <option value="">-- Select Gender --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>

            <label>Civil Status</label>
            <select name="civil_status" required>
                <option value="">-- Select Civil Status --</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Widowed">Widowed</option>
                <option value="Separated">Separated</option>
            </select>

            <label>Address</label>
            <input type="text" name="address">

            <label>Contact Number</label>
            <input type="text" name="contact_number" maxlength="11"
                   pattern="\d{11}"
                   title="Enter 11 digits only"
                   oninput="this.value=this.value.replace(/[^0-9]/g,'')">

            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>
</div>

<!-- ================= VIEW PATIENT MODAL ================= -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
        <h3>Patient Details</h3>

        <div class="modal-form-grid">
            <div class="input-group">
                <label>First Name</label>
                <input type="text" id="v_fname" readonly>
            </div>
            <div class="input-group">
                <label>Middle Name</label>
                <input type="text" id="v_mname" readonly>
            </div>
            <div class="input-group">
                <label>Last Name</label>
                <input type="text" id="v_lname" readonly>
            </div>
            <div class="input-group">
                <label>Suffix</label>
                <input type="text" id="v_suffix" readonly>
            </div>
            <div class="input-group">
                <label>Birthdate</label>
                <input type="text" id="v_birthdate" readonly>
            </div>
            <div class="input-group">
                <label>Age</label>
                <input type="text" id="v_age" readonly>
            </div>
            <div class="input-group">
                <label>Gender</label>
                <input type="text" id="v_gender" readonly>
            </div>
            <div class="input-group">
                <label>Civil Status</label>
                <input type="text" id="v_civil" readonly>
            </div>
            <div class="input-group" style="grid-column: span 2;">
                <label>Address</label>
                <input type="text" id="v_address" readonly>
            </div>
            <div class="input-group" style="grid-column: span 2;">
                <label>Contact Number</label>
                <input type="text" id="v_contact" readonly>
            </div>
        </div>
    </div>
</div>

<!-- ================= APPOINTMENT MODAL ================= -->
<div id="appointmentModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('appointmentModal')">&times;</button>
        <h3>Add Appointment</h3>

        <form method="POST" action="{{ route('admin.appointments.store') }}">
            @csrf
            <input type="hidden" name="patient_id" id="appointment_patient_id">

            <label>Date</label>
            <input type="date" name="appointment_date" required>

            <label>Time</label>
            <input type="time" name="appointment_time" required>

            <label>Doctor</label>
            <select name="doctor_id" required>
                <option value="">-- Select Doctor --</option>
                @foreach(\App\Models\User::where('role', 'Doctor')->get() as $doctor)
                    <option value="{{ $doctor->id }}">
                        Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}
                    </option>
                @endforeach
            </select>

            <label>Status</label>
            <select name="status" required>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
            </select>

            <label>Reason</label>
            <textarea name="reason" required></textarea>

            <button type="submit" class="btn-primary">Save</button>
        </form>
    </div>
</div>
@endsection

<!-- ================= JAVASCRIPT ================= -->
<script>
function openModal(id) {
    document.getElementById(id).style.display = "flex";

    if (id === 'addPatientModal') {
        const birthdateInput = document.getElementById('birthdateInput');
        const agePreview = document.getElementById('agePreview');

        if (birthdateInput && !birthdateInput.hasAttribute('data-listener')) {
            birthdateInput.addEventListener('change', function () {
                const birthdate = new Date(this.value);
                const today = new Date();

                if (birthdate >= today) {
                    alert('Invalid birthdate');
                    this.value = '';
                    agePreview.value = '';
                    return;
                }

                let age = today.getFullYear() - birthdate.getFullYear();
                const m = today.getMonth() - birthdate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }

                agePreview.value = age;
            });

            birthdateInput.setAttribute('data-listener', 'true');
        }

        // Reset on open
        birthdateInput.value = '';
        agePreview.value = '';
    }
}

function closeModal(id) {
    document.getElementById(id).style.display = "none";
}

// VIEW MODAL
function openViewModal(btn) {
    document.getElementById('v_fname').value    = btn.dataset.fname;
    document.getElementById('v_mname').value    = btn.dataset.mname || '-';
    document.getElementById('v_lname').value    = btn.dataset.lname;
    document.getElementById('v_suffix').value   = btn.dataset.suffix || '-';
    document.getElementById('v_birthdate').value = btn.dataset.birthdate;
    document.getElementById('v_age').value      = btn.dataset.age;
    document.getElementById('v_gender').value   = btn.dataset.gender;
    document.getElementById('v_civil').value    = btn.dataset.civil;
    document.getElementById('v_address').value  = btn.dataset.address || '-';
    document.getElementById('v_contact').value  = btn.dataset.contact || '-';

    openModal('viewModal');
}

// APPOINTMENT MODAL
function openAppointmentModal(patientId) {
    document.getElementById('appointment_patient_id').value = patientId;
    openModal('appointmentModal');
}

// CLICK OUTSIDE TO CLOSE
window.onclick = function (event) {
    document.querySelectorAll('.modal').forEach(modal => {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
};
</script>