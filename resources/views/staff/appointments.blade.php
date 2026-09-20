@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-appointments.css') }}">
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

    @php
        // Whatever filters are active right now, minus 'status' and 'page' —
        // reused to build every status tab link and keep pagination links
        // pointing at the same filtered view.
        $activeFilters = collect(request()->query())->except(['status', 'page'])->all();
    @endphp

    <!-- ================= STATUS TABS ================= -->
    <div class="status-tabs">
        <a href="{{ route('staff.appointments.index', array_merge($activeFilters, ['status' => 'all'])) }}"
           class="status-tab {{ $activeStatus === 'all' ? 'active' : '' }}">
            All
            <span class="tab-count">{{ $statusCounts->sum() }}</span>
        </a>

        @foreach($statusList as $s)
            <a href="{{ route('staff.appointments.index', array_merge($activeFilters, ['status' => $s])) }}"
               class="status-tab {{ $activeStatus === $s ? 'active' : '' }}">
                {{ $s }}
                <span class="tab-count">{{ $statusCounts->get($s, 0) }}</span>
            </a>
        @endforeach
    </div>

    <!-- ================= SEARCH & FILTERS ================= -->
    <form method="GET" action="{{ route('staff.appointments.index') }}" class="filter-bar">
        <input type="hidden" name="status" value="{{ $activeStatus }}">

        <input
            type="text"
            name="search"
            class="filter-input"
            placeholder="Search patient or appointment ID..."
            value="{{ request('search') }}">

        <select name="doctor_id" class="filter-input">
            <option value="">All Doctors</option>
            @foreach($doctors as $doc)
                <option value="{{ $doc->id }}" {{ (string) request('doctor_id') === (string) $doc->id ? 'selected' : '' }}>
                    Dr. {{ $doc->first_name }} {{ $doc->last_name }}
                </option>
            @endforeach
        </select>

        <input
            type="date"
            name="date"
            class="filter-input"
            value="{{ request('date') }}">

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        @if(request('search') || request('doctor_id') || request('date'))
            <a href="{{ route('staff.appointments.index', ['status' => $activeStatus]) }}" class="btn-clear-filter">
                Clear
            </a>
        @endif
    </form>

    <!-- ================= APPOINTMENTS TABLE ================= -->
    <div class="table-container">
        <table class="appointments-table">

            <colgroup>
                <col class="col-id">
                <col class="col-patient">
                <col class="col-datetime">
                <col class="col-doctor">
                <col class="col-reason">
                <col class="col-status">
                <col class="col-action">
            </colgroup>

            <thead>
                <tr>
                    <th>Appointment ID</th>
                    <th>Patient</th>
                    <th>Date &amp; Time</th>
                    <th>Doctor</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                @forelse($appointments as $appointment)

                @php
                    $date = \Carbon\Carbon::parse($appointment->appointment_date);
                    $time = \Carbon\Carbon::parse($appointment->appointment_time);

                    // Same eligibility rules enforced server-side in
                    // StaffAppointmentController::reschedule()/noShow().
                    $isReschedulable = in_array($appointment->status, ['Approved', 'Rescheduled'], true);

                    $slotEnd = \Carbon\Carbon::parse(
                        $date->format('Y-m-d') . ' ' . $time->format('H:i:s')
                    )->addMinutes(30);
                    $isNoShowEligible = $isReschedulable && \Carbon\Carbon::now()->gte($slotEnd);

                    // Same eligibility rule enforced server-side in
                    // StaffAppointmentController::checkIn() /
                    // Appointment::canBeCheckedIn().
                    $isCheckInEligible = $appointment->canBeCheckedIn();
                @endphp

                <tr>

                    <!-- Appointment ID -->
                    <td>{{ $appointment->reference_no ?? '#'.$appointment->id }}</td>

                    <!-- Patient -->
                    <td>{{ $appointment->patientName() }}</td>

                    <!-- Schedule -->
                    <td>
                        {{ $date->format('F d, Y') }}
                        <span style="margin-left:8px; color:#555; font-size:13px;">
                            {{ $time->format('h:i A') }}
                        </span>
                    </td>

                    <!-- Doctor -->
                    <td>
                        Dr. {{ $appointment->doctor->first_name ?? '' }} {{ $appointment->doctor->last_name ?? '' }}
                    </td>

                    <!-- Reason -->
                    <td>
                        <span class="reason-preview">{{ $appointment->reason ?? '-' }}</span>
                    </td>

                    <!-- Status -->
                    <td>
                        <span class="status {{ str_replace(' ', '-', strtolower($appointment->status)) }}">
                            {{ $appointment->status }}
                        </span>
                    </td>

                    <!-- Action -->
                    <td>
                    <div class="action-cell">
                        @if($canViewDetails)
                            <a href="{{ route('staff.appointments.show', $appointment->id) }}" class="btn-view">
                                View
                            </a>
                        @endif

                        
                            <div class="action-menu">
                                <button type="button" class="action-menu-toggle" aria-haspopup="true" aria-expanded="false" aria-label="More actions">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>

                                <div class="action-menu-dropdown">

    {{-- Approve --}}
    <form action="{{ route('staff.appointments.approve', $appointment->id) }}" method="POST">
        @csrf
        <button
            type="submit"
            class="action-menu-item action-menu-approve {{ !($appointment->status === 'Pending' && $canApprove) ? 'action-menu-disabled' : '' }}"
            {{ !($appointment->status === 'Pending' && $canApprove) ? 'disabled' : '' }}
            onclick="return confirm('Approve this appointment?')">
            <i class="fa-solid fa-check"></i> Approve
        </button>
    </form>

    {{-- Reject --}}
    <button
        type="button"
        class="action-menu-item action-menu-reject openRejectModal {{ !($appointment->status === 'Pending' && $canReject) ? 'action-menu-disabled' : '' }}"
        {{ !($appointment->status === 'Pending' && $canReject) ? 'disabled' : '' }}
        data-id="{{ $appointment->id }}"
        data-name="{{ $appointment->patientName() }}"
        data-email="{{ $appointment->patientEmail() ?? 'N/A' }}">
        <i class="fa-solid fa-xmark"></i> Reject
    </button>

    {{-- Cancel --}}
    <form action="{{ route('staff.appointments.cancel', $appointment->id) }}" method="POST">
        @csrf
        <button
            type="submit"
            class="action-menu-item action-menu-cancel {{ !($appointment->status === 'Approved' && $canCancel) ? 'action-menu-disabled' : '' }}"
            {{ !($appointment->status === 'Approved' && $canCancel) ? 'disabled' : '' }}
            onclick="return confirm('Cancel this appointment?')">
            <i class="fa-solid fa-ban"></i> Cancel
        </button>
    </form>

    {{-- Check In --}}
    <form action="{{ route('staff.appointments.checkIn', $appointment->id) }}" method="POST">
        @csrf
        <button
            type="submit"
            class="action-menu-item action-menu-checkin {{ !($isCheckInEligible && $canCheckIn) ? 'action-menu-disabled' : '' }}"
            {{ !($isCheckInEligible && $canCheckIn) ? 'disabled' : '' }}
            onclick="return confirm('Check in this patient? This confirms they have arrived at the clinic.')">
            <i class="fa-solid fa-clipboard-check"></i> Check In
        </button>
    </form>

    {{-- Reschedule --}}
    <button
        type="button"
        class="action-menu-item action-menu-reschedule openRescheduleModal {{ !($isReschedulable && $canReschedule) ? 'action-menu-disabled' : '' }}"
        {{ !($isReschedulable && $canReschedule) ? 'disabled' : '' }}
        data-id="{{ $appointment->id }}"
        data-date="{{ $date->format('Y-m-d') }}"
        data-time="{{ $time->format('H:i') }}">
        <i class="fa-solid fa-calendar-days"></i> Reschedule
    </button>

    {{-- No Show --}}
    <button
        type="button"
        class="action-menu-item action-menu-no-show openNoShowModal {{ !($isNoShowEligible && $canMarkNoShow) ? 'action-menu-disabled' : '' }}"
        {{ !($isNoShowEligible && $canMarkNoShow) ? 'disabled' : '' }}
        data-id="{{ $appointment->id }}"
        title="{{ !$isNoShowEligible ? 'Available after the scheduled time ends' : '' }}">
        <i class="fa-solid fa-triangle-exclamation"></i> No Show
    </button>

</div>
                            </div>
                        
                    </div>
                    </td>

                </tr>

                @empty

                <tr>
                    <td colspan="7"
                        style="text-align:center; font-style:italic; color:#9ca3af; padding: 32px 0;">
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

<!-- ================= REJECT MODAL ================= -->
<div id="rejectModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" id="closeModal" aria-label="Close">&times;</button>
        <h3 class="modal-title">Reject Appointment</h3>

        <div class="modal-patient-info">
            <div class="info-row">
                <span class="info-label">Patient</span>
                <span class="info-value" id="modalName">—</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value" id="modalEmail">—</span>
            </div>
        </div>

        <form id="rejectForm" method="POST" data-url="{{ route('staff.appointments.reject', ':id') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="rejectReason">Reason for Rejection</label>
                <textarea
                    class="form-input"
                    name="reason"
                    id="rejectReason"
                    rows="3"
                    placeholder="Enter reason..."
                    required></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-back" id="cancelRejectBtn">Cancel</button>
                <button type="submit" class="btn-danger">Confirm Reject</button>
            </div>
        </form>

    </div>
</div>

<!-- ================= RESCHEDULE MODAL ================= -->
<div id="rescheduleModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" id="closeRescheduleModal" aria-label="Close">&times;</button>
        <h3 class="modal-title">Reschedule Appointment</h3>

        <form id="rescheduleForm" method="POST" data-url="{{ route('staff.appointments.reschedule', ':id') }}">
            @csrf

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="rescheduleDate">Appointment Date</label>
                    <input
                        type="date"
                        class="form-input"
                        name="appointment_date"
                        id="rescheduleDate"
                        min="{{ date('Y-m-d') }}"
                        required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="rescheduleTime">Appointment Time</label>
                    <input
                        type="time"
                        class="form-input"
                        name="appointment_time"
                        id="rescheduleTime"
                        min="08:00"
                        max="17:00"
                        required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="rescheduleReason">Reason for Rescheduling</label>
                <textarea
                    class="form-input"
                    name="reschedule_reason"
                    id="rescheduleReason"
                    rows="3"
                    placeholder="Enter reason..."
                    required></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-back" id="cancelRescheduleBtn">Cancel</button>
                <button type="submit" class="btn-reschedule">Confirm Reschedule</button>
            </div>
        </form>

    </div>
</div>

<!-- ================= NO SHOW MODAL ================= -->
<div id="noShowModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">

        <button class="modal-close-btn" id="closeNoShowModal" aria-label="Close">&times;</button>
        <h3 class="modal-title">Mark as No Show</h3>

        <p style="font-size:14px; color:#374151; margin:0 0 20px;">
            The scheduled appointment time has ended. Please confirm that the patient did not arrive.
            The appointment will be marked as <strong>No Show</strong>.
        </p>

        <form id="noShowForm" method="POST" data-url="{{ route('staff.appointments.noShow', ':id') }}">
            @csrf

            <div class="modal-actions">
                <button type="button" class="btn-back" id="cancelNoShowBtn">Cancel</button>
                <button type="submit" class="btn-danger">Confirm No Show</button>
            </div>
        </form>

    </div>
</div>

<!-- ================= FONT AWESOME ================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- ================= JAVASCRIPT ================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const rejectModal   = document.getElementById('rejectModal');
    const closeModalBtn = document.getElementById('closeModal');
    const cancelBtn     = document.getElementById('cancelRejectBtn');

    document.querySelectorAll('.openRejectModal').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('modalName').textContent  = this.dataset.name;
            document.getElementById('modalEmail').textContent = this.dataset.email;

            const url = document.getElementById('rejectForm').dataset.url.replace(':id', this.dataset.id);
            document.getElementById('rejectForm').action = url;

            rejectModal.style.display = 'flex';
            // Force a reflow so the opacity/transform transition below actually
            // animates instead of jumping straight to the visible state.
            void rejectModal.offsetWidth;
            rejectModal.classList.add('modal-visible');
        });
    });

    function closeModal() {
        rejectModal.classList.remove('modal-visible');
        rejectModal.style.display = 'none';
    }

    closeModalBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    rejectModal.addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    // ================= RESCHEDULE MODAL =================
    const rescheduleModal      = document.getElementById('rescheduleModal');
    const closeRescheduleBtn   = document.getElementById('closeRescheduleModal');
    const cancelRescheduleBtn  = document.getElementById('cancelRescheduleBtn');
    const rescheduleForm       = document.getElementById('rescheduleForm');

    document.querySelectorAll('.openRescheduleModal').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('rescheduleDate').value = this.dataset.date;
            document.getElementById('rescheduleTime').value = this.dataset.time;
            document.getElementById('rescheduleReason').value = '';

            rescheduleForm.action = rescheduleForm.dataset.url.replace(':id', this.dataset.id);

            rescheduleModal.style.display = 'flex';
            void rescheduleModal.offsetWidth;
            rescheduleModal.classList.add('modal-visible');
        });
    });

    function closeRescheduleModal() {
        rescheduleModal.classList.remove('modal-visible');
        rescheduleModal.style.display = 'none';
    }

    closeRescheduleBtn.addEventListener('click', closeRescheduleModal);
    cancelRescheduleBtn.addEventListener('click', closeRescheduleModal);

    rescheduleModal.addEventListener('click', function (e) {
        if (e.target === this) closeRescheduleModal();
    });

    // ================= NO SHOW MODAL =================
    const noShowModal     = document.getElementById('noShowModal');
    const closeNoShowBtn  = document.getElementById('closeNoShowModal');
    const cancelNoShowBtn = document.getElementById('cancelNoShowBtn');
    const noShowForm      = document.getElementById('noShowForm');

    document.querySelectorAll('.openNoShowModal').forEach(function (button) {
        button.addEventListener('click', function () {
            noShowForm.action = noShowForm.dataset.url.replace(':id', this.dataset.id);

            noShowModal.style.display = 'flex';
            void noShowModal.offsetWidth;
            noShowModal.classList.add('modal-visible');
        });
    });

    function closeNoShowModal() {
        noShowModal.classList.remove('modal-visible');
        noShowModal.style.display = 'none';
    }

    closeNoShowBtn.addEventListener('click', closeNoShowModal);
    cancelNoShowBtn.addEventListener('click', closeNoShowModal);

    noShowModal.addEventListener('click', function (e) {
        if (e.target === this) closeNoShowModal();
    });

    // ================= ACTION MENU (three-dot dropdown) =================
    // While open, each dropdown is switched to position:fixed and placed via
    // JS using the toggle button's screen position — this keeps it fully
    // visible even though the table wrapper uses overflow-x:auto for
    // horizontal scrolling (fixed elements aren't clipped by that).
    let openMenu = null;

    function closeActionMenu() {
        if (!openMenu) return;
        openMenu.dropdown.style.display = 'none';
        openMenu.dropdown.style.position = '';
        openMenu.dropdown.style.top = '';
        openMenu.dropdown.style.left = '';
        openMenu.toggle.setAttribute('aria-expanded', 'false');
        openMenu = null;
    }

    document.querySelectorAll('.action-menu-toggle').forEach(function (toggle) {
        const wrapper  = toggle.closest('.action-menu');
        const dropdown = wrapper.querySelector('.action-menu-dropdown');

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();

            const alreadyOpenForThis = openMenu && openMenu.dropdown === dropdown;
            closeActionMenu();
            if (alreadyOpenForThis) return;

            const rect = toggle.getBoundingClientRect();
            const menuWidth = 160;

            dropdown.style.display = 'block';
            dropdown.style.position = 'fixed';
            dropdown.style.minWidth = menuWidth + 'px';

            // Keep the menu on-screen horizontally.
            let left = rect.right - menuWidth;
            if (left < 8) left = 8;
            if (left + menuWidth > window.innerWidth - 8) {
                left = window.innerWidth - menuWidth - 8;
            }

            // Flip above the button if there isn't room below.
            const estimatedMenuHeight = dropdown.offsetHeight || 90;
            let top = rect.bottom + 6;
            if (top + estimatedMenuHeight > window.innerHeight - 8) {
                top = rect.top - estimatedMenuHeight - 6;
            }

            dropdown.style.top  = top + 'px';
            dropdown.style.left = left + 'px';

            toggle.setAttribute('aria-expanded', 'true');
            openMenu = { dropdown: dropdown, toggle: toggle };
        });
    });

    document.addEventListener('click', closeActionMenu);
    window.addEventListener('scroll', closeActionMenu, true);
    window.addEventListener('resize', closeActionMenu);

});
</script>

@endsection