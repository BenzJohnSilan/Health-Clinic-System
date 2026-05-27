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

<!-- ===== PAGE HEADER ===== -->
<div class="page-header">
    <h1>Dashboard</h1>
</div>

<!-- ===== STATISTICS SECTION ===== -->
<div class="stats-section">

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

<!-- ===== CALENDAR SECTION ===== -->
<div class="calendar-section">
    <div class="calendar-card">
        <div id="calendar"></div>
    </div>
</div>

<!-- ================================================================
     SCRIPTS
     ================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

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

    document.querySelectorAll('.stat-value').forEach(el => observer.observe(el));

    // ─── FullCalendar ─────────────────────────────────────────────
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

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
            return {
                html: `<div style="line-height:1.3; padding:2px 0;">
                           <strong>${arg.event.title}</strong><br>
                           <small>
                               ${arg.event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                               –
                               ${arg.event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                           </small>
                       </div>`
            };
        },
        eventDidMount: function (info) {
            if (info.event.end < new Date()) {
                info.el.classList.add('past-event');
            }
        }
    });

    calendar.render();
});
</script>

@endsection