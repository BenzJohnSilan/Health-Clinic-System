@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-patient.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Patient List</h1>
        <button class="btn-add" onclick="openModal('addPatientModal')">
            <i class="fa-solid fa-user-plus"></i>
            Add Walk-in Patient
        </button>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- ================= PATIENTS TABLE ================= -->
    <div class="table-container">
        <table class="patients-table">
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
                    <td>{{ $patient['first_name'] }} {{ $patient['last_name'] }}</td>
                    <td>{{ $patient['contact_number'] ?: '—' }}</td>
                    <td>{{ $patient['address'] ?: '—' }}</td>
                    <td>
                        @if($patient['is_walk_in'])
                            <span class="type-badge walkin">Walk-in</span>
                        @else
                            <span class="type-badge registered">Registered</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn-view"
                            data-fname="{{ $patient['first_name'] }}"
                            data-mname="{{ $patient['middle_name'] }}"
                            data-lname="{{ $patient['last_name'] }}"
                            data-suffix="{{ $patient['suffix'] }}"
                            data-birthdate="{{ $patient['birthdate'] }}"
                            data-age="{{ $patient['age'] }}"
                            data-gender="{{ $patient['gender'] }}"
                            data-civil="{{ $patient['civil_status'] }}"
                            data-address="{{ $patient['address'] }}"
                            data-contact="{{ $patient['contact_number'] }}"
                            data-blood="{{ $patient['blood_type'] ?? '' }}"
                            data-allergies="{{ $patient['allergies'] ?? '' }}"
                            data-email="{{ $patient['email'] ?? '' }}"
                            data-username="{{ $patient['username'] ?? '' }}"
                            data-emergency-name="{{ $patient['emergency_name'] ?? '' }}"
                            data-emergency-contact="{{ $patient['emergency_contact'] ?? '' }}"
                            data-relationship="{{ $patient['relationship'] ?? '' }}"
                            data-emergency-address="{{ $patient['emergency_address'] ?? '' }}"
                            data-status="{{ $patient['status'] ?? '' }}"
                            data-approval="{{ $patient['approval'] ?? '' }}"
                            data-patient-type="{{ $patient['is_walk_in'] ? 'Walk-in' : 'Registered' }}"
                            onclick="openViewModal(this)">
                            View
                        </button>

                        <button class="btn-appointment"
                            onclick="openAppointmentModal(
                                '{{ $patient['raw_id'] }}',
                                '{{ $patient['type'] }}'
                            )">
                            Add Appointment
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="empty-row">No patients found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($patients->total() > 0)
                Showing <strong>{{ $patients->firstItem() }}–{{ $patients->lastItem() }}</strong>
                of <strong>{{ $patients->total() }}</strong> result{{ $patients->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($patients->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $patients->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $patients->currentPage();
                $lastPage    = $patients->lastPage();

                $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1
                        || $p === $lastPage
                        || abs($p - $currentPage) <= 2;
                })->values();
            @endphp

            @php $prev = null; @endphp
            @foreach($pages as $page)
                @if($prev !== null && $page - $prev > 1)
                    <span class="page-ellipsis">…</span>
                @endif

                @if($page === $currentPage)
                    <span class="page-btn active">{{ $page }}</span>
                @else
                    <a class="page-btn" href="{{ $patients->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($patients->hasMorePages())
                <a class="page-btn" href="{{ $patients->nextPageUrl() }}">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @else
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            @endif

        </nav>
    </div>

</div>

<!-- ================= ADD WALK-IN PATIENT MODAL ================= -->
<div id="addPatientModal" class="modal-overlay" style="display:none;">
    <div class="modal-box modal-box--wide">

        <button class="modal-close-btn" onclick="closeModal('addPatientModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Add Walk-in Patient</h3>

        <form method="POST" action="{{ route('staff.patients.store') }}">
            @csrf

            <!-- PERSONAL INFORMATION -->
            <div class="section-header">
                <i class="fa-solid fa-user"></i> Personal Information
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input class="form-input" type="text" name="first_name"
                           value="{{ old('first_name') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input class="form-input" type="text" name="middle_name"
                           value="{{ old('middle_name') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Last Name <span class="required">*</span></label>
                    <input class="form-input" type="text" name="last_name"
                           value="{{ old('last_name') }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Suffix</label>
                    <input class="form-input" type="text" name="suffix"
                           value="{{ old('suffix') }}" placeholder="Jr., Sr., III…">
                </div>

                <div class="form-group">
                    <label class="form-label">Birthdate</label>
                    <input class="form-input" type="date"
                           name="birthdate"
                           id="birthdateInput"
                           value="{{ old('birthdate') }}"
                           min="{{ date('Y-m-d', strtotime('-120 years')) }}"
                           max="{{ date('Y-m-d') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input class="form-input" type="text" id="agePreview" readonly placeholder="Auto-computed">
                </div>

                <div class="form-group">
                    <label class="form-label">Gender <span class="required">*</span></label>
                    <select class="form-input" name="gender" required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male"   {{ old('gender') === 'Male'   ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other"  {{ old('gender') === 'Other'  ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Civil Status <span class="required">*</span></label>
                    <select class="form-input" name="civil_status" required>
                        <option value="">-- Select Civil Status --</option>
                        <option value="Single"    {{ old('civil_status') === 'Single'    ? 'selected' : '' }}>Single</option>
                        <option value="Married"   {{ old('civil_status') === 'Married'   ? 'selected' : '' }}>Married</option>
                        <option value="Widowed"   {{ old('civil_status') === 'Widowed'   ? 'selected' : '' }}>Widowed</option>
                        <option value="Separated" {{ old('civil_status') === 'Separated' ? 'selected' : '' }}>Separated</option>
                    </select>
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Address</label>
                    <input class="form-input" type="text" name="address"
                           value="{{ old('address') }}">
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Contact Number</label>
                    <input class="form-input" type="text"
                           name="contact_number"
                           value="{{ old('contact_number') }}"
                           maxlength="11"
                           pattern="\d{11}"
                           title="Enter 11 digits only"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                </div>
            </div>

            <!-- MEDICAL INFORMATION -->
            <div class="section-header">
                <i class="fa-solid fa-stethoscope"></i> Medical Information
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Blood Type</label>
                    <select class="form-input" name="blood_type">
                        <option value="">-- Select Blood Type --</option>
                        @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt)
                            <option value="{{ $bt }}" {{ old('blood_type') === $bt ? 'selected' : '' }}>{{ $bt }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Allergies</label>
                    <textarea class="form-input" name="allergies" rows="3"
                              placeholder="List any known allergies…">{{ old('allergies') }}</textarea>
                </div>
            </div>

            <!-- EMERGENCY CONTACT -->
            <div class="section-header">
                <i class="fa-solid fa-circle-exclamation"></i> Emergency Contact
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Contact Name</label>
                    <input class="form-input" type="text" name="emergency_name"
                           value="{{ old('emergency_name') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input class="form-input" type="text"
                           name="emergency_contact"
                           value="{{ old('emergency_contact') }}"
                           maxlength="11"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                </div>

                <div class="form-group">
                    <label class="form-label">Relationship</label>
                    <input class="form-input" type="text" name="relationship"
                           value="{{ old('relationship') }}" placeholder="e.g. Spouse, Parent…">
                </div>

                <div class="form-group span-2">
                    <label class="form-label">Emergency Address</label>
                    <input class="form-input" type="text" name="emergency_address"
                           value="{{ old('emergency_address') }}">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-back" onclick="closeModal('addPatientModal')">Cancel</button>
                <button type="submit" class="btn-confirm">Save Patient</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= VIEW PATIENT MODAL ================= -->
<div id="viewModal" class="modal-overlay" style="display:none;">
    <div class="modal-box modal-box--wide">

        <button class="modal-close-btn" onclick="closeModal('viewModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Patient Profile</h3>

        <!-- PERSONAL INFORMATION -->
        <div class="section-header">
            <i class="fa-solid fa-user"></i> Personal Information
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">First Name</label>
                <input class="form-input" type="text" id="v_fname" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Middle Name</label>
                <input class="form-input" type="text" id="v_mname" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name</label>
                <input class="form-input" type="text" id="v_lname" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Suffix</label>
                <input class="form-input" type="text" id="v_suffix" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Birthdate</label>
                <input class="form-input" type="text" id="v_birthdate" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Age</label>
                <input class="form-input" type="text" id="v_age" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Gender</label>
                <input class="form-input" type="text" id="v_gender" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Civil Status</label>
                <input class="form-input" type="text" id="v_civil" readonly>
            </div>
            <div class="form-group span-2">
                <label class="form-label">Address</label>
                <input class="form-input" type="text" id="v_address" readonly>
            </div>
            <div class="form-group span-2">
                <label class="form-label">Contact Number</label>
                <input class="form-input" type="text" id="v_contact" readonly>
            </div>
        </div>

        <!-- MEDICAL INFORMATION -->
        <div class="section-header">
            <i class="fa-solid fa-stethoscope"></i> Medical Information
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Blood Type</label>
                <input class="form-input" type="text" id="v_blood" readonly>
            </div>
            <div class="form-group span-2">
                <label class="form-label">Allergies</label>
                <textarea class="form-input" id="v_allergies" readonly rows="3"></textarea>
            </div>
        </div>

        <!-- EMERGENCY CONTACT -->
        <div class="section-header">
            <i class="fa-solid fa-circle-exclamation"></i> Emergency Contact
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Contact Name</label>
                <input class="form-input" type="text" id="v_emergency_name" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input class="form-input" type="text" id="v_emergency_contact" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Relationship</label>
                <input class="form-input" type="text" id="v_relationship" readonly>
            </div>
            <div class="form-group span-2">
                <label class="form-label">Emergency Address</label>
                <input class="form-input" type="text" id="v_emergency_address" readonly>
            </div>
        </div>

        <!-- ACCOUNT INFORMATION -->
        <div class="section-header">
            <i class="fa-solid fa-lock"></i> Account Information
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-input" type="text" id="v_email" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input class="form-input" type="text" id="v_username" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Patient Type</label>
                <input class="form-input" type="text" id="v_patient_type" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Account Status</label>
                <input class="form-input" type="text" id="v_status" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Approval Status</label>
                <input class="form-input" type="text" id="v_approval" readonly>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-back" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- ================= APPOINTMENT MODAL ================= -->
<div id="appointmentModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" onclick="closeModal('appointmentModal')" aria-label="Close">&times;</button>
        <h3 class="modal-title">Add Appointment</h3>

        <form method="POST" action="{{ route('staff.appointments.store') }}">
            @csrf

            <input type="hidden" name="patient_id"   id="appointment_patient_id">
            <input type="hidden" name="patient_type" id="appointment_patient_type">

            <!-- Date -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Date <span class="required">*</span></label>
                <input class="form-input" type="date"
                       name="appointment_date"
                       id="appt_date"
                       min="{{ date('Y-m-d') }}"
                       required>
            </div>

            <!-- Doctor -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Doctor <span class="required">*</span></label>

                @if($doctors->count() === 1)
                    <input class="form-input"
                           type="text"
                           value="{{ $doctors->first()->first_name }} {{ $doctors->first()->last_name }}"
                           disabled
                           style="background-color:#f3f4f6;cursor:not-allowed;">
                    <input type="hidden" name="doctor_id" id="appt_doctor_id" value="{{ $doctors->first()->id }}">
                @else
                    <select class="form-input" name="doctor_id" id="appt_doctor_id" required>
                        <option value="">-- Select Doctor --</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}">
                                Dr. {{ $doctor->first_name }} {{ $doctor->last_name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <!-- Time Slot -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Time Slot <span class="required">*</span></label>
                <select class="form-input" name="appointment_time" id="appt_time" required>
                    <option value="">-- Select Time Slot --</option>
                    <option value="09:00">9:00 AM – 9:30 AM</option>
                    <option value="09:30">9:30 AM – 10:00 AM</option>
                    <option value="10:00">10:00 AM – 10:30 AM</option>
                    <option value="10:30">10:30 AM – 11:00 AM</option>
                    <option value="11:00">11:00 AM – 11:30 AM</option>
                    <option value="11:30">11:30 AM – 12:00 PM</option>
                    <option value="12:00" data-lunch="true" disabled>12:00 PM – 1:00 PM (Lunch Break)</option>
                    <option value="13:00">1:00 PM – 1:30 PM</option>
                    <option value="13:30">1:30 PM – 2:00 PM</option>
                    <option value="14:00">2:00 PM – 2:30 PM</option>
                    <option value="14:30">2:30 PM – 3:00 PM</option>
                    <option value="15:00">3:00 PM – 3:30 PM</option>
                    <option value="15:30">3:30 PM – 4:00 PM</option>
                    <option value="16:00">4:00 PM – 4:30 PM</option>
                    <option value="16:30">4:30 PM – 5:00 PM</option>
                </select>
            </div>

            <!-- Reason -->
            <div class="form-group" style="margin-bottom:16px;">
                <label class="form-label">Reason <span class="required">*</span></label>
                <textarea class="form-input" name="reason" rows="3" required></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-back" onclick="closeModal('appointmentModal')">Cancel</button>
                <button type="submit" class="btn-confirm">Save Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- ================= JAVASCRIPT ================= -->
@php
    // Pass booked slots to JS (same structure as patient appointments page)
    $staffBookedSlotsJson = $bookedSlots->map(fn($s) => [
        'doctor_id'        => $s->doctor_id,
        'appointment_date' => \Carbon\Carbon::parse($s->appointment_date)->format('Y-m-d'),
        'appointment_time' => substr($s->appointment_time, 0, 5),
    ])->values()->toArray();
@endphp

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== BOOKED SLOTS FROM SERVER =====
    const bookedSlots = @json($staffBookedSlotsJson);

    // ===== MODAL HELPERS =====
    window.openModal = function (id) {
        document.getElementById(id).style.display = 'flex';

        if (id === 'addPatientModal') {
            const birthdateInput = document.getElementById('birthdateInput');
            const agePreview     = document.getElementById('agePreview');

            if (!birthdateInput.hasAttribute('data-listener')) {
                birthdateInput.addEventListener('change', function () {
                    agePreview.value = computeAge(this.value);
                });
                birthdateInput.setAttribute('data-listener', 'true');
            }

            agePreview.value = birthdateInput.value ? computeAge(birthdateInput.value) : '';
        }
    };

    window.closeModal = function (id) {
        document.getElementById(id).style.display = 'none';
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // Auto-open add modal if validation errors
    @if($errors->any())
        openModal('addPatientModal');
    @endif

    // ===== APPOINTMENT MODAL: TIME SLOT LOGIC =====
    const apptDateInput   = document.getElementById('appt_date');
    const apptDoctorInput = document.getElementById('appt_doctor_id');
    const apptTimeSelect  = document.getElementById('appt_time');

    // Set default date to today when modal opens
    window.openAppointmentModal = function (patientId, patientType) {
        document.getElementById('appointment_patient_id').value   = patientId;
        document.getElementById('appointment_patient_type').value = patientType;

        // Reset form fields
        apptTimeSelect.value = '';
        if (apptDoctorInput && apptDoctorInput.tagName === 'SELECT') {
            apptDoctorInput.value = '';
        }

        // Default date to today
        if (!apptDateInput.value) {
            apptDateInput.value = new Date().toISOString().split('T')[0];
        }

        updateStaffTimeSlots();
        openModal('appointmentModal');
    };

    // Re-run slot update when date or doctor changes
    apptDateInput.addEventListener('change', updateStaffTimeSlots);

    if (apptDoctorInput && apptDoctorInput.tagName === 'SELECT') {
        apptDoctorInput.addEventListener('change', updateStaffTimeSlots);
    }

    function updateStaffTimeSlots() {
        const selectedDate   = apptDateInput.value;
        const selectedDoctor = apptDoctorInput ? apptDoctorInput.value : '';
        const today          = new Date().toISOString().split('T')[0];
        const now            = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        apptTimeSelect.querySelectorAll('option').forEach(function (option) {
            if (!option.value) return; // skip placeholder

            // Save original label
            if (!option.getAttribute('data-base-text')) {
                option.setAttribute('data-base-text', option.textContent);
            }
            const baseText = option.getAttribute('data-base-text');

            // Always disable lunch break
            if (option.dataset.lunch) {
                option.disabled    = true;
                option.textContent = baseText;
                return;
            }

            const [h, m]        = option.value.split(':').map(Number);
            const optionMinutes = h * 60 + m;

            // Disable past times when today is selected
            if (selectedDate === today && optionMinutes <= currentMinutes) {
                option.disabled    = true;
                option.textContent = baseText + ' (Unavailable)';
                return;
            }

            // Disable booked slots for selected doctor + date
            const isBooked = selectedDoctor && selectedDate && bookedSlots.some(function (slot) {
                return String(slot.doctor_id)  === String(selectedDoctor) &&
                       slot.appointment_date   === selectedDate &&
                       slot.appointment_time   === option.value;
            });

            option.disabled    = isBooked;
            option.textContent = isBooked ? baseText + ' – Booked' : baseText;
        });
    }

    // ===== VIEW PATIENT MODAL =====
    window.openViewModal = function (btn) {
        const d = btn.dataset;

        document.getElementById('v_fname').value     = d.fname     || '—';
        document.getElementById('v_mname').value     = d.mname     || '—';
        document.getElementById('v_lname').value     = d.lname     || '—';
        document.getElementById('v_suffix').value    = d.suffix    || '—';
        document.getElementById('v_birthdate').value = d.birthdate || '—';
        document.getElementById('v_age').value       = d.age       || '—';
        document.getElementById('v_gender').value    = d.gender    || '—';
        document.getElementById('v_civil').value     = d.civil     || '—';
        document.getElementById('v_address').value   = d.address   || '—';
        document.getElementById('v_contact').value   = d.contact   || '—';

        document.getElementById('v_blood').value     = d.blood     || '—';
        document.getElementById('v_allergies').value = d.allergies || '—';

        document.getElementById('v_emergency_name').value    = d.emergencyName    || '—';
        document.getElementById('v_emergency_contact').value = d.emergencyContact || '—';
        document.getElementById('v_relationship').value      = d.relationship     || '—';
        document.getElementById('v_emergency_address').value = d.emergencyAddress || '—';

        document.getElementById('v_email').value        = d.email       || '—';
        document.getElementById('v_username').value     = d.username    || '—';
        document.getElementById('v_patient_type').value = d.patientType || '—';
        document.getElementById('v_status').value       = d.status      || '—';
        document.getElementById('v_approval').value     = d.approval    || '—';

        openModal('viewModal');
    };

    // ===== AGE COMPUTATION =====
    window.computeAge = function (dateStr) {
        const birth = new Date(dateStr);
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        return age >= 0 ? age : '';
    };

});
</script>

@endsection