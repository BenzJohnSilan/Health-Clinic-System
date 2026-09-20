@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-appointments.css') }}">
@endsection

@section('content')

<!-- ================= PAGE HEADER ================= -->
<div class="page-header">
    <div class="header-left">
        <h1>My Appointments</h1>
    </div>
    <div class="header-right">
        @if($profileComplete)
            <button class="btn-add" id="openModalBtn">
                <i class="fa-solid fa-calendar-plus"></i>
                Book Appointment
            </button>
        @else
            <a href="{{ route('patient.settings') }}" class="btn-add" title="Complete your required patient information first">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Complete Profile to Book
            </a>
        @endif
    </div>
</div>

<!-- ================= ALERTS ================= -->
@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif
@if(session('error') && !$errors->any())
    <div class="alert-error">{{ session('error') }}</div>
@endif
@if(!$profileComplete)
    <div class="alert-error">
        ⚠️ Your patient profile is incomplete. Please complete the required information before booking an appointment.
    </div>
@endif

<!-- ================= APPOINTMENTS TABLE (Desktop/Tablet) ================= -->
<div class="appointments-table">
    <table>
        <thead>
            <tr>
                <th>Ref. No.</th>
                <th>Schedule</th>
                <th>Doctor</th>
                <th>Reason</th>
                <th>Status</th>
                <th style="width: 180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($appointments as $appointment)
                <tr>
                    <td>
                        <span class="ref-no">
                            {{ $appointment->reference_no ?? '—' }}
                        </span>
                    </td>

                    <td>
                        {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y') }}
                        &mdash;
                        {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                    </td>

                    <td>
                        {{ $appointment->doctor->first_name ?? 'N/A' }}
                        {{ $appointment->doctor->last_name ?? '' }}
                    </td>

                    <td>
                        <span class="reason-preview">{{ $appointment->reason }}</span>
                    </td>

                    <td>
                        <span class="status {{ \Illuminate\Support\Str::slug($appointment->status) }}">
                            {{ $appointment->status }}
                        </span>
                    </td>

                    <td>
                        <div class="action-buttons">
                            <a href="{{ route('patient.appointments.show', $appointment->id) }}" class="btn-view-appt">
                                View
                            </a>

                            @if(in_array($appointment->status, ['Pending', 'Approved', 'Rescheduled']))
                                @php
                                    $dateOnly       = \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d');
                                    $timeOnly       = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i:s');
                                    $apptDateTime   = \Carbon\Carbon::parse($dateOnly . ' ' . $timeOnly);
                                    $minutesUntil   = \Carbon\Carbon::now()->diffInMinutes($apptDateTime, false);
                                    $withinTwoHours = $appointment->status === 'Approved' && $minutesUntil <= 120;
                                @endphp

                                @if($withinTwoHours)
                                    <span class="no-cancel-hint" title="Cannot cancel within 2 hours of an approved appointment">
                                        Locked
                                    </span>
                                @else
                                    <button
                                        class="btn-cancel-appt"
                                        onclick="openCancel({{ $appointment->id }}, '{{ $appointment->status }}')">
                                        Cancel
                                    </button>
                                @endif
                            @else
                                <button
                                    type="button"
                                    class="btn-cancel-appt"
                                    disabled
                                    title="This appointment can no longer be cancelled">
                                    Cancel
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-row">
                        No appointments found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- ================= APPOINTMENTS CARDS (Mobile) ================= -->
<div class="appointments-cards">
    @forelse($appointments as $appointment)
        <div class="appt-card">
            <div class="appt-card-top">
                <span class="ref-no">{{ $appointment->reference_no ?? '—' }}</span>
                <span class="status {{ \Illuminate\Support\Str::slug($appointment->status) }}">
                    {{ $appointment->status }}
                </span>
            </div>

            <div class="appt-card-row">
                <span class="appt-card-label">Doctor</span>
                <span class="appt-card-value">
                    {{ $appointment->doctor->first_name ?? 'N/A' }}
                    {{ $appointment->doctor->last_name ?? '' }}
                </span>
            </div>

            <div class="appt-card-row">
                <span class="appt-card-label">Schedule</span>
                <span class="appt-card-value">
                    {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}
                    &bull;
                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                </span>
            </div>

            <div class="appt-card-row">
                <span class="appt-card-label">Reason</span>
                <span class="appt-card-value reason-preview">{{ $appointment->reason }}</span>
            </div>

            <div class="appt-card-actions">
                <a href="{{ route('patient.appointments.show', $appointment->id) }}" class="btn-view-appt">
                    View
                </a>

                @if(in_array($appointment->status, ['Pending', 'Approved', 'Rescheduled']))
                    @php
                        $dateOnly       = \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d');
                        $timeOnly       = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i:s');
                        $apptDateTime   = \Carbon\Carbon::parse($dateOnly . ' ' . $timeOnly);
                        $minutesUntil   = \Carbon\Carbon::now()->diffInMinutes($apptDateTime, false);
                        $withinTwoHours = $appointment->status === 'Approved' && $minutesUntil <= 120;
                    @endphp

                    @if($withinTwoHours)
                        <span class="no-cancel-hint" title="Cannot cancel within 2 hours of an approved appointment">
                            Locked
                        </span>
                    @else
                        <button
                            class="btn-cancel-appt"
                            onclick="openCancel({{ $appointment->id }}, '{{ $appointment->status }}')">
                            Cancel
                        </button>
                    @endif
                @else
                    <button
                        type="button"
                        class="btn-cancel-appt"
                        disabled
                        title="This appointment can no longer be cancelled">
                        Cancel
                    </button>
                @endif
            </div>
        </div>
    @empty
        <div class="empty-row">No appointments found.</div>
    @endforelse
</div>

<!-- ================= PAGINATION ================= -->
<div class="pagination-wrapper">
        <div class="pagination-info">
            @if($appointments->total() > 0)
                Showing <strong>{{ $appointments->firstItem() }}–{{ $appointments->lastItem() }}</strong>
                of <strong>{{ $appointments->total() }}</strong> result{{ $appointments->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($appointments->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $appointments->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $appointments->currentPage();
                $lastPage    = $appointments->lastPage();

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
                    <a class="page-btn" href="{{ $appointments->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($appointments->hasMorePages())
                <a class="page-btn" href="{{ $appointments->nextPageUrl() }}">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @else
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            @endif

        </nav>
    </div>


<!-- ================= BOOK APPOINTMENT MODAL ================= -->
<div id="appointmentModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" id="closeModalBtn" aria-label="Close">&times;</button>
        <h3 class="modal-title">Book an Appointment</h3>

        <!-- ERROR MESSAGES -->
        @if(session('error') || $errors->any())
            <div class="alert-error" style="margin-bottom: 16px;">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i>
                @if(session('error'))
                    {{ session('error') }}
                @endif
                @if($errors->any())
                    <ul style="margin: 4px 0 0; padding-left: 18px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <!-- NO DOCTORS AVAILABLE ON SELECTED DATE (client-side hint only —
             the server-side check in PatientAppointmentController::store()
             is what actually enforces this and cannot be bypassed). -->
        <div id="noAvailabilityMsg" class="alert-error" style="display:none; margin-bottom: 16px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i>
            <span id="noAvailabilityMsgText">No doctors are available on this date. Please select another date.</span>
        </div>

        <form method="POST" action="{{ route('patient.appointments.store') }}">
            @csrf

            <!-- Date -->
            <div class="form-group">
                <label class="form-label" for="appointment_date">Date</label>
                <input class="form-input"
                       type="date"
                       name="appointment_date"
                       id="appointment_date"
                       required
                       value="{{ old('appointment_date') }}"
                       min="{{ date('Y-m-d') }}">
            </div>

            <!-- Doctor -->
            <div class="form-group">
                <label class="form-label" for="doctor_id">Doctor</label>

                @if($doctors->count() === 1)
                    <input class="form-input"
                           type="text"
                           value="{{ $doctors->first()->first_name }} {{ $doctors->first()->last_name }}"
                           disabled
                           style="background-color: #f3f4f6; cursor: not-allowed;">
                    <input type="hidden" name="doctor_id" id="doctor_id" value="{{ $doctors->first()->id }}">
                @else
                    <select class="form-input" name="doctor_id" id="doctor_id" required>
                        <option value="">-- Choose Doctor --</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}" {{ old('doctor_id') == $doctor->id ? 'selected' : '' }}>
                                {{ $doctor->first_name }} {{ $doctor->last_name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <!-- Time Slot -->
            <div class="form-group">
                <label class="form-label" for="appointment_time">Available Time</label>
                <select class="form-input" name="appointment_time" id="appointment_time" required>
                    <option value="">-- Select Time Slot --</option>
                    <option value="09:00" {{ old('appointment_time') == '09:00' ? 'selected' : '' }}>9:00 AM – 9:30 AM</option>
                    <option value="09:30" {{ old('appointment_time') == '09:30' ? 'selected' : '' }}>9:30 AM – 10:00 AM</option>
                    <option value="10:00" {{ old('appointment_time') == '10:00' ? 'selected' : '' }}>10:00 AM – 10:30 AM</option>
                    <option value="10:30" {{ old('appointment_time') == '10:30' ? 'selected' : '' }}>10:30 AM – 11:00 AM</option>
                    <option value="11:00" {{ old('appointment_time') == '11:00' ? 'selected' : '' }}>11:00 AM – 11:30 AM</option>
                    <option value="11:30" {{ old('appointment_time') == '11:30' ? 'selected' : '' }}>11:30 AM – 12:00 PM</option>
                    <option value="12:00" data-lunch="true" disabled>12:00 PM – 1:00 PM (Lunch Break)</option>
                    <option value="13:00" {{ old('appointment_time') == '13:00' ? 'selected' : '' }}>1:00 PM – 1:30 PM</option>
                    <option value="13:30" {{ old('appointment_time') == '13:30' ? 'selected' : '' }}>1:30 PM – 2:00 PM</option>
                    <option value="14:00" {{ old('appointment_time') == '14:00' ? 'selected' : '' }}>2:00 PM – 2:30 PM</option>
                    <option value="14:30" {{ old('appointment_time') == '14:30' ? 'selected' : '' }}>2:30 PM – 3:00 PM</option>
                    <option value="15:00" {{ old('appointment_time') == '15:00' ? 'selected' : '' }}>3:00 PM – 3:30 PM</option>
                    <option value="15:30" {{ old('appointment_time') == '15:30' ? 'selected' : '' }}>3:30 PM – 4:00 PM</option>
                    <option value="16:00" {{ old('appointment_time') == '16:00' ? 'selected' : '' }}>4:00 PM – 4:30 PM</option>
                    <option value="16:30" {{ old('appointment_time') == '16:30' ? 'selected' : '' }}>4:30 PM – 5:00 PM</option>
                </select>
            </div>

            <!-- Reason -->
            <div class="form-group">
                <label class="form-label" for="reason">Reason for Visit</label>
                <textarea class="form-input"
                          name="reason"
                          id="reason"
                          rows="3"
                          required>{{ old('reason') }}</textarea>
            </div>

            <!-- Buttons -->
            <div class="modal-actions">
                <button type="button" class="btn-back" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="btn-confirm">Book Appointment</button>
            </div>

        </form>
    </div>
</div>

<!-- ===== CANCEL CONFIRMATION MODAL ===== -->
<div id="cancelModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3 class="modal-title">Cancel Appointment</h3>
        <p class="modal-message" id="cancelModalMessage">
            Are you sure you want to cancel this appointment? This action cannot be undone.
        </p>

        <form id="cancelForm" method="POST">
            @csrf
            @method('DELETE')

            <div class="modal-actions">
                <button type="button" onclick="closeCancel()" class="btn-back">No, Go Back</button>
                <button type="submit" class="btn-danger">Yes, Cancel Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- ================= JAVASCRIPT ================= -->
@php
    $bookedSlotsJson = $bookedSlots->map(fn($s) => [
        'doctor_id'        => $s->doctor_id,
        'appointment_date' => \Carbon\Carbon::parse($s->appointment_date)->format('Y-m-d'),
        'appointment_time' => substr($s->appointment_time, 0, 5),
    ])->values()->toArray();
@endphp

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== BOOKED SLOTS FROM SERVER =====
    const bookedSlots = @json($bookedSlotsJson);

    // ===== DOCTOR SCHEDULE DATA (My Schedule feature) =====
    // `weeklyAvailability` only contains entries for doctors who have at
    // least one configured day. A doctor with NO entry here (or with a
    // day missing from their entry) has NOT configured a schedule and
    // MUST be treated as unavailable — there is no "unrestricted"
    // fallback. This mirrors DoctorAvailabilityService on the backend,
    // which is the authoritative check and cannot be bypassed by this
    // script being disabled, edited, or skipped entirely.
    const weeklyAvailability = @json($weeklyAvailability);
    const exceptionsByDoctor = @json($exceptionsByDoctor);

    function dayNameFromDateString(dateStr) {
        // Parse as local calendar date (avoid UTC-shift off-by-one).
        const [y, m, d] = dateStr.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return days[date.getDay()];
    }

    function timeFitsInSlots(timeStr, slots) {
        if (!slots || slots.length === 0) return false;
        const [h, m] = timeStr.split(':').map(Number);
        const start = h * 60 + m;
        const end = start + 30; // appointments are 30-minute slots

        return slots.some(slot => {
            const [sh, sm] = slot.start.split(':').map(Number);
            const [eh, em] = slot.end.split(':').map(Number);
            const slotStart = sh * 60 + sm;
            const slotEnd = eh * 60 + em;
            return start >= slotStart && end <= slotEnd;
        });
    }

    // Returns true only if the time is bookable for this doctor/date given
    // their weekly schedule + any schedule exception. A doctor with no
    // schedule configured, no row for that day, a day marked Unavailable,
    // or an Available day with no slots is ALWAYS unavailable — matching
    // the backend exactly (no special-casing for "unconfigured" doctors).
    function isTimeAvailableForDoctor(doctorId, dateStr, timeStr) {
        if (!doctorId || !dateStr) return false;

        const doctorExceptions = exceptionsByDoctor[doctorId] || {};
        const exception = doctorExceptions[dateStr];

        if (exception) {
            if (exception.type === 'Unavailable') return false;
            // Custom Hours
            return timeFitsInSlots(timeStr, [{ start: exception.start, end: exception.end }]);
        }

        const dayName = dayNameFromDateString(dateStr);
        const weekly = (weeklyAvailability[doctorId] || {})[dayName];

        if (!weekly || weekly.status !== 'Available') return false;

        return timeFitsInSlots(timeStr, weekly.slots);
    }

    // ===== ADD APPOINTMENT MODAL =====
    const apptModal      = document.getElementById('appointmentModal');
    const openModalBtn   = document.getElementById('openModalBtn');
    const closeModalBtn  = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');

    // openModalBtn only exists when the patient's profile is complete
    // (see the profileComplete check in the page header above) — guard
    // against it being null so the rest of this script (time slots,
    // cancel modal, etc.) still runs for patients with an incomplete profile.
    if (openModalBtn) {
        openModalBtn.addEventListener('click', () => {
            apptModal.style.display = 'flex';
            setDefaultDate();
            refreshAvailability();
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => apptModal.style.display = 'none');
    }

    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', () => apptModal.style.display = 'none');
    }

    if (apptModal) {
        apptModal.addEventListener('click', function (e) {
            if (e.target === this) this.style.display = 'none';
        });
    }

    function setDefaultDate() {
        const dateInput = document.getElementById('appointment_date');
        if (dateInput && !dateInput.value) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }
    }

    // ===== TIME SLOT + DOCTOR AVAILABILITY UPDATER =====
    const dateInput   = document.getElementById('appointment_date');
    const doctorInput = document.getElementById('doctor_id');
    const noAvailabilityMsg = document.getElementById('noAvailabilityMsg');

    // All selectable (non-placeholder) time-slot <option> elements.
    const timeOptionEls = Array.from(document.querySelectorAll('#appointment_time option'))
        .filter(option => option.value);

    if (dateInput) {
        dateInput.addEventListener('change', refreshAvailability);
    }

    if (doctorInput && doctorInput.tagName === 'SELECT') {
        doctorInput.addEventListener('change', refreshAvailability);
    }

    /**
     * How many of the fixed daily time slots are actually bookable for
     * this doctor on this date (not lunch, not in the past, not already
     * booked, and inside the doctor's configured schedule/exception).
     */
    function countBookableSlotsForDoctor(doctorId, dateStr) {
        if (!doctorId || !dateStr) return 0;

        const today          = new Date().toISOString().split('T')[0];
        const now            = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        let count = 0;

        timeOptionEls.forEach(option => {
            if (option.dataset.lunch) return;

            const [h, m]        = option.value.split(':').map(Number);
            const optionMinutes = h * 60 + m;

            if (dateStr === today && optionMinutes <= currentMinutes) return;

            const isBooked = bookedSlots.some(slot =>
                String(slot.doctor_id)  === String(doctorId) &&
                slot.appointment_date   === dateStr &&
                slot.appointment_time   === option.value
            );
            if (isBooked) return;

            if (!isTimeAvailableForDoctor(doctorId, dateStr, option.value)) return;

            count++;
        });

        return count;
    }

    // Greys out doctors (in a <select>) with zero bookable slots on the
    // selected date, and shows a clear message when NO doctor at all has
    // any availability on that date.
    function updateDoctorAvailabilityForDate() {
        if (!noAvailabilityMsg) return;

        const selectedDate = dateInput ? dateInput.value : '';

        if (!selectedDate) {
            noAvailabilityMsg.style.display = 'none';
            return;
        }

        let anyDoctorAvailable = false;

        if (doctorInput && doctorInput.tagName === 'SELECT') {
            Array.from(doctorInput.options).forEach(option => {
                if (!option.value) return;

                if (!option.dataset.baseText) {
                    option.dataset.baseText = option.textContent;
                }

                const bookable = countBookableSlotsForDoctor(option.value, selectedDate) > 0;

                option.disabled    = !bookable;
                option.textContent = bookable
                    ? option.dataset.baseText
                    : option.dataset.baseText + ' (Unavailable this date)';

                if (bookable) anyDoctorAvailable = true;
            });

            // If the currently selected doctor just became unavailable,
            // clear the selection so the patient must pick a valid one.
            const selected = doctorInput.selectedOptions && doctorInput.selectedOptions[0];
            if (doctorInput.value && selected && selected.disabled) {
                doctorInput.value = '';
            }
        } else if (doctorInput && doctorInput.value) {
            // Single-doctor form (hidden input) — same rule applies.
            anyDoctorAvailable = countBookableSlotsForDoctor(doctorInput.value, selectedDate) > 0;
        }

        noAvailabilityMsg.style.display = anyDoctorAvailable ? 'none' : 'flex';
    }

    function updateTimeSlots() {
        const selectedDate   = dateInput ? dateInput.value : '';
        const selectedDoctor = doctorInput ? doctorInput.value : '';
        const today          = new Date().toISOString().split('T')[0];
        const now            = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        timeOptionEls.forEach(option => {
            const [h, m]        = option.value.split(':').map(Number);
            const optionMinutes = h * 60 + m;

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

            // Disable past times when today is selected
            if (selectedDate === today && optionMinutes <= currentMinutes) {
                option.disabled    = true;
                option.textContent = baseText + ' (Unavailable)';
                return;
            }

            // Disable booked slots for selected doctor + date
            const isBooked = selectedDoctor && selectedDate && bookedSlots.some(slot =>
                String(slot.doctor_id)  === String(selectedDoctor) &&
                slot.appointment_date   === selectedDate &&
                slot.appointment_time   === option.value
            );

            // Disable times outside the doctor's configured schedule
            // (weekly availability + schedule exceptions). With no doctor
            // or no date selected yet, times stay enabled so the patient
            // can pick freely; once both are chosen, the real rule applies.
            const isOutsideSchedule = selectedDoctor && selectedDate &&
                !isTimeAvailableForDoctor(selectedDoctor, selectedDate, option.value);

            const isUnavailable = isBooked || isOutsideSchedule;

            option.disabled    = isUnavailable;
            option.textContent = isBooked
                ? baseText + ' – Booked'
                : (isOutsideSchedule ? baseText + ' – Unavailable' : baseText);
        });
    }

    function refreshAvailability() {
        updateDoctorAvailabilityForDate();
        updateTimeSlots();
    }

    // Auto-open modal if there were validation errors
    @if(session('error') || $errors->any())
        if (apptModal) {
            apptModal.style.display = 'flex';
        }
    @endif

    refreshAvailability();

    // ===== CANCEL APPOINTMENT MODAL =====
    window.openCancel = function (id, status) {
        const msg = document.getElementById('cancelModalMessage');
        if (msg) {
            msg.textContent = status === 'Approved'
                ? 'This appointment has already been approved. Are you sure you want to cancel it? This action cannot be undone.'
                : 'Are you sure you want to cancel this appointment? This action cannot be undone.';
        }

        const cancelForm = document.getElementById('cancelForm');
        if (cancelForm) {
            cancelForm.action = `/patient/appointments/${id}/cancel`;
        }

        const cancelModalEl = document.getElementById('cancelModal');
        if (cancelModalEl) {
            cancelModalEl.style.display = 'flex';
        }
    };

    window.closeCancel = function () {
        const cancelModalEl = document.getElementById('cancelModal');
        if (cancelModalEl) {
            cancelModalEl.style.display = 'none';
        }
    };

    const cancelModalEl = document.getElementById('cancelModal');
    if (cancelModalEl) {
        cancelModalEl.addEventListener('click', function (e) {
            if (e.target === this) closeCancel();
        });
    }

});
</script>

@endsection