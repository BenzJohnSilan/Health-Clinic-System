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
        <button class="btn-add" id="openModalBtn">
            <i class="fa-solid fa-calendar-plus"></i>
            Add Appointment
        </button>
    </div>
</div>

<!-- ================= ALERTS ================= -->
@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif
@if(session('error') && !$errors->any())
    <div class="alert-error">{{ session('error') }}</div>
@endif

<!-- ================= APPOINTMENTS TABLE ================= -->
<div class="appointments-table">
    <table>
        <thead>
            <tr>
                <th>Ref. No.</th>
                <th>Patient Name</th>
                <th>Appointment Schedule</th>
                <th>Doctor</th>
                <th>Reason</th>
                <th>Status</th>
                <th style="width: 130px;">Actions</th>
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
                        {{ $appointment->patient->first_name ?? auth()->user()->first_name }}
                        {{ $appointment->patient->last_name ?? auth()->user()->last_name }}
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

                    <td>{{ $appointment->reason }}</td>

                    <td>
                        <span class="status {{ strtolower($appointment->status) }}">
                            {{ $appointment->status }}
                        </span>
                        
                    </td>

                    <td>
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
                            <span class="no-action">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty-row">
                        No appointments found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
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


<!-- ================= ADD APPOINTMENT MODAL ================= -->
<div id="appointmentModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" id="closeModalBtn" aria-label="Close">&times;</button>
        <h3 class="modal-title">Add New Appointment</h3>

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

            <!-- Time Slot -->
            <div class="form-group">
                <label class="form-label" for="appointment_time">Time Slot</label>
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

            <!-- Reason -->
            <div class="form-group">
                <label class="form-label" for="reason">Reason / Notes</label>
                <textarea class="form-input"
                          name="reason"
                          id="reason"
                          rows="3"
                          required>{{ old('reason') }}</textarea>
            </div>

            <!-- Buttons -->
            <div class="modal-actions">
                <button type="button" class="btn-back" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="btn-confirm">Save Appointment</button>
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

    // ===== ADD APPOINTMENT MODAL =====
    const apptModal      = document.getElementById('appointmentModal');
    const openModalBtn   = document.getElementById('openModalBtn');
    const closeModalBtn  = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');

    openModalBtn.addEventListener('click', () => {
        apptModal.style.display = 'flex';
        setDefaultDate();
        updateTimeSlots();
    });

    closeModalBtn.addEventListener('click',  () => apptModal.style.display = 'none');
    cancelModalBtn.addEventListener('click', () => apptModal.style.display = 'none');

    apptModal.addEventListener('click', function (e) {
        if (e.target === this) this.style.display = 'none';
    });

    function setDefaultDate() {
        const dateInput = document.getElementById('appointment_date');
        if (!dateInput.value) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }
    }

    // ===== TIME SLOT UPDATER =====
    const dateInput   = document.getElementById('appointment_date');
    const doctorInput = document.getElementById('doctor_id');

    dateInput.addEventListener('change', updateTimeSlots);

    if (doctorInput && doctorInput.tagName === 'SELECT') {
        doctorInput.addEventListener('change', updateTimeSlots);
    }

    function updateTimeSlots() {
        const selectedDate   = dateInput.value;
        const selectedDoctor = doctorInput ? doctorInput.value : '';
        const today          = new Date().toISOString().split('T')[0];
        const now            = new Date();
        const currentMinutes = now.getHours() * 60 + now.getMinutes();

        document.querySelectorAll('#appointment_time option').forEach(option => {
            if (!option.value) return;

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

            option.disabled    = isBooked;
            option.textContent = isBooked ? baseText + ' – Booked' : baseText;
        });
    }

    // Auto-open modal if there were validation errors
    @if(session('error') || $errors->any())
        apptModal.style.display = 'flex';
    @endif

    updateTimeSlots();

    // ===== CANCEL APPOINTMENT MODAL =====
    window.openCancel = function (id, status) {
        const msg = document.getElementById('cancelModalMessage');
        msg.textContent = status === 'Approved'
            ? 'This appointment has already been approved. Are you sure you want to cancel it? This action cannot be undone.'
            : 'Are you sure you want to cancel this appointment? This action cannot be undone.';

        document.getElementById('cancelForm').action = `/patient/appointments/${id}/cancel`;
        document.getElementById('cancelModal').style.display = 'flex';
    };

    window.closeCancel = function () {
        document.getElementById('cancelModal').style.display = 'none';
    };

    document.getElementById('cancelModal').addEventListener('click', function (e) {
        if (e.target === this) closeCancel();
    });

});
</script>

@endsection