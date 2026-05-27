@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-dashboard.css') }}">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')

{{-- ===== PAGE HEADER ===== --}}
<div class="page-header">
    <h1>Dashboard</h1>
    <div id="datetime-container">
        <span id="datetime">Loading...</span>
    </div>
</div>

{{-- ===== APPOINTMENT SUMMARY CARDS ===== --}}
<div class="appt-cards">

    <div class="appt-card appt-total">
        <div class="appt-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="appt-info">
            <p>Total Appointments</p>
            <h2>{{ $totalAppointments }}</h2>
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

{{-- ===== PENDING ALERT BANNER ===== --}}
@if($pendingAppointments > 0)
<div class="pending-alert">
    <i class="fas fa-bell"></i>
    <span>
        <strong>{{ $pendingAppointments }} pending appointment{{ $pendingAppointments > 1 ? 's' : '' }}</strong>
        waiting for your action.
    </span>
    <a href="{{ route('staff.pending-appointments') }}">Review Now →</a>
</div>
@endif

{{-- ================================================================
     TODAY'S APPOINTMENTS TABLE
     ================================================================ --}}
<div class="section-title">
    <i class="fas fa-calendar-day"></i>
    Today's Appointments
</div>

<div class="appt-table-wrap">
    <div class="table-header-row">
        <span><i class="fas fa-list" style="color:var(--primary); margin-right:8px;"></i>Scheduled for Today</span>
        <a href="{{ route('staff.appointments.index') }}">View All →</a>
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
            @forelse($todayAppointments as $appt)
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

{{-- ================================================================
     PENDING APPOINTMENTS TABLE
     ================================================================ --}}
<div class="section-title" style="color: #d97706;">
    <i class="fas fa-hourglass-half" style="color:#d97706; background:#fef3c7;"></i>
    Pending Appointments
</div>

<div class="appt-table-wrap">
    <div class="table-header-row amber">
        <span><i class="fas fa-clock" style="margin-right:8px;"></i>Needs Approval ({{ $pendingAppointments }})</span>
        <a href="{{ route('staff.pending-appointments') }}">View All →</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date & Time</th>
                <th>Reason</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendingList as $pending)
            <tr>
                <td>{{ $pending->patient->first_name ?? 'N/A' }} {{ $pending->patient->last_name ?? '' }}</td>
                <td>Dr. {{ $pending->doctor->first_name ?? 'N/A' }} {{ $pending->doctor->last_name ?? '' }}</td>
                <td>
                    {{ \Carbon\Carbon::parse($pending->appointment_date)->format('M j, Y') }}
                    at {{ \Carbon\Carbon::parse($pending->appointment_time)->format('g:i A') }}
                </td>
                <td>{{ Str::limit($pending->reason, 30, '...') ?? '-' }}</td>
                <td>
                    <form action="{{ route('staff.appointments.approve', $pending->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn-approve-sm" onclick="return confirm('Approve this appointment?')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                    </form>
                    <a href="{{ route('staff.pending-appointments') }}" class="btn-view-sm">
                        <i class="fas fa-eye"></i> View
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="no-records">
                    <i class="fas fa-check-circle" style="font-size:24px; color:#10b981; display:block; margin-bottom:6px;"></i>
                    No pending appointments. All caught up!
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ================================================================
     CHART — Appointment Analytics
     ================================================================ --}}
<div class="charts-wrapper">
    <div class="charts-section-title">
        <i class="fas fa-chart-bar"></i>
        Appointment Analytics
    </div>

    <div class="chart-card">
        <div class="chart-card-header">
            <p class="chart-title">Appointment Status Breakdown</p>
            <p class="chart-subtitle">Overview of all appointment statuses</p>
        </div>
        <div class="chart-body">
            <canvas id="staffBarChart"></canvas>
        </div>
        <div class="bar-totals">
            <span class="bar-total-item">
                <span class="bar-total-badge" style="background:#f59e0b;"></span>
                Pending: <strong>{{ $pendingAppointments }}</strong>
            </span>
            <span class="bar-total-item">
                <span class="bar-total-badge" style="background:#10b981;"></span>
                Approved: <strong>{{ $approvedAppointments }}</strong>
            </span>
            <span class="bar-total-item">
                <span class="bar-total-badge" style="background:#3b82f6;"></span>
                Completed: <strong>{{ $completedAppointments }}</strong>
            </span>
            <span class="bar-total-item">
                <span class="bar-total-badge" style="background:#ef4444;"></span>
                Cancelled: <strong>{{ $cancelledAppointments }}</strong>
            </span>
        </div>
    </div>
</div>

{{-- ================================================================
     SCRIPTS
     ================================================================ --}}
<script>
(function () {
    // ─── Date & Time ──────────────────────────────────────────────
    const dtEl = document.getElementById('datetime');
    function tick() {
        if (!dtEl) return;
        dtEl.textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
    tick();
    setInterval(tick, 1000);

    // ─── BAR CHART ────────────────────────────────────────────────
    const ctxBar = document.getElementById('staffBarChart');
    if (ctxBar) {
        const values = [
            {{ $pendingAppointments }},
            {{ $approvedAppointments }},
            {{ $completedAppointments }},
            {{ $cancelledAppointments }}
        ];
        const barMax = Math.max(...values);

        new Chart(ctxBar.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Pending', 'Approved', 'Completed', 'Cancelled'],
                datasets: [{
                    label: 'Appointments',
                    data: values,
                    backgroundColor: [
                        'rgba(245,158,11,0.13)',
                        'rgba(16,185,129,0.13)',
                        'rgba(59,130,246,0.13)',
                        'rgba(239,68,68,0.13)',
                    ],
                    hoverBackgroundColor: [
                        'rgba(245,158,11,0.85)',
                        'rgba(16,185,129,0.85)',
                        'rgba(59,130,246,0.85)',
                        'rgba(239,68,68,0.85)',
                    ],
                    borderColor: ['#f59e0b', '#10b981', '#3b82f6', '#ef4444'],
                    borderWidth: 2,
                    borderRadius: 10,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 900,
                    easing: 'easeOutQuart',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1a2e',
                        titleColor: '#fff',
                        bodyColor: 'rgba(255,255,255,0.75)',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} appointments`,
                        }
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: barMax + Math.ceil(barMax * 0.25) + 1,
                        ticks: {
                            stepSize: 1,
                            color: '#888',
                            font: { size: 11, weight: '600' },
                            callback: v => (Number.isInteger(v) ? v : ''),
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.04)',
                            drawBorder: false,
                        },
                        border: { display: false },
                    },
                    x: {
                        ticks: {
                            color: '#555',
                            font: { size: 12, weight: '700' },
                        },
                        grid: { display: false },
                        border: { display: false },
                    }
                },
                layout: { padding: { top: 24 } },
            },
            plugins: [{
                id: 'barValueLabels',
                afterDatasetsDraw(chart) {
                    const { ctx, data } = chart;
                    const clrs = ['#f59e0b', '#10b981', '#3b82f6', '#ef4444'];
                    chart.getDatasetMeta(0).data.forEach((bar, i) => {
                        const val = data.datasets[0].data[i];
                        ctx.save();
                        ctx.fillStyle = clrs[i];
                        ctx.font = 'bold 13px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(val, bar.x, bar.y - 4);
                        ctx.restore();
                    });
                }
            }]
        });
    }
})();
</script>

@endsection