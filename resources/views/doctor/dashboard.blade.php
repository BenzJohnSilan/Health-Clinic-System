@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-dashboard.css') }}">

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
@endsection

@section('content')

<!-- ================= PAGE HEADER ================= -->
<div class="page-header">
    <h1>Dashboard</h1>

    <div id="datetime-container">
        <span id="datetime">Loading...</span>
    </div>
</div>

<!-- ================= DASHBOARD CARDS ================= -->
<div class="cards-container">

    <div class="dashboard-card appointments-card">
        <div class="card-info">
            <p>TOTAL APPOINTMENTS</p>
            <h2>{{ $totalAppointments }}</h2>
        </div>
        <div class="card-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
    </div>

    <div class="dashboard-card patients-card">
        <div class="card-info">
            <p>TOTAL PATIENTS</p>
            <h2>{{ $totalPatients }}</h2>
        </div>
        <div class="card-icon">
            <i class="fas fa-procedures"></i>
        </div>
    </div>

    <div class="dashboard-card upcoming-card">
        <div class="card-info">
            <p>UPCOMING APPOINTMENTS</p>
            <h2>{{ $upcomingAppointments }}</h2>
        </div>
        <div class="card-icon">
            <i class="fas fa-hourglass-half"></i>
        </div>
    </div>

</div>

<!-- ================= CALENDAR SECTION ================= -->
<div class="calendar-section">
    <div class="calendar-card">
        <div id="calendar"></div>
    </div>
</div>

<!-- ================= SCRIPTS ================= -->

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== DATE & TIME =====
    const datetimeElement = document.getElementById('datetime');

    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute:'2-digit',
            second:'2-digit'
        };
        datetimeElement.textContent = now.toLocaleString('en-US', options);
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ===== CALENDAR =====
    const calendarEl = document.getElementById('calendar');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: "auto",

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
                title: "{{ $appointment->patient->name ?? 'Patient' }}",
                start: "{{ $start->format('Y-m-d\TH:i:s') }}",
                end: "{{ $end->format('Y-m-d\TH:i:s') }}",
                allDay: false
            },
            @endforeach
        ],

        displayEventTime: true,

        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: 'short'
        },

        eventContent: function(arg) {
            return {
                html: `
                    <div style="line-height:1.2;">
                        <strong>${arg.event.title}</strong><br>
                        <small>
                            ${arg.event.start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}
                            -
                            ${arg.event.end.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}
                        </small>
                    </div>
                `
            };
        },

        eventDidMount: function(info) {
            const now = new Date();
            if (info.event.end < now) {
                info.el.classList.add('past-event');
            }
        }

    });

    calendar.render();

});
</script>

@endsection