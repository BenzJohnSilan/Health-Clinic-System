@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-dashboard.css') }}">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')

{{-- ================================================================
     HERO — Dynamic Greeting Card
     ================================================================ --}}
<div class="hero-wrapper" id="heroWrapper">

    {{-- SKELETON --}}
    <div class="hero-section skeleton-hero" id="skeletonHero">
        <div class="dashboard-hero">
            <div class="hero-greeting">
                <div class="skeleton-line skeleton-greeting-title"></div>
                <div class="skeleton-line skeleton-greeting-subtitle"></div>
            </div>
        </div>
    </div>

    {{-- REAL CONTENT --}}
    <div class="hero-section real-hero" id="realHero">
        <div class="dashboard-hero">
            <div class="hero-greeting">
                <h2 class="greeting-text">{{ $greeting }}, Dr. {{ $doctor->last_name }}! 👋</h2>
                <p class="greeting-subtitle">Here's your clinic activity for today.</p>
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     SUMMARY CARDS — colored cards
     Pending card removed. Total Patients keeps the existing
     registered + walk-in combined total from the controller.
     ================================================================ --}}
<div class="stats-wrapper" id="statsWrapper">

    {{-- SKELETON --}}
    <div class="stats-section skeleton-stats" id="skeletonStats">
        @for ($i = 0; $i < 4; $i++)
        <div class="stat-card skeleton-card">
            <div class="stat-body">
                <div class="skeleton-line skeleton-label"></div>
                <div class="skeleton-line skeleton-value"></div>
                <div class="skeleton-line skeleton-badge"></div>
            </div>
            <div class="skeleton-icon"></div>
        </div>
        @endfor
    </div>

    {{-- REAL CONTENT --}}
    <div class="stats-section real-stats" id="realStats">

        <div class="stat-card" id="stat-total">
            <div class="stat-body">
                <p class="stat-label">Total Appointments</p>
                <h2 class="stat-value" data-target="{{ $totalAppointments }}">0</h2>
                <span class="stat-badge">All Time</span>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-check" aria-hidden="true"></i></div>
        </div>

        <div class="stat-card" id="stat-patients">
            <div class="stat-body">
                <p class="stat-label">Total Patients</p>
                <h2 class="stat-value" data-target="{{ $totalPatients }}">0</h2>
                <span class="stat-badge">Registered + Walk-in</span>
            </div>
            <div class="stat-icon"><i class="fas fa-procedures" aria-hidden="true"></i></div>
        </div>

        <div class="stat-card" id="stat-upcoming">
            <div class="stat-body">
                <p class="stat-label">Upcoming</p>
                <h2 class="stat-value" data-target="{{ $upcomingAppointments }}">0</h2>
                <span class="stat-badge">Scheduled</span>
            </div>
            <div class="stat-icon"><i class="fas fa-hourglass-half" aria-hidden="true"></i></div>
        </div>

        <div class="stat-card" id="stat-completed">
            <div class="stat-body">
                <p class="stat-label">Completed</p>
                <h2 class="stat-value" data-target="{{ $completedAppointments }}">0</h2>
                <span class="stat-badge">Done</span>
            </div>
            <div class="stat-icon"><i class="fas fa-check-circle" aria-hidden="true"></i></div>
        </div>

    </div>
</div>

{{-- ================================================================
     MAIN DASHBOARD GRID
     Left/large: Today's Patients Queue
     Right: Next Patient
     Both columns are stretched to the same height.
     ================================================================ --}}
<div class="dashboard-grid">

    {{-- ============================================================
         TODAY'S PATIENTS QUEUE (main/largest section)
         ============================================================ --}}
    <div class="grid-main">
        <div class="today-wrapper" id="todayWrapper">

            {{-- SKELETON --}}
            <div class="today-section skeleton-today" id="skeletonToday">
                <div class="appt-table-wrap">
                    <div class="table-header-row">
                        <div class="skeleton-line skeleton-th-title"></div>
                        <div class="skeleton-line skeleton-th-link"></div>
                    </div>
                    <div class="today-list">
                        @for ($i = 0; $i < 5; $i++)
                        <div class="today-row-skeleton">
                            <div class="skeleton-line skeleton-time"></div>
                            <div class="skeleton-today-details">
                                <div class="skeleton-line skeleton-name"></div>
                                <div class="skeleton-line skeleton-meta"></div>
                            </div>
                            <div class="skeleton-line skeleton-badge-sm"></div>
                        </div>
                        @endfor
                    </div>
                </div>
            </div>

            {{-- REAL CONTENT --}}
            <div class="today-section real-today" id="realToday">
                <div class="appt-table-wrap">
                    <div class="table-header-row">
                        <span><i class="fas fa-calendar-day" style="color:var(--primary); margin-right:8px;" aria-hidden="true"></i>Today's Patients Queue</span>
                        @php $todayCount = count($todayAppointments ?? []); @endphp
                        @if($todayCount > 0)
                            <span class="today-count-badge">{{ $todayCount }}</span>
                        @endif
                    </div>

                    <div class="today-list">
                        @forelse($todayAppointments ?? [] as $appointment)
                            @php
                                $isWalkin = !is_null($appointment->walkin_patient_id);
                                $p = $appointment->patient ?? $appointment->walkinPatient;
                            @endphp
                            <div class="today-row">
                                <div class="today-time">
                                    {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                                </div>
                                <div class="today-details">
                                    <p class="today-patient">
                                        {{ $p->first_name ?? '' }} {{ $p->last_name ?? '' }}
                                        <span class="patient-type-badge {{ $isWalkin ? 'type-walkin' : 'type-registered' }}">
                                            {{ $isWalkin ? 'Walk-in' : 'Registered' }}
                                        </span>
                                    </p>
                                    <p class="today-meta">
                                        {{ Str::limit($appointment->reason, 45, '...') ?: 'No reason provided' }}
                                    </p>
                                </div>
                                <span class="status-badge {{ Str::slug($appointment->status) }}">
                                    {{ $appointment->status }}
                                </span>
                            </div>
                        @empty
                            <div class="empty-state">
                                <i class="fas fa-calendar-times" aria-hidden="true"></i>
                                <p>No appointments today.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ============================================================
         RIGHT COLUMN: Next Patient
         ============================================================ --}}
    <div class="grid-side">

        <div class="side-wrapper" id="nextWrapper">

            {{-- SKELETON --}}
            <div class="side-section skeleton-next" id="skeletonNext">
                <div class="side-card">
                    <div class="skeleton-line skeleton-side-title"></div>
                    <div class="skeleton-line skeleton-side-name"></div>
                    <div class="skeleton-line skeleton-side-time"></div>
                    <div class="skeleton-line skeleton-side-desc"></div>
                    <div class="skeleton-line skeleton-side-desc-short"></div>
                    <div class="skeleton-line skeleton-side-badge"></div>
                    <div class="skeleton-line skeleton-side-btn"></div>
                </div>
            </div>

            {{-- REAL CONTENT --}}
            <div class="side-section real-next" id="realNext">
                <div class="side-card">
                    <h3 class="side-card-title">
                        <i class="fas fa-user-clock" aria-hidden="true"></i> Next Patient
                    </h3>

                    @if(isset($nextPatient))
                        @php
                            $nextIsWalkin = !is_null($nextPatient->walkin_patient_id);
                            $nextP = $nextPatient->patient ?? $nextPatient->walkinPatient;
                            $nextStatus = $nextPatient->status;
                            $nextCanConsult = in_array($nextStatus, ['Checked In', 'In Progress'], true);
                            $nextConsultLabel = $nextStatus === 'In Progress'
                                ? 'Continue Consultation'
                                : 'Start Consultation';
                        @endphp

                        <p class="next-patient-name">
                            {{ $nextP->first_name ?? '' }} {{ $nextP->last_name ?? '' }}
                            <span class="patient-type-badge {{ $nextIsWalkin ? 'type-walkin' : 'type-registered' }}">
                                {{ $nextIsWalkin ? 'Walk-in' : 'Registered' }}
                            </span>
                        </p>

                        <p class="next-patient-time">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            {{ \Carbon\Carbon::parse($nextPatient->appointment_time)->format('h:i A') }}
                        </p>

                        <p class="attention-desc">
                            {{ Str::limit($nextPatient->reason, 60, '...') ?: 'No reason provided' }}
                        </p>

                        <p class="next-patient-status">
                            <span class="status-badge {{ Str::slug($nextStatus) }}">{{ $nextStatus }}</span>
                        </p>

                        @if($nextCanConsult)
                            <a href="{{ route('doctor.appointments.show', $nextPatient->id) }}" class="side-btn primary">
                                <i class="fas fa-stethoscope" aria-hidden="true"></i>
                                {{ $nextConsultLabel }}
                            </a>
                        @else
                            <span class="side-btn disabled" aria-disabled="true">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                                Waiting for Check In
                            </span>
                        @endif
                    @else
                        <p class="attention-count ok"><i class="fas fa-check-circle" aria-hidden="true"></i> No upcoming patient.</p>
                        <p class="attention-desc">There are no more patients in today's queue.</p>
                    @endif
                </div>
            </div>

        </div>

    </div>
</div>

{{-- ================================================================
     CALENDAR — now with skeleton loading
     ================================================================ --}}
<div class="calendar-wrapper" id="calendarWrapper">

    {{-- SKELETON --}}
    <div class="calendar-section skeleton-calendar" id="skeletonCalendar">
        <div class="calendar-card">
            <div class="skeleton-calendar-toolbar">
                <div class="skeleton-line skeleton-cal-btns"></div>
                <div class="skeleton-line skeleton-cal-title"></div>
                <div class="skeleton-line skeleton-cal-btns"></div>
            </div>
            <div class="skeleton-calendar-grid">
                @for ($i = 0; $i < 35; $i++)
                    <div class="skeleton-cal-cell"></div>
                @endfor
            </div>
        </div>
    </div>

    {{-- REAL CONTENT --}}
    <div class="calendar-section real-calendar" id="realCalendar">
        <div class="calendar-card">
            <div id="calendar"></div>
        </div>
    </div>
</div>

{{-- ================================================================
     RECENT APPOINTMENTS — latest 5, fixed/compact height, skeleton
     ================================================================ --}}
<div class="recent-wrapper" id="recentWrapper">

    {{-- SKELETON --}}
    <div class="recent-section skeleton-recent" id="skeletonRecent">
        <div class="appt-table-wrap">
            <div class="table-header-row">
                <div class="skeleton-line skeleton-th-title"></div>
                <div class="skeleton-line skeleton-th-link"></div>
            </div>
            <div class="recent-list">
                @for ($i = 0; $i < 5; $i++)
                <div class="recent-row-skeleton">
                    <div class="skeleton-recent-details">
                        <div class="skeleton-line skeleton-name"></div>
                        <div class="skeleton-line skeleton-recent-meta"></div>
                    </div>
                    <div class="skeleton-line skeleton-badge-sm"></div>
                </div>
                @endfor
            </div>
        </div>
    </div>

    {{-- REAL CONTENT --}}
    <div class="recent-section real-recent" id="realRecent">
        <div class="appt-table-wrap">
            <div class="table-header-row">
                <span><i class="fas fa-clock-rotate-left" style="color:var(--primary); margin-right:8px;" aria-hidden="true"></i>Recent Appointments</span>
                <a href="{{ route('doctor.appointments.index') }}">View All <span aria-hidden="true">→</span></a>
            </div>

            <div class="recent-list">
                @forelse($recentAppointments ?? [] as $appointment)
                    @php
                        $recentIsWalkin = !is_null($appointment->walkin_patient_id);
                        $rp = $appointment->patient ?? $appointment->walkinPatient;
                    @endphp
                    <div class="recent-row">
                        <div class="recent-details">
                            <p class="recent-patient">
                                {{ $rp->first_name ?? '' }} {{ $rp->last_name ?? '' }}
                                <span class="patient-type-badge {{ $recentIsWalkin ? 'type-walkin' : 'type-registered' }}">
                                    {{ $recentIsWalkin ? 'Walk-in' : 'Registered' }}
                                </span>
                            </p>
                            <p class="recent-meta">
                                {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('M d, Y') }}
                                &middot; {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                            </p>
                        </div>
                        <span class="status-badge {{ Str::slug($appointment->status) }}">
                            {{ $appointment->status }}
                        </span>
                    </div>
                @empty
                    <div class="empty-state">
                        <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
                        <p>No recent appointments.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     SCRIPTS
     ================================================================ --}}
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ─── Crossfade reveal helper ────────────────────────────────────
    function reveal(skeletonId, realId) {
        const skeleton = document.getElementById(skeletonId);
        const real = document.getElementById(realId);
        if (!skeleton || !real) return;

        real.classList.add('fade-in');
        skeleton.classList.add('fade-out');

        setTimeout(() => skeleton.classList.add('removed'), 320);
    }

    const MIN_SKELETON_TIME = 450;
    const pageLoadedAt = Date.now();

    function revealAfterMinimumDelay(fn) {
        const elapsed = Date.now() - pageLoadedAt;
        const remaining = Math.max(0, MIN_SKELETON_TIME - elapsed);
        setTimeout(fn, remaining);
    }

    // Every dashboard section reveals through the same skeleton pattern.
    revealAfterMinimumDelay(() => reveal('skeletonHero', 'realHero'));
    revealAfterMinimumDelay(() => reveal('skeletonStats', 'realStats'));
    revealAfterMinimumDelay(() => reveal('skeletonToday', 'realToday'));
    revealAfterMinimumDelay(() => reveal('skeletonNext', 'realNext'));
    revealAfterMinimumDelay(() => reveal('skeletonRecent', 'realRecent'));

    // ─── Counter Animation (existing feature, preserved) ───────────
    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-target')) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        let current = 0;
        const timer = setInterval(() => {
            current += Math.max(1, Math.floor(target / (1200 / 16)));
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = current;
        }, 16);
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    document.querySelectorAll('.stat-value').forEach(el => observer.observe(el));

    // ─── FullCalendar ─────────────────────────────────────────────
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    let calendarRevealed = false;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: [
            @foreach($appointments as $appointment)
                @php
                    $start = \Carbon\Carbon::parse($appointment->appointment_date)
                        ->setTimeFromTimeString($appointment->appointment_time);

                    $end = $start->copy()->addMinutes(30);

                    $calendarPatient = $appointment->patient ?? $appointment->walkinPatient;

                    $calendarPatientName = $calendarPatient
                        ? trim(($calendarPatient->first_name ?? '') . ' ' . ($calendarPatient->last_name ?? ''))
                        : 'Unknown Patient';
                @endphp
                {
                    title: @json($calendarPatientName),
                    start: "{{ $start->format('Y-m-d\TH:i:s') }}",
                    end: "{{ $end->format('Y-m-d\TH:i:s') }}",
                    allDay: false
                },
            @endforeach
        ],
        displayEventTime: true,
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        eventContent: function (arg) {
            const startTime = arg.event.start.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            const endTime = arg.event.end.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            return {
                html: `<div style="line-height:1.3; padding:2px 0;">
                           <strong>${arg.event.title}</strong><br>
                           <small>${startTime} – ${endTime}</small>
                       </div>`
            };
        },
        eventDidMount: function (info) {
            if (info.event.end < new Date()) {
                info.el.classList.add('past-event');
            }
        },
        loading: function (isLoading) {
            if (!isLoading && !calendarRevealed) {
                calendarRevealed = true;
                revealAfterMinimumDelay(() => reveal('skeletonCalendar', 'realCalendar'));
            }
        }
    });

    calendar.render();

    // Fallback in case the `loading` callback never fires on this
    // FullCalendar build (all events are local, no async fetch).
    revealAfterMinimumDelay(() => {
        if (!calendarRevealed) {
            calendarRevealed = true;
            reveal('skeletonCalendar', 'realCalendar');
        }
    });
});
</script>

@endsection
