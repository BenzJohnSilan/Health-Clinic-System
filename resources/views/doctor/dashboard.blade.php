@extends('layouts.doctor')
@section('head')
<link rel="stylesheet" href="{{ asset('css/doctor-dashboard.css') }}">
@endsection
@section('content')

<!-- Page Header with Date & Time -->
<div class="page-header">
    <h1>Dashboard</h1>

    <!-- Date & Time Container -->
    <div id="datetime-container">
        <span id="datetime">Loading...</span>
    </div>
</div>

<!-- Dashboard Cards -->
<div class="cards-container">

    <!-- Total Appointments Card -->
    <div class="dashboard-card appointments-card">
        <div class="card-info">
            <p>TOTAL APPOINTMENTS</p>
            <h2>{{ $totalAppointments }}</h2>
        </div>
        <div class="card-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
    </div>

    <!-- Total Patients Card -->
    <div class="dashboard-card patients-card">
        <div class="card-info">
            <p>TOTAL PATIENTS</p>
            <h2>{{ $totalPatients }}</h2>
        </div>
        <div class="card-icon">
            <i class="fas fa-procedures"></i>
        </div>
    </div>

    <!-- Upcoming Appointments Card -->
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

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Date & Time Script -->
<script>
    const datetimeElement = document.getElementById('datetime');

    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', 
            hour: '2-digit', minute:'2-digit', second:'2-digit' 
        };
        datetimeElement.textContent = now.toLocaleDateString('en-US', options);
    }

    // Run immediately and then every second
    updateDateTime();
    setInterval(updateDateTime, 1000);
</script>

@endsection
