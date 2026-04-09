@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ================= CHARTS ================= */
.charts-container {
    display: flex;
    flex-wrap: wrap;
    gap: 40px;
    margin-top: 40px;
    justify-content: center;
}

.chart-container {
    flex: 1 1 400px;
    max-width: 500px;
    min-width: 300px;
    background-color: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
    height: 350px; /* fixed height para sakto lang */
}
</style>
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

    <!-- Total Users Card -->
    <div class="dashboard-card users-card">
        <div class="card-info">
            <p>TOTAL USERS</p>
            <h2>{{ $totalUsers }}</h2>
        </div>
        <div class="card-icon">
            <i class="fas fa-users"></i>
        </div>
    </div>

    <!-- Total Doctors Card -->
    <div class="dashboard-card doctors-card">
        <div class="card-info">
            <p>TOTAL DOCTORS</p>
            <h2>{{ $totalDoctors }}</h2>
        </div>
        <div class="card-icon">
            <i class="fas fa-user-md"></i>
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

</div>

<!-- Charts Container -->
<div class="charts-container">

    <!-- Bar Chart -->
    <div class="chart-container">
        <canvas id="dashboardBarChart"></canvas>
    </div>

    <!-- Pie Chart -->
    <div class="chart-container">
        <canvas id="dashboardPieChart"></canvas>
    </div>

</div>

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

    updateDateTime();
    setInterval(updateDateTime, 1000);
</script>

<!-- Dashboard Charts Script -->
<script>
    // === Bar Chart with rounded corners & gradient ===
    const ctxBar = document.getElementById('dashboardBarChart').getContext('2d');
    const gradientUsers = ctxBar.createLinearGradient(0,0,0,350);
    gradientUsers.addColorStop(0, 'rgba(52, 152, 219, 0.8)');
    gradientUsers.addColorStop(1, 'rgba(52, 152, 219, 0.4)');

    const gradientDoctors = ctxBar.createLinearGradient(0,0,0,350);
    gradientDoctors.addColorStop(0, 'rgba(46, 204, 113, 0.8)');
    gradientDoctors.addColorStop(1, 'rgba(46, 204, 113, 0.4)');

    const gradientPatients = ctxBar.createLinearGradient(0,0,0,350);
    gradientPatients.addColorStop(0, 'rgba(231, 76, 60, 0.8)');
    gradientPatients.addColorStop(1, 'rgba(231, 76, 60, 0.4)');

    const dashboardBarChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['Users', 'Doctors', 'Patients'],
            datasets: [{
                label: 'Total Count',
                data: [{{ $totalUsers }}, {{ $totalDoctors }}, {{ $totalPatients }}],
                backgroundColor: [gradientUsers, gradientDoctors, gradientPatients],
                borderColor: ['rgba(52, 152, 219, 1)','rgba(46, 204, 113, 1)','rgba(231, 76, 60, 1)'],
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Clinic System Overview', font: { size: 18 } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { drawBorder:false, color:'rgba(0,0,0,0.05)'} },
                x: { grid: { drawBorder:false, color:'rgba(0,0,0,0.05)'} }
            }
        }
    });

    // === Pie Chart with soft shadows ===
    const ctxPie = document.getElementById('dashboardPieChart').getContext('2d');
    const dashboardPieChart = new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: ['Users', 'Doctors', 'Patients'],
            datasets: [{
                data: [{{ $totalUsers }}, {{ $totalDoctors }}, {{ $totalPatients }}],
                backgroundColor: [
                    'rgba(52, 152, 219, 0.8)',
                    'rgba(46, 204, 113, 0.8)',
                    'rgba(231, 76, 60, 0.8)'
                ],
                borderColor: ['#fff','#fff','#fff'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position:'bottom', labels:{ font:{size:14} } },
                title: { display:true, text:'User Distribution', font:{ size:18 } }
            }
        }
    });
</script>

@endsection