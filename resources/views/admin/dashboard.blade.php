@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

@section('content')

<!-- ===== PAGE HEADER ===== -->
<div class="page-header">
    <h1>Dashboard</h1>
    <div id="datetime-container">
        <span id="datetime">Loading...</span>
    </div>
</div>

<!-- ===== USER SUMMARY CARDS ===== -->
<div class="cards-container">

    <div class="dashboard-card users-card">
        <div class="card-info">
            <p>TOTAL USERS</p>
            <h2>{{ $totalUsers }}</h2>
        </div>
        <div class="card-icon"><i class="fas fa-users"></i></div>
    </div>

    <div class="dashboard-card doctors-card">
        <div class="card-info">
            <p>TOTAL DOCTORS</p>
            <h2>{{ $totalDoctors }}</h2>
        </div>
        <div class="card-icon"><i class="fas fa-user-md"></i></div>
    </div>

    <div class="dashboard-card patients-card">
        <div class="card-info">
            <p>TOTAL PATIENTS</p>
            <h2>{{ $totalPatients }}</h2>
        </div>
        <div class="card-icon"><i class="fas fa-procedures"></i></div>
    </div>

</div>

<!-- ================================================================
     1. APPOINTMENT OVERVIEW
     ================================================================ -->
<div class="section-title" style="color: #6a0dad;">
    <i class="fas fa-calendar-check" style="color:#6a0dad;"></i>
    Appointment Overview
</div>

<!-- Appointment stat cards -->
<div class="appt-cards">

    <div class="appt-card appt-today">
        <div class="appt-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="appt-info">
            <p>Today's</p>
            <h2>{{ $todaysAppointments }}</h2>
        </div>
    </div>

    <div class="appt-card appt-pending">
        <div class="appt-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="appt-info">
            <p>Pending</p>
            <h2>{{ $pendingAppointments }}</h2>
        </div>
    </div>

    <div class="appt-card appt-approved">
        <div class="appt-icon"><i class="fas fa-check-circle"></i></div>
        <div class="appt-info">
            <p>Approved</p>
            <h2>{{ $approvedAppointments }}</h2>
        </div>
    </div>

    <div class="appt-card appt-completed">
        <div class="appt-icon"><i class="fas fa-clipboard-check"></i></div>
        <div class="appt-info">
            <p>Completed</p>
            <h2>{{ $completedAppointments }}</h2>
        </div>
    </div>

    <div class="appt-card appt-cancelled">
        <div class="appt-icon"><i class="fas fa-times-circle"></i></div>
        <div class="appt-info">
            <p>Cancelled</p>
            <h2>{{ $cancelledAppointments }}</h2>
        </div>
    </div>

</div>

<!-- Today's Appointments Table -->
<div class="appt-table-wrap" style="margin-top: 20px;">
    <div style="padding: 14px 18px; font-weight: 700; font-size: 14px; border-bottom: 1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
        <span><i class="fas fa-list" style="color:#6a0dad; margin-right:8px;"></i>Today's Appointments</span>
        <a href="{{ route('admin.appointments.index') }}" style="font-size:12px; color:#6a0dad; text-decoration:none;">View All →</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Time</th>
                <th>Status</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            @forelse($todaysAppointmentList as $appt)
            <tr>
                <td>{{ $appt->patient->first_name ?? 'N/A' }} {{ $appt->patient->last_name ?? '' }}</td>
                <td>Dr. {{ $appt->doctor->first_name ?? 'N/A' }} {{ $appt->doctor->last_name ?? '' }}</td>
                <td>{{ \Carbon\Carbon::parse($appt->appointment_time)->format('h:i A') }}</td>
                <td><span class="status-badge {{ $appt->status }}">{{ $appt->status }}</span></td>
                <td>{{ Str::limit($appt->reason, 35, '...') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="no-records">
                    <i class="fas fa-calendar-times" style="font-size:24px; color:#d1d5db; display:block; margin-bottom:6px;"></i>
                    No appointments scheduled for today.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- ================================================================
     2. PENDING ALERTS / NOTIFICATIONS
     ================================================================ -->
<div class="section-title" style="color: #d97706;">
    <i class="fas fa-bell" style="color:#d97706;"></i>
    Pending Alerts & Notifications
</div>

<div class="alerts-grid">

    <!-- Pending Accounts -->
    <div class="alert-box">
        <div class="alert-box-header purple">
            <span><i class="fas fa-user-clock" style="margin-right:8px;"></i>Pending User Accounts ({{ $totalPending }})</span>
            <a href="{{ route('admin.pending') }}">View All →</a>
        </div>
        <div class="alert-box-body">
            @if($totalPending > 0)
                <div class="alert-item">
                    <span class="alert-dot red"></span>
                    <span>{{ $totalPending }} account{{ $totalPending > 1 ? 's' : '' }} waiting for approval</span>
                    <span class="alert-meta">
                        <a href="{{ route('admin.pending') }}" style="color:#6a0dad; font-weight:600; text-decoration:none;">Review →</a>
                    </span>
                </div>
            @else
                <div class="alert-empty">
                    <i class="fas fa-check-circle" style="color:#10b981;"></i>
                    No pending accounts. All caught up!
                </div>
            @endif
        </div>
    </div>

    <!-- Pending Appointments -->
    <div class="alert-box">
        <div class="alert-box-header amber">
            <span><i class="fas fa-calendar-exclamation" style="margin-right:8px;"></i>Pending Appointments ({{ $pendingAppointments }})</span>
            <a href="{{ route('admin.appointments.index') }}">View All →</a>
        </div>
        <div class="alert-box-body">
            @if($pendingAppointments > 0)
                <div class="alert-item">
                    <span class="alert-dot amber"></span>
                    <span>{{ $pendingAppointments }} appointment{{ $pendingAppointments > 1 ? 's' : '' }} need{{ $pendingAppointments == 1 ? 's' : '' }} approval</span>
                    <span class="alert-meta">
                        <a href="{{ route('admin.appointments.index') }}" style="color:#d97706; font-weight:600; text-decoration:none;">Review →</a>
                    </span>
                </div>
            @else
                <div class="alert-empty">
                    <i class="fas fa-check-circle" style="color:#10b981;"></i>
                    No pending appointments!
                </div>
            @endif
        </div>
    </div>

</div>

<!-- ================================================================
     3. MEDICINE / INVENTORY ALERTS
     ================================================================ -->
<div class="section-title" style="color: #ef4444;">
    <i class="fas fa-pills" style="color:#ef4444;"></i>
    Medicine & Inventory Alerts
</div>

<!-- Medicine stat mini cards -->
<div class="med-stat-row">

    <div class="med-stat-card red">
        <div class="med-icon"><i class="fas fa-times-circle"></i></div>
        <div class="med-info">
            <p>Out of Stock</p>
            <h3>{{ $outOfStockMedicines }}</h3>
        </div>
    </div>

    <div class="med-stat-card amber">
        <div class="med-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="med-info">
            <p>Low Stock</p>
            <h3>{{ $lowStockMedicines }}</h3>
        </div>
    </div>

    <div class="med-stat-card orange">
        <div class="med-icon"><i class="fas fa-clock"></i></div>
        <div class="med-info">
            <p>Expiring Soon</p>
            <h3>{{ $expiringMedicines }}</h3>
        </div>
    </div>

    <div class="med-stat-card gray">
        <div class="med-icon"><i class="fas fa-ban"></i></div>
        <div class="med-info">
            <p>Already Expired</p>
            <h3>{{ $expiredMedicines }}</h3>
        </div>
    </div>

</div>

<!-- Medicine Alert List -->
<div class="alert-box">
    <div class="alert-box-header" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
        <span><i class="fas fa-clipboard-list" style="margin-right:8px;"></i>Medicine Alert List</span>
        <a href="{{ route('admin.medicines.index') }}">Manage Inventory →</a>
    </div>
    <div class="alert-box-body">
        @forelse($medicineAlertList as $med)
        @php
            $today = \Carbon\Carbon::today();
            $expDate = \Carbon\Carbon::parse($med->expiration_date);
            $isExpired = $expDate->lt($today);
            $isExpiringSoon = !$isExpired && $expDate->lte($today->copy()->addDays(30));

            if ($med->status === 'Out of Stock') {
                $dotClass = 'red';
                $badge = '<span class="badge-pill red">Out of Stock</span>';
            } elseif ($med->status === 'Low Stock') {
                $dotClass = 'amber';
                $badge = '<span class="badge-pill amber">Low Stock</span>';
            } elseif ($isExpired) {
                $dotClass = 'red';
                $badge = '<span class="badge-pill red">Expired</span>';
            } else {
                $dotClass = 'amber';
                $badge = '<span class="badge-pill amber">Expiring Soon</span>';
            }
        @endphp
        <div class="alert-item">
            <span class="alert-dot {{ $dotClass }}"></span>
            <span>
                <strong>{{ $med->medicine_name }}</strong>
                @if($med->brand) <span style="color:#888; font-size:12px;">({{ $med->brand }})</span> @endif
            </span>
            <span class="alert-meta" style="display:flex; align-items:center; gap:8px;">
                {!! $badge !!}
                <span style="font-size:11px; color:#aaa;">Exp: {{ $expDate->format('M d, Y') }}</span>
            </span>
        </div>
        @empty
        <div class="alert-empty">
            <i class="fas fa-check-circle" style="color:#10b981;"></i>
            All medicines are in good condition. No alerts!
        </div>
        @endforelse
    </div>
</div>

<!-- ================================================================
     CHARTS
     ================================================================ -->
<div class="charts-container">

    <div class="chart-container">
        <canvas id="dashboardBarChart"></canvas>
    </div>

    <div class="chart-container">
        <canvas id="dashboardPieChart"></canvas>
    </div>

</div>

<!-- ================================================================
     SCRIPTS
     ================================================================ -->
<script>
    // Date & Time
    const datetimeElement = document.getElementById('datetime');
    function updateDateTime() {
        const now = new Date();
        const options = {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        };
        datetimeElement.textContent = now.toLocaleDateString('en-US', options);
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
</script>

<script>
    // Bar Chart
    const ctxBar = document.getElementById('dashboardBarChart').getContext('2d');

    const gradientUsers = ctxBar.createLinearGradient(0, 0, 0, 350);
    gradientUsers.addColorStop(0, 'rgba(52, 152, 219, 0.8)');
    gradientUsers.addColorStop(1, 'rgba(52, 152, 219, 0.4)');

    const gradientDoctors = ctxBar.createLinearGradient(0, 0, 0, 350);
    gradientDoctors.addColorStop(0, 'rgba(46, 204, 113, 0.8)');
    gradientDoctors.addColorStop(1, 'rgba(46, 204, 113, 0.4)');

    const gradientPatients = ctxBar.createLinearGradient(0, 0, 0, 350);
    gradientPatients.addColorStop(0, 'rgba(231, 76, 60, 0.8)');
    gradientPatients.addColorStop(1, 'rgba(231, 76, 60, 0.4)');

    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['Users', 'Doctors', 'Patients'],
            datasets: [{
                label: 'Total Count',
                data: [{{ $totalUsers }}, {{ $totalDoctors }}, {{ $totalPatients }}],
                backgroundColor: [gradientUsers, gradientDoctors, gradientPatients],
                borderColor: ['rgba(52,152,219,1)', 'rgba(46,204,113,1)', 'rgba(231,76,60,1)'],
                borderWidth: 1,
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Clinic System Overview', font: { size: 16 } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { color: 'rgba(0,0,0,0.05)' } }
            }
        }
    });

    // Pie Chart
    const ctxPie = document.getElementById('dashboardPieChart').getContext('2d');
    new Chart(ctxPie, {
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
                borderColor: ['#fff', '#fff', '#fff'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 13 } } },
                title: { display: true, text: 'User Distribution', font: { size: 16 } }
            }
        }
    });
</script>

@endsection