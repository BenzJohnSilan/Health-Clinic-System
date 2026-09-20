@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-schedule.css') }}">
@endsection

@section('content')
<div class="container doctor-schedule-page">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">My Schedule</h1>
        <p class="page-subtitle">Manage your weekly availability and temporary schedule changes.</p>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- ================= SECTION 1: WEEKLY AVAILABILITY ================= -->
    <div class="schedule-card">
        <div class="schedule-card-header">
            <div>
                <div class="schedule-card-title">Weekly Availability</div>
                <div class="schedule-card-desc">Your regular recurring schedule for each day of the week.</div>
            </div>
        </div>

        <div class="table-container">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th>Status</th>
                        <th>Available Hours</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($days as $i => $day)
                        @php
                            $weekly     = $weeklySchedules[$i];
                            $isAvail    = $weekly && $weekly->status === 'Available';
                            $slots      = $weekly ? $weekly->slots : collect();
                            $slotsJson  = $slots->map(fn($s) => [
                                'start_time' => substr($s->start_time, 0, 5),
                                'end_time'   => substr($s->end_time, 0, 5),
                            ])->values();
                        @endphp
                        <tr>
                            <td>{{ $day }}</td>
                            <td>
                                @if($isAvail)
                                    <span class="badge badge-available">Available</span>
                                @else
                                    <span class="badge badge-unavailable">Unavailable</span>
                                @endif
                            </td>
                            <td>
                                @if($isAvail && $slots->isNotEmpty())
                                    <div class="hours-list">
                                        @foreach($slots as $slot)
                                            <span>
                                                {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }}
                                                &ndash;
                                                {{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="hours-empty">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button"
                                            class="btn-edit"
                                            onclick='openWeeklyModal(@json($day), @json($isAvail ? "Available" : "Unavailable"), @json($slotsJson))'>
                                        <i class='bx bx-pencil'></i> Edit
                                    </button>

                                    <button type="button"
                                            class="btn-delete"
                                            title="Delete schedule"
                                            onclick="openDeleteModal('{{ route('doctor.schedule.weekly.destroy', $day) }}', 'Delete {{ $day }}\'s schedule?', 'This will remove the configured schedule for {{ $day }} and make the day unavailable for future bookings. Existing appointments will not be affected.')">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================= SECTION 2: SCHEDULE EXCEPTIONS ================= -->
    <div class="schedule-card">
        <div class="schedule-card-header">
            <div>
                <div class="schedule-card-title">Schedule Exceptions</div>
                <div class="schedule-card-desc">Temporary changes to your regular schedule, such as leave, holidays, or custom hours.</div>
            </div>
            <button type="button" class="btn-add-exception" onclick="openExceptionModal()">
                <i class='bx bx-plus'></i> Add Exception
            </button>
        </div>

        <div class="table-container">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Available Hours</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exceptions as $exception)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($exception->exception_date)->format('M d, Y') }}</td>
                            <td>
                                @if($exception->type === 'Unavailable')
                                    <span class="badge badge-unavailable">Unavailable</span>
                                @else
                                    <span class="badge badge-custom">Custom Hours</span>
                                @endif
                            </td>
                            <td>
                                @if($exception->type === 'Custom Hours' && $exception->start_time && $exception->end_time)
                                    {{ \Carbon\Carbon::parse($exception->start_time)->format('g:i A') }}
                                    &ndash;
                                    {{ \Carbon\Carbon::parse($exception->end_time)->format('g:i A') }}
                                @else
                                    <span class="hours-empty">&mdash;</span>
                                @endif
                            </td>
                            <td class="reason-cell">{{ $exception->reason ?: '—' }}</td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button"
                                            class="btn-edit"
                                            onclick='openExceptionModal(@json($exception->id), @json(\Carbon\Carbon::parse($exception->exception_date)->format("Y-m-d")), @json($exception->type), @json($exception->start_time ? substr($exception->start_time, 0, 5) : ""), @json($exception->end_time ? substr($exception->end_time, 0, 5) : ""), @json($exception->reason ?? ""))'>
                                        <i class='bx bx-pencil'></i> Edit
                                    </button>

                                    <button type="button"
                                            class="btn-delete"
                                            title="Delete exception"
                                            onclick="openDeleteModal('{{ route('doctor.schedule.exceptions.destroy', $exception->id) }}', 'Delete this exception?', 'This schedule exception for {{ \Carbon\Carbon::parse($exception->exception_date)->format('M d, Y') }} will be permanently removed. Existing appointments will not be affected.')">
                                        <i class='bx bx-trash'></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td colspan="5">No schedule exceptions added yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ================= EDIT SCHEDULE MODAL ================= -->
<div class="modal-overlay" id="weeklyModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Schedule</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('weeklyModal')">&times;</button>
        </div>

        <form method="POST" action="{{ route('doctor.schedule.weekly.update') }}">
            @csrf
            @method('PUT')

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Day</label>
                    <div class="schedule-day-display" id="weekly_day">Monday</div>
                    <input type="hidden" name="day_of_week" id="weekly_day_hidden">
                </div>

                <div class="form-group">
                    <label class="form-label" for="weekly_status">Status</label>
                    <select class="form-input" name="status" id="weekly_status" onchange="toggleSlotsVisibility()" required>
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                </div>

                <div class="form-group" id="weeklySlotsGroup">
                    <label class="form-label">Time Slots</label>
                    <div class="slots-wrapper" id="slotsWrapper"></div>
                    <button type="button" class="btn-add-slot" onclick="addSlotRow()">
                        <i class='bx bx-plus'></i> Add Time Slot
                    </button>
                    <div class="slots-hint">Example: 8:00 AM to 12:00 PM, 1:00 PM to 5:00 PM</div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeModal('weeklyModal')">Cancel</button>
                <button type="submit" class="btn-save-modal">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= ADD / EDIT EXCEPTION MODAL ================= -->
<div class="modal-overlay" id="exceptionModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="exceptionModalTitle">Add Exception</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('exceptionModal')">&times;</button>
        </div>

        <form method="POST" id="exceptionForm" action="{{ route('doctor.schedule.exceptions.store') }}">
            @csrf
            <input type="hidden" name="_method" id="exceptionMethod" value="POST">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="exception_date">Date</label>
                    <input class="form-input" type="date" name="exception_date" id="exception_date" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="exception_type">Type</label>
                    <select class="form-input" name="type" id="exception_type" onchange="toggleExceptionHours()" required>
                        <option value="Unavailable">Unavailable</option>
                        <option value="Custom Hours">Custom Hours</option>
                    </select>
                </div>

                <div class="form-row" id="exceptionHoursGroup">
                    <div class="form-group">
                        <label class="form-label" for="exception_start_time">Start Time</label>
                        <input class="form-input" type="time" name="start_time" id="exception_start_time">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="exception_end_time">End Time</label>
                        <input class="form-input" type="time" name="end_time" id="exception_end_time">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="exception_reason">Reason</label>
                    <input class="form-input" type="text" name="reason" id="exception_reason" maxlength="500" placeholder="e.g. Leave, Holiday">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeModal('exceptionModal')">Cancel</button>
                <button type="submit" class="btn-save-modal">Save Exception</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= DELETE CONFIRMATION MODAL ================= -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box confirm-box">
        <div class="confirm-icon"><i class='bx bx-trash'></i></div>
        <h3 id="deleteModalTitle">Are you sure?</h3>
        <p id="deleteModalMessage">This action cannot be undone.</p>
        <form method="POST" id="deleteForm">
            @csrf
            @method('DELETE')
            <div class="confirm-buttons">
                <button type="button" class="btn-cancel-modal" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn-confirm-delete">Delete</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const WEEKLY_UPDATE_URL = @json(route('doctor.schedule.weekly.update'));
    const EXCEPTION_STORE_URL = @json(route('doctor.schedule.exceptions.store'));

    /* ---------- Generic modal helpers ---------- */
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('active');
        });
    });

    /* ================= WEEKLY SCHEDULE MODAL ================= */
    let slotCounter = 0;

    function addSlotRow(startTime = '', endTime = '') {
        slotCounter++;
        const wrapper = document.getElementById('slotsWrapper');
        const row = document.createElement('div');
        row.className = 'slot-row';
        row.id = 'slotRow' + slotCounter;
        row.innerHTML = `
            <input class="form-input" type="time" name="slots[${slotCounter}][start_time]" value="${startTime}" required>
            <input class="form-input" type="time" name="slots[${slotCounter}][end_time]" value="${endTime}" required>
            <button type="button" class="btn-remove-slot" onclick="document.getElementById('slotRow${slotCounter}').remove()">
                <i class='bx bx-x'></i>
            </button>
        `;
        wrapper.appendChild(row);
    }

    function toggleSlotsVisibility() {
        const status = document.getElementById('weekly_status').value;
        document.getElementById('weeklySlotsGroup').style.display = status === 'Available' ? 'block' : 'none';
    }

    function openWeeklyModal(day, status, slots) {
        document.getElementById('weekly_day').textContent = day;
        document.getElementById('weekly_day_hidden').value = day;
        document.getElementById('weekly_status').value = status;

        document.getElementById('slotsWrapper').innerHTML = '';
        slotCounter = 0;

        if (slots && slots.length > 0) {
            slots.forEach(s => addSlotRow(s.start_time, s.end_time));
        } else {
            addSlotRow();
        }

        toggleSlotsVisibility();
        document.getElementById('weeklyModal').classList.add('active');
    }

    /* ================= EXCEPTION MODAL ================= */
    function toggleExceptionHours() {
        const type = document.getElementById('exception_type').value;
        const group = document.getElementById('exceptionHoursGroup');
        const start = document.getElementById('exception_start_time');
        const end = document.getElementById('exception_end_time');

        if (type === 'Custom Hours') {
            group.style.display = 'flex';
            start.required = true;
            end.required = true;
        } else {
            group.style.display = 'none';
            start.required = false;
            end.required = false;
            start.value = '';
            end.value = '';
        }
    }

    function openExceptionModal(id = null, date = '', type = 'Unavailable', startTime = '', endTime = '', reason = '') {
        const form = document.getElementById('exceptionForm');
        const title = document.getElementById('exceptionModalTitle');

        document.getElementById('exception_date').value = date;
        document.getElementById('exception_type').value = type;
        document.getElementById('exception_start_time').value = startTime;
        document.getElementById('exception_end_time').value = endTime;
        document.getElementById('exception_reason').value = reason;

        if (id) {
            title.textContent = 'Edit Exception';
            form.action = EXCEPTION_STORE_URL + '/' + id;
            document.getElementById('exceptionMethod').value = 'PUT';
        } else {
            title.textContent = 'Add Exception';
            form.action = EXCEPTION_STORE_URL;
            document.getElementById('exceptionMethod').value = 'POST';
        }

        toggleExceptionHours();
        document.getElementById('exceptionModal').classList.add('active');
    }

    /* ================= DELETE CONFIRMATION MODAL ================= */
    function openDeleteModal(actionUrl, title, message) {
        document.getElementById('deleteModalTitle').textContent = title;
        document.getElementById('deleteModalMessage').textContent = message;
        document.getElementById('deleteForm').action = actionUrl;
        document.getElementById('deleteModal').classList.add('active');
    }

    /* Reopen the relevant modal automatically if validation failed */
    @if($errors->any() && old('day_of_week'))
        openWeeklyModal(
            @json(old('day_of_week')),
            @json(old('status')),
            @json(collect(old('slots', []))->values())
        );
    @elseif($errors->any() && old('exception_date'))
        openExceptionModal(
            null,
            @json(old('exception_date')),
            @json(old('type', 'Unavailable')),
            @json(old('start_time', '')),
            @json(old('end_time', '')),
            @json(old('reason', ''))
        );
    @endif
</script>
@endsection
