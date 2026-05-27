@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-dashboard.css') }}">
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')

<!-- ================= PAGE HEADER ================= -->
<div class="page-header">
    <h1>Doctor Dashboard</h1>
</div>

<!-- ================= DASHBOARD CARDS ================= -->
<div class="stats-section">

    {{-- Total Appointments --}}
    <div class="stat-card" id="stat-total">
        <div class="stat-body">
            <p class="stat-label">Total Appointments</p>
            <h2 class="stat-value" data-target="{{ $totalAppointments }}">0</h2>
            <span class="stat-badge">All Time</span>
        </div>
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
    </div>

    {{-- Total Patients --}}
    <div class="stat-card" id="stat-patients">
        <div class="stat-body">
            <p class="stat-label">Total Patients</p>
            <h2 class="stat-value" data-target="{{ $totalPatients }}">0</h2>
            <span class="stat-badge">Registered</span>
        </div>
        <div class="stat-icon">
            <i class="fas fa-procedures"></i>
        </div>
    </div>

    {{-- Upcoming Appointments --}}
    <div class="stat-card" id="stat-upcoming">
        <div class="stat-body">
            <p class="stat-label">Upcoming</p>
            <h2 class="stat-value" data-target="{{ $upcomingAppointments }}">0</h2>
            <span class="stat-badge">Scheduled</span>
        </div>
        <div class="stat-icon">
            <i class="fas fa-hourglass-half"></i>
        </div>
    </div>

    {{-- Pending Appointments --}}
    <div class="stat-card" id="stat-pending">
        <div class="stat-body">
            <p class="stat-label">Pending</p>
            <h2 class="stat-value" data-target="{{ $pendingAppointments }}">0</h2>
            <span class="stat-badge">Awaiting</span>
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

</div>

<!-- ================= TODAY QUEUE ================= -->
<div class="today-section">

    <h2 class="section-title">Today's Patients Queue</h2>

    {{-- Next Patient --}}
    @if(isset($nextPatient))
        @php $patient = $nextPatient->patient ?? $nextPatient->walkinPatient; @endphp
        <div class="next-patient-card">
            <div class="next-patient-header">
                <span class="next-badge"><i class="fas fa-user-clock"></i> Next Patient</span>
            </div>
            <p class="next-patient-name">
                {{ $patient->first_name ?? '' }} {{ $patient->last_name ?? '' }}
            </p>
            <p class="next-patient-time">
                <i class="fas fa-clock"></i>
                {{ \Carbon\Carbon::parse($nextPatient->appointment_time)->format('h:i A') }}
            </p>
            <a href="{{ route('doctor.appointments.show', $nextPatient->id) }}" class="btn btn-primary">
                <i class="fas fa-stethoscope"></i> Start Consultation
            </a>
        </div>
    @else
        <div class="next-patient-card empty-queue">
            <i class="fas fa-calendar-times empty-icon"></i>
            <p>No patients in queue today.</p>
        </div>
    @endif

    {{-- All Approved Today --}}
    <div class="today-list">
        <div class="today-list-header">
            <h3 class="today-list-title">
                <i class="fas fa-list-ul"></i> All Approved Today
            </h3>
            @php $todayCount = count($todayAppointments ?? []); @endphp
            @if($todayCount > 0)
                <span class="today-count-badge">{{ $todayCount }}</span>
            @endif
        </div>

        <div class="today-list-scroll">
            @forelse($todayAppointments ?? [] as $appointment)
                @php $p = $appointment->patient ?? $appointment->walkinPatient; @endphp
                <div class="patient-item">
                    <div class="patient-item-info">
                        <strong>{{ $p->first_name ?? '' }} {{ $p->last_name ?? '' }}</strong>
                        <small>
                            <i class="fas fa-clock"></i>
                            {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                        </small>
                    </div>
                    <a href="{{ route('doctor.appointments.show', $appointment->id) }}" class="open-link">
                        Open <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            @empty
                <p class="empty-text">No appointments today.</p>
            @endforelse
        </div>
    </div>

</div>

<!-- ================= CALENDAR ================= -->
<div class="calendar-section">
    <div class="calendar-card">
        <div id="calendar"></div>
    </div>
</div>

<!-- ================= RECENT ================= -->
<div class="recent-section">
    <h2 class="section-title">Recent Appointments</h2>

    @foreach($recentAppointments ?? [] as $appointment)
        @php $p = $appointment->patient ?? $appointment->walkinPatient; @endphp
        <div class="recent-item">
            <strong>{{ $p->first_name ?? '' }} {{ $p->last_name ?? '' }}</strong>
            <span class="status-badge status-{{ strtolower($appointment->status) }}">
                {{ $appointment->status }}
            </span>
        </div>
    @endforeach
</div>

<!-- ================= SCRIPTS ================= -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

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
            @foreach($appointments as $appointment)
                @php
                    $start = \Carbon\Carbon::parse($appointment->appointment_date)
                        ->setTimeFromTimeString($appointment->appointment_time);
                    $end = $start->copy()->addMinutes(30);
                @endphp
            {
                title: "{{ $appointment->patient->first_name ?? $appointment->walkinPatient->first_name ?? '' }}",
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
        }
    });

    calendar.render();
});
</script>

@endsection