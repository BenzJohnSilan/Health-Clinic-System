@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-dashboard.css') }}">
@endsection

@section('content')

<!-- ================= PAGE HEADER ================= -->
<div class="page-header">

    <div class="header-left">
        <button class="btn-add" id="openModalBtn">
            <i class="fa-solid fa-calendar-plus" style="margin-right: 8px;"></i>
            Add Appointment
        </button>
    </div>

    <h1>Appointment Schedules</h1>

    <div class="header-right">
        <div id="datetime-container">
            <span id="datetime">Loading...</span>
        </div>
    </div>

</div>

<!-- ================= CALENDAR SECTION ================= -->
<div class="calendar-section">
    <div class="calendar-card">
        <div id="calendar"></div>
    </div>
</div>

<!-- ================= MODAL FORM ================= -->
<div class="modal" id="appointmentModal">
    <div class="modal-content">

        <span class="close" id="closeModalBtn">&times;</span>
        <h2>Add New Appointment</h2>

        <!-- SHOW ERROR MESSAGES -->
        @if(session('error') || $errors->any())
            <div class="alert alert-danger" style="
                background-color: #f8d7da;
                color: #842029;
                border: 1px solid #f5c2c7;
                padding: 12px 16px;
                border-radius: 6px;
                margin-bottom: 16px;
                display: flex;
                align-items: center;
            ">
                <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i>
                <div>
                    @if(session('error'))
                        {{ session('error') }}
                    @endif

                    @if($errors->any())
                        <ul style="margin:0; padding-left:18px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('patient.appointments.store') }}">
            @csrf

            <!-- Date -->
            <div class="form-group">
                <label for="appointment_date">Date</label>
                <input type="date"
                       name="appointment_date"
                       id="appointment_date"
                       required
                       value="{{ old('appointment_date') }}"
                       min="{{ date('Y-m-d') }}">
            </div>

            <!-- TIME SLOT -->
            <div class="form-group">
                <label for="appointment_time">Time Slot</label>
                <select name="appointment_time" id="appointment_time" required>
                    <option value="">-- Select Time Slot --</option>

                    <option value="09:00" {{ old('appointment_time') == '09:00' ? 'selected' : '' }}>9:00 AM - 9:30 AM</option>
                    <option value="09:30" {{ old('appointment_time') == '09:30' ? 'selected' : '' }}>9:30 AM - 10:00 AM</option>
                    <option value="10:00" {{ old('appointment_time') == '10:00' ? 'selected' : '' }}>10:00 AM - 10:30 AM</option>
                    <option value="10:30" {{ old('appointment_time') == '10:30' ? 'selected' : '' }}>10:30 AM - 11:00 AM</option>
                    <option value="11:00" {{ old('appointment_time') == '11:00' ? 'selected' : '' }}>11:00 AM - 11:30 AM</option>
                    <option value="11:30" {{ old('appointment_time') == '11:30' ? 'selected' : '' }}>11:30 AM - 12:00 PM</option>
                    <option value="12:00" data-lunch="true">12:00 PM - 1:00 PM (Lunch Break)</option>
                    <option value="13:00" {{ old('appointment_time') == '13:00' ? 'selected' : '' }}>1:00 PM - 1:30 PM</option>
                    <option value="13:30" {{ old('appointment_time') == '13:30' ? 'selected' : '' }}>1:30 PM - 2:00 PM</option>
                    <option value="14:00" {{ old('appointment_time') == '14:00' ? 'selected' : '' }}>2:00 PM - 2:30 PM</option>
                    <option value="14:30" {{ old('appointment_time') == '14:30' ? 'selected' : '' }}>2:30 PM - 3:00 PM</option>
                    <option value="15:00" {{ old('appointment_time') == '15:00' ? 'selected' : '' }}>3:00 PM - 3:30 PM</option>
                    <option value="15:30" {{ old('appointment_time') == '15:30' ? 'selected' : '' }}>3:30 PM - 4:00 PM</option>
                    <option value="16:00" {{ old('appointment_time') == '16:00' ? 'selected' : '' }}>4:00 PM - 4:30 PM</option>
                    <option value="16:30" {{ old('appointment_time') == '16:30' ? 'selected' : '' }}>4:30 PM - 5:00 PM</option>
                </select>
            </div>

            <!-- Doctor -->
            <div class="form-group">
                <label for="doctor_id">Select Doctor</label>
                <select name="doctor_id" id="doctor_id" required>
                    <option value="">-- Choose Doctor --</option>
                    @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}" {{ old('doctor_id') == $doctor->id ? 'selected' : '' }}>
                            {{ $doctor->first_name }} {{ $doctor->last_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Reason -->
            <div class="form-group">
                <label for="reason">Reason / Notes</label>
                <textarea name="reason"
                          id="reason"
                          rows="3"
                          required>{{ old('reason') }}</textarea>
            </div>

            <!-- Buttons -->
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="btn-submit">Save Appointment</button>
            </div>

        </form>
    </div>
</div>

<!-- ================= FULLCALENDAR ================= -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<!-- ================= JAVASCRIPT ================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== REALTIME CLOCK =====
    const datetimeElement = document.getElementById('datetime');
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday:'long',
            year:'numeric',
            month:'long',
            day:'numeric',
            hour:'2-digit',
            minute:'2-digit',
            second:'2-digit'
        };
        datetimeElement.textContent = now.toLocaleString('en-US', options);
    }
    updateDateTime();
    setInterval(updateDateTime,1000);

    // ===== CALENDAR =====
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl,{
        initialView:'dayGridMonth',
        height:720,
        headerToolbar:{
            left:'prev,next today',
            center:'title',
            right:'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events:[
            @foreach($upcomingAppointments as $appointment)
                @php
                    $start = \Carbon\Carbon::parse($appointment->appointment_date)->setTimeFromTimeString($appointment->appointment_time);
                    $end = $start->copy()->addMinutes(30);
                @endphp
            {
                title: "Dr. {{ $appointment->doctor->first_name ?? '' }} {{ $appointment->doctor->last_name ?? '' }}",
                start: "{{ $start->format('Y-m-d\TH:i:s') }}",
                end: "{{ $end->format('Y-m-d\TH:i:s') }}",
                allDay: false
            },
            @endforeach
        ],
        displayEventTime: true,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: 'short' },

        eventContent: function(arg) {
            return {
                html: `<div style="line-height:1.2;">
                        <strong>${arg.event.title}</strong><br>
                        <small>${arg.event.start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})} - ${arg.event.end.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</small>
                       </div>`
            };
        },

        eventDidMount: function(info){
            const now = new Date();
            if(info.event.end < now){
                info.el.classList.add('past-event'); // gray for past
            }
        }
    });
    calendar.render();

    // ===== MODAL LOGIC =====
    const modal = document.getElementById('appointmentModal');
    const openModalBtn = document.getElementById('openModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');

    openModalBtn.addEventListener('click', () => {
        modal.style.display = 'block';
        setDefaultDate();
        disablePastTimes();
    });

    closeModalBtn.onclick = () => modal.style.display='none';
    cancelModalBtn.onclick = () => modal.style.display='none';
    window.onclick = (e)=>{ if(e.target===modal){ modal.style.display='none'; }};

    function setDefaultDate(){
        const dateInput = document.getElementById('appointment_date');
        const today = new Date();
        dateInput.value = today.toISOString().split('T')[0];
    }

    // ===== DISABLE LUNCH + PAST TIMES =====
    const dateInput = document.getElementById('appointment_date');
    dateInput.addEventListener('change', disablePastTimes);
    function disablePastTimes(){
        const selectedDate = document.getElementById('appointment_date').value;
        const today = new Date().toISOString().split('T')[0];
        const now = new Date();
        const currentMinutes = now.getHours()*60 + now.getMinutes();

        const options = document.querySelectorAll('#appointment_time option');
        options.forEach(option=>{
            if(!option.value) return;

            const timeParts = option.value.split(':');
            const optionMinutes = parseInt(timeParts[0])*60 + parseInt(timeParts[1]);

            // disable lunch
            if(option.dataset.lunch){
                option.disabled = true;
                return;
            }

            // disable past times if today
            if(selectedDate === today){
                option.disabled = optionMinutes <= currentMinutes;
            }else{
                option.disabled = false;
            }
        });
    }

    // ===== AUTO OPEN MODAL IF ERROR =====
    @if(session('error') || $errors->any())
        modal.style.display = 'block';
    @endif

});
</script>

@endsection