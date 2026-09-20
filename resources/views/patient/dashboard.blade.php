@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-dashboard.css') }}">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
@endsection

@section('content')

<!-- ===== HERO SECTION (Greeting + Profile Completion in ONE container) —
     skeleton + real stacked in same grid cell ===== -->
<div class="hero-wrapper" id="heroWrapper">

    <!-- SKELETON -->
    <div class="hero-section skeleton-hero" id="skeletonHero">
        <div class="dashboard-hero">
            <div class="hero-greeting">
                <div class="skeleton-line skeleton-greeting-title"></div>
                <div class="skeleton-line skeleton-greeting-subtitle"></div>
            </div>

            <div class="hero-divider"></div>

            <div class="hero-profile">
                <div class="pc-header">
                    <div class="skeleton-line skeleton-pc-title"></div>
                    <div class="skeleton-line skeleton-pc-percent"></div>
                </div>
                <div class="skeleton-line skeleton-pc-message"></div>
                <div class="pc-progress-track"><div class="skeleton-line skeleton-pc-bar"></div></div>
                <div class="pc-footer">
                    <div class="skeleton-line skeleton-pc-submessage"></div>
                    <div class="skeleton-line skeleton-pc-button"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- REAL CONTENT -->
    <div class="hero-section real-hero" id="realHero">
        <div class="dashboard-hero">

            <div class="hero-greeting">
                <h2 class="greeting-text">{{ $greeting }}, {{ $patient->first_name }}! 👋</h2>
                <p class="greeting-subtitle">Here's an overview of your clinic information and appointments.</p>
            </div>

            <div class="hero-divider"></div>

            <div class="hero-profile {{ $profileComplete ? 'is-complete' : '' }}" id="profileCompletionCard">
                <div class="pc-header">
                    <h3 class="pc-title">
                        {{ $profileComplete ? 'Profile Complete' : 'Profile Completion' }}
                        @if($profileComplete)<span class="pc-check">✓</span>@endif
                    </h3>
                    <span class="pc-percent">{{ $profileComplete ? '✓ 100%' : $profileCompletionPercent . '%' }}</span>
                </div>

                <p class="pc-message">
                    {{ $profileComplete
                        ? 'Your profile is complete. You can now book an appointment.'
                        : 'Complete your profile to book an appointment.' }}
                </p>

                <div class="pc-progress-track">
                    <div class="pc-progress-fill" style="width: {{ $profileCompletionPercent }}%;"></div>
                </div>

                <div class="pc-footer">
                    <span class="pc-submessage">
                        {{ $completedRequiredFields }} / {{ $totalRequiredFields }} Required Information Completed
                    </span>

                    @if($profileComplete)
                        <a href="{{ route('patient.appointments.index') }}?book=1" class="pc-btn">
                            <i class="fa-solid fa-calendar-plus"></i>
                            Book Now
                        </a>
                    @else
                        <a href="{{ route('patient.settings') }}" class="pc-btn">
                            Complete Profile
                        </a>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ===== STATISTICS SECTION (skeleton + real stacked in same grid cell) ===== -->
<div class="stats-wrapper" id="statsWrapper">

    <!-- SKELETON (shown first) -->
    <div class="stats-section skeleton-stats" id="skeletonStats">
        @for ($i = 0; $i < 5; $i++)
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

    <!-- REAL CONTENT (occupies the exact same grid cell as skeleton) -->
    <div class="stats-section real-stats" id="realStats">

        {{-- Total Appointments --}}
        <div class="stat-card" id="stat-total">
            <div class="stat-body">
                <p class="stat-label">Total Appointments</p>
                <h2 class="stat-value" data-target="{{ $totalAppointments ?? 0 }}">0</h2>
                <span class="stat-badge">All Time</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>

        {{-- Upcoming --}}
        <div class="stat-card" id="stat-upcoming">
            <div class="stat-body">
                <p class="stat-label">Upcoming</p>
                <h2 class="stat-value" data-target="{{ $upcomingCount }}">0</h2>
                <span class="stat-badge">Scheduled</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>

        {{-- Completed --}}
        <div class="stat-card" id="stat-completed">
            <div class="stat-body">
                <p class="stat-label">Completed</p>
                <h2 class="stat-value" data-target="{{ $completedAppointments }}">0</h2>
                <span class="stat-badge">Done</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>

        {{-- Cancelled --}}
        <div class="stat-card" id="stat-cancelled">
            <div class="stat-body">
                <p class="stat-label">Cancelled</p>
                <h2 class="stat-value" data-target="{{ $cancelledAppointments }}">0</h2>
                <span class="stat-badge">Missed</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
        </div>

        {{-- This Month --}}
        <div class="stat-card" id="stat-this-month">
            <div class="stat-body">
                <p class="stat-label">This Month</p>
                <h2 class="stat-value" data-target="{{ $thisMonthCount ?? 0 }}">0</h2>
                <span class="stat-badge">{{ now()->format('M Y') }}</span>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>

    </div>
</div>

<!-- ===== CALENDAR SECTION (skeleton + real stacked in same grid cell) ===== -->
<div class="calendar-section">
    <div class="calendar-card">

        <div class="calendar-stack" id="calendarStack">

            <!-- SKELETON CALENDAR -->
            <div class="skeleton-calendar" id="skeletonCalendar">
                <div class="skeleton-cal-header">
                    <div class="skeleton-line skeleton-cal-btns"></div>
                    <div class="skeleton-line skeleton-cal-title"></div>
                    <div class="skeleton-line skeleton-cal-btns"></div>
                </div>
                <div class="skeleton-cal-grid">
                    @for ($i = 0; $i < 35; $i++)
                    <div class="skeleton-cal-cell"></div>
                    @endfor
                </div>
            </div>

            <!-- REAL CALENDAR -->
            <div id="calendar"></div>

        </div>

    </div>
</div>

<!-- ================================================================
     SCRIPTS
     ================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Holds the live FullCalendar instance so the resize/observer
    // logic below can call updateSize() on it whenever the container
    // (e.g. after the sidebar is opened/closed) changes width.
    let calendarInstance = null;

    // ─── Counter Animation ────────────────────────────────────────
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

    // ─── Reveal stat cards (crossfade only — no position/layout change) ───
    function revealStats() {
        const skeleton = document.getElementById('skeletonStats');
        const real = document.getElementById('realStats');
        if (!skeleton || !real) return;

        real.classList.add('fade-in');
        skeleton.classList.add('fade-out');

        // After the fade finishes, drop the skeleton out of the grid
        // stack entirely so it can no longer influence the wrapper's
        // auto-computed height.
        setTimeout(() => skeleton.classList.add('removed'), 320);

        document.querySelectorAll('.real-stats .stat-value').forEach(el => observer.observe(el));
    }

    // ─── Reveal calendar (crossfade only) ──────────────────────────
    function revealCalendar() {
        const calendarEl = document.getElementById('calendar');
        const skeletonCal = document.getElementById('skeletonCalendar');
        if (!calendarEl || !skeletonCal) return;

        calendarEl.classList.add('fade-in');
        skeletonCal.classList.add('fade-out');

        // Remove the skeleton from layout once the fade completes.
        // Without this, the skeleton (fixed at 5 rows) keeps holding
        // the grid cell open even after it's invisible, which is what
        // was leaving extra blank space under months that render
        // fewer real rows (or mismatched space for 6-row months).
        setTimeout(() => {
            skeletonCal.classList.add('removed');
            if (calendarInstance) calendarInstance.updateSize();
        }, 320);
    }

    // ─── Reveal hero (greeting + profile completion) — crossfade only ───
    function revealHero() {
        const skeleton = document.getElementById('skeletonHero');
        const real = document.getElementById('realHero');
        if (!skeleton || !real) return;

        real.classList.add('fade-in');
        skeleton.classList.add('fade-out');

        setTimeout(() => skeleton.classList.add('removed'), 320);
    }

    // ─── Minimum skeleton display time (para pareho ng bilis stats & calendar) ───
    const MIN_SKELETON_TIME = 450; // ms — parehong ginagamit ng cards at calendar
    const pageLoadedAt = Date.now();

    function revealAfterMinimumDelay(revealFn) {
        const elapsed = Date.now() - pageLoadedAt;
        const remaining = Math.max(0, MIN_SKELETON_TIME - elapsed);
        setTimeout(revealFn, remaining);
    }

    // ─── FullCalendar ─────────────────────────────────────────────
    const calendarEl = document.getElementById('calendar');

    if (calendarEl) {
        let calendarReady = false;

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: [
                @foreach($upcomingAppointments as $appointment)
                    @php
                        $start = \Carbon\Carbon::parse($appointment->appointment_date)
                                    ->setTimeFromTimeString($appointment->appointment_time);
                        $end   = $start->copy()->addMinutes(30);
                    @endphp
                {
                    title : "Dr. {{ $appointment->doctor->first_name ?? '' }} {{ $appointment->doctor->last_name ?? '' }}",
                    start : "{{ $start->format('Y-m-d\TH:i:s') }}",
                    end   : "{{ $end->format('Y-m-d\TH:i:s') }}",
                    allDay: false
                },
                @endforeach
            ],
            displayEventTime: true,
            eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: 'short' },
            eventContent: function (arg) {
                const startTime = arg.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const endTime = arg.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                return {
                    html: `<div class="calendar-event" title="${arg.event.title}">
                               <strong class="calendar-event-title">${arg.event.title}</strong>
                               <small class="calendar-event-time">${startTime} – ${endTime}</small>
                           </div>`
                };
            },
            eventDidMount: function (info) {
                if (info.event.end < new Date()) {
                    info.el.classList.add('past-event');
                }
            },
            viewDidMount: function () {
                if (calendarReady) return; // avoid re-triggering on view switch
                calendarReady = true;
                revealAfterMinimumDelay(revealCalendar);
            }
        });

        calendar.render();
        calendarInstance = calendar;

        // ─── Keep the calendar's own internal sizing in sync with its
        // container. FullCalendar only recalculates its layout on a
        // *window* resize by default — it has no idea when the sidebar
        // is opened/closed, since that only changes the width of the
        // container around it (the window itself doesn't resize).
        // A ResizeObserver watches the actual container box and asks
        // FullCalendar to re-measure whenever it changes, which is
        // what keeps the calendar from "breaking" (stuck at its old
        // width, cut off, or leaving blank space) after the sidebar
        // toggles.
        const calendarCard = calendarEl.closest('.calendar-card');
        if (calendarCard && 'ResizeObserver' in window) {
            let resizeTimeout;
            const ro = new ResizeObserver(() => {
                clearTimeout(resizeTimeout);
                // Debounce slightly so we don't hammer updateSize()
                // on every intermediate frame of a CSS width transition.
                resizeTimeout = setTimeout(() => {
                    if (calendarInstance) calendarInstance.updateSize();
                }, 100);
            });
            ro.observe(calendarCard);
        }

        // Fallback for browsers without ResizeObserver, and as a
        // general safety net for plain window resizes too.
        window.addEventListener('resize', function () {
            if (calendarInstance) calendarInstance.updateSize();
        });
    }

    // Stats: same minimum delay logic
    revealAfterMinimumDelay(revealStats);

    // Intro (greeting + profile completion): same minimum delay logic
    revealAfterMinimumDelay(revealHero);
});
</script>

@endsection
