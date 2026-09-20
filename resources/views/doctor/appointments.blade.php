@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-appointments.css') }}">
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Appointments</h1>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================= SEARCH & FILTERS ================= -->
    <form method="GET" action="{{ route('doctor.appointments.index') }}" class="filters-bar">

        <div class="filter-field filter-field-search">
            <i class="fa-solid fa-magnifying-glass filter-search-icon"></i>
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Search patient name or reference no."
                class="filter-input filter-search-input"
            >
        </div>

        <div class="filter-field">
            <select name="status" class="filter-input filter-select">
                <option value="All Active" @selected($statusFilter === 'All Active')>All Active</option>
                <option value="Approved" @selected($statusFilter === 'Approved')>Approved</option>
                <option value="Rescheduled" @selected($statusFilter === 'Rescheduled')>Rescheduled</option>
                <option value="Checked In" @selected($statusFilter === 'Checked In')>Checked In</option>
                <option value="In Progress" @selected($statusFilter === 'In Progress')>In Progress</option>
            </select>
        </div>

        <div class="filter-field">
            <select name="date_filter" class="filter-input filter-select">
                <option value="All Dates" @selected($dateFilter === 'All Dates')>All Dates</option>
                <option value="Today" @selected($dateFilter === 'Today')>Today</option>
                <option value="Upcoming" @selected($dateFilter === 'Upcoming')>Upcoming</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-filter-apply">
                <i class="fa-solid fa-filter"></i>
                Filter
            </button>
            @if($search !== '' || $statusFilter !== 'All Active' || $dateFilter !== 'All Dates')
                <a href="{{ route('doctor.appointments.index') }}" class="btn-filter-clear">
                    Clear Filters
                </a>
            @endif
        </div>

    </form>

    <!-- ================= TABLE ================= -->
    <div class="table-container">

        <table class="appointments-table">

            <thead>
                <tr>
                    <th>Reference No.</th>
                    <th>Patient Name</th>
                    <th>Appointment Schedule</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($appointments as $appointment)

                @php
                    $date          = \Carbon\Carbon::parse($appointment->appointment_date);
                    $time          = \Carbon\Carbon::parse($appointment->appointment_time);
                    $isToday       = $date->isToday();
                    $appointmentEnd = $time->copy()->addMinutes(30);

                    $appointmentEndDateTime = \Carbon\Carbon::parse(
                        $date->format('Y-m-d') . ' ' . $appointmentEnd->format('H:i:s')
                    );

                    // The 30-minute slot has ended.
                    $isNoShowEligible = \Carbon\Carbon::now()->gte($appointmentEndDateTime)
                        && in_array($appointment->status, ['Approved', 'Rescheduled'], true);

                    // Same eligibility rule enforced server-side in
                    // StaffAppointmentController::reschedule() /
                    // DoctorAppointmentController::reschedule().
                    $isReschedulable = in_array($appointment->status, ['Approved', 'Rescheduled'], true);

                    // ── Consult button state, per Appointment status ──────
                    // Approved/Rescheduled : waiting for Staff Check-In, no
                    //                        consultation access yet.
                    // Checked In           : Staff confirmed arrival — Doctor
                    //                        can open the page (still
                    //                        read-only there until Start
                    //                        Consultation is clicked).
                    // In Progress          : consultation already started.
                    $consultLabel = match ($appointment->status) {
                        'Checked In'  => 'Start Consultation',
                        'In Progress' => 'Continue Consultation',
                        default       => 'Waiting for Check In',
                    };
                    $consultEnabled = $isToday
                        && in_array($appointment->status, ['Checked In', 'In Progress'], true);
                @endphp

                <tr>

                    <!-- Reference No. -->
                    <td class="ref-no-cell">{{ $appointment->reference_no ?? 'N/A' }}</td>

                    <!-- Patient Name -->
                    <td>
                        @if($appointment->patient)
                            {{ $appointment->patient->first_name }} {{ $appointment->patient->last_name }}
                        @elseif($appointment->walkinPatient)
                            {{ $appointment->walkinPatient->first_name }} {{ $appointment->walkinPatient->last_name }}
                        @else
                            N/A
                        @endif
                    </td>

                    <!-- Schedule -->
                    <td>
                        {{ $date->format('M d, Y') }}
                        <span style="margin-left:6px; color:#6b7280; font-size:13px;">
                            {{ $time->format('h:i A') }} – {{ $appointmentEnd->format('h:i A') }}
                        </span>
                    </td>

                    <!-- Reason -->
                    <td>{{ $appointment->reason ?? '—' }}</td>

                    <!-- Status -->
                    <td>
                        @if($isNoShowEligible)
                            <span class="status eligible-no-show">Eligible for No Show</span>
                        @elseif($appointment->status === 'Approved')
                            <span class="status approved">Approved</span>

                        @elseif($appointment->status === 'Checked In')
                            <span class="status checked-in">Checked In</span>

                        @elseif($appointment->status === 'In Progress')
                            <span class="status in-progress">In Progress</span>

                        @elseif($appointment->status === 'Rescheduled')
                            <span class="status rescheduled">Rescheduled</span>
                            @if($appointment->rescheduledBy)
                                <span class="reschedule-by">
                                    by {{ $appointment->rescheduledBy->first_name ?? ucfirst($appointment->rescheduled_by) }}
                                </span>
                            @endif

                        @elseif($appointment->status === 'No Show')
                            <span class="status no-show">No Show</span>
                        @endif
                    </td>

                    <!-- Actions -->
                    <td>
                        <div class="action-buttons">

                            {{-- Main action: Consult — label/state depends on
                                 status (see $consultLabel/$consultEnabled
                                 above). Always just navigates to the show
                                 page; the actual Start Consultation status
                                 transition happens on that page itself. --}}
                            @if($consultEnabled)
                                <a href="{{ route('doctor.appointments.show', $appointment->id) }}"
                                   class="btn-view">
                                    {{ $consultLabel }}
                                </a>
                            @else
                                <span class="btn-wrapper">
                                    <span class="btn-view-disabled" aria-disabled="true">{{ $consultLabel }}</span>
                                    <span class="tooltip">
                                        @if(!$isToday)
                                            Available on {{ $date->format('M d, Y') }}
                                        @else
                                            Waiting for Staff to check in the patient
                                        @endif
                                    </span>
                                </span>
                            @endif

                            {{-- Secondary actions: three-dot menu --}}
                            <div class="action-menu">
                                <button
                                    type="button"
                                    class="action-menu-toggle"
                                    aria-label="More appointment actions"
                                    aria-expanded="false"
                                    onclick="toggleActionMenu(this)">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>

                                <div class="action-menu-dropdown">

    {{-- Reschedule --}}
    @if($isReschedulable)
        <button
            type="button"
            class="action-menu-item reschedule-item"
            onclick="closeActionMenus(); openReschedule(
                {{ $appointment->id }},
                '{{ $date->format('Y-m-d') }}',
                '{{ $time->format('H:i') }}'
            )">
            <i class="fa-solid fa-calendar-days"></i>
            <span>Reschedule</span>
        </button>
    @else
        <button
            type="button"
            class="action-menu-item disabled-item"
            disabled
            title="This appointment can no longer be rescheduled">
            <i class="fa-solid fa-lock"></i>
            <span>Reschedule</span>
        </button>
    @endif


    {{-- No Show --}}
    @if($isNoShowEligible)
        <button
            type="button"
            class="action-menu-item no-show-item"
            onclick="closeActionMenus(); openNoShow({{ $appointment->id }})">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>No Show</span>
        </button>
    @else
        <button
            type="button"
            class="action-menu-item disabled-item"
            disabled
            title="No Show is only available after the scheduled time ends">
            <i class="fa-solid fa-lock"></i>
            <span>No Show</span>
        </button>
    @endif

</div>
                            </div>

                        </div>
                    </td>

                </tr>

                @empty
                <tr>
                    <td colspan="6" class="no-data">
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

</div>

<!-- ===== RESCHEDULE MODAL ===== -->
<div id="rescheduleModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-icon icon-reschedule">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div>
                <h3 class="modal-title">Reschedule Appointment</h3>
                <p class="modal-subtitle">Set a new date and time for this appointment.</p>
            </div>
            <button class="modal-close" onclick="closeReschedule()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="modal-divider"></div>

        <form id="rescheduleForm" method="POST">
            @csrf
            @method('PATCH')

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="rescheduleDate">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            New Date
                        </label>
                        <input
                            type="date"
                            name="appointment_date"
                            id="rescheduleDate"
                            min="{{ date('Y-m-d') }}"
                            required
                            class="form-input"
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="rescheduleTime">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            New Time
                        </label>
                        <input
                            type="time"
                            name="appointment_time"
                            id="rescheduleTime"
                            min="08:00"
                            max="17:00"
                            required
                            class="form-input"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="rescheduleReason">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                        </svg>
                        Reason for Rescheduling
                    </label>
                    <textarea
                        name="reschedule_reason"
                        id="rescheduleReason"
                        rows="3"
                        required
                        placeholder="Enter reason for rescheduling..."
                        class="form-input"
                    ></textarea>
                </div>
            </div>

            <div class="modal-divider"></div>
            <div class="modal-footer">
                <button type="button" onclick="closeReschedule()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-confirm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Confirm Reschedule
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ===== NO SHOW MODAL ===== -->
<div id="noShowModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-icon icon-no-show">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div>
                <h3 class="modal-title">Mark as No Show</h3>
                <p class="modal-subtitle">Are you sure the patient did not show up for this appointment?</p>
            </div>
            <button class="modal-close" onclick="closeNoShow()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="modal-divider"></div>

        <form id="noShowForm" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="No Show">

            <div class="modal-body">
                <p style="font-size:14px; color:#374151; margin:0;">
                    The scheduled appointment time has ended. Please confirm that the patient did not arrive.
                    The appointment will be marked as <strong>No Show</strong>.
                </p>
            </div>

            <div class="modal-divider"></div>
            <div class="modal-footer">
                <button type="button" onclick="closeNoShow()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-confirm-danger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Confirm No Show
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>
/* ===== Three-Dot Action Menu ===== */
function toggleActionMenu(button) {
    const menu = button.closest('.action-menu');
    const dropdown = menu.querySelector('.action-menu-dropdown');
    const isOpen = menu.classList.contains('open');

    closeActionMenus();

    if (!isOpen) {
        menu.classList.add('open');
        button.setAttribute('aria-expanded', 'true');
        dropdown.style.display = 'block';

        // Position the dropdown using viewport coordinates so it is not
        // clipped by the table's horizontal scroll container.
        const rect = button.getBoundingClientRect();
        const menuWidth = dropdown.offsetWidth;
        const menuHeight = dropdown.offsetHeight;
        const gap = 6;
        const viewportPadding = 8;

        let left = rect.right - menuWidth;
        let top = rect.bottom + gap;

        if (left < viewportPadding) {
            left = viewportPadding;
        }
        if (left + menuWidth > window.innerWidth - viewportPadding) {
            left = window.innerWidth - menuWidth - viewportPadding;
        }

        // Open upward when there is not enough room below the button.
        if (top + menuHeight > window.innerHeight - viewportPadding) {
            top = rect.top - menuHeight - gap;
        }
        if (top < viewportPadding) {
            top = viewportPadding;
        }

        dropdown.style.left = `${left}px`;
        dropdown.style.top = `${top}px`;
    }
}

function closeActionMenus() {
    document.querySelectorAll('.action-menu.open').forEach(menu => {
        menu.classList.remove('open');
        const toggle = menu.querySelector('.action-menu-toggle');
        const dropdown = menu.querySelector('.action-menu-dropdown');

        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        if (dropdown) {
            dropdown.style.display = '';
            dropdown.style.left = '';
            dropdown.style.top = '';
        }
    });
}

document.addEventListener('click', function (event) {
    if (!event.target.closest('.action-menu')) {
        closeActionMenus();
    }
});

window.addEventListener('scroll', closeActionMenus, true);
window.addEventListener('resize', closeActionMenus);

/* ===== Reschedule Modal ===== */
function openReschedule(id, date, time) {
    document.getElementById('rescheduleDate').value = date;
    document.getElementById('rescheduleTime').value = time;
    document.getElementById('rescheduleReason').value = '';
    document.getElementById('rescheduleForm').action = `/doctor/appointments/${id}/reschedule`;

    const modal = document.getElementById('rescheduleModal');
    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('modal-visible'));
}

function closeReschedule() {
    const modal = document.getElementById('rescheduleModal');
    modal.classList.remove('modal-visible');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
}

document.getElementById('rescheduleModal').addEventListener('click', function (e) {
    if (e.target === this) closeReschedule();
});

/* ===== No Show Modal ===== */
function openNoShow(id) {
    document.getElementById('noShowForm').action = `/doctor/appointments/${id}`;

    const modal = document.getElementById('noShowModal');
    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('modal-visible'));
}

function closeNoShow() {
    const modal = document.getElementById('noShowModal');
    modal.classList.remove('modal-visible');
    setTimeout(() => { modal.style.display = 'none'; }, 200);
}

document.getElementById('noShowModal').addEventListener('click', function (e) {
    if (e.target === this) closeNoShow();
});
</script>

@endsection