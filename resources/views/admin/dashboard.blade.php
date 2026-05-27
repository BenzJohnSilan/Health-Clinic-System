@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================================
   CHARTS — Improved Styles
   ============================================================ */
.charts-wrapper {
    margin-top: 40px;
    margin-bottom: 40px;
}

.charts-section-title {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.2px;
}

.charts-section-title i {
    font-size: 18px;
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px;
    background: var(--primary-soft);
    color: var(--primary);
}

.charts-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 20px;
}

.chart-card {
    background: var(--bg-card);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.chart-card-header {
    padding: 18px 20px 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.chart-card-header .chart-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--text-dark);
    margin: 0;
    letter-spacing: -0.2px;
}

.chart-card-header .chart-subtitle {
    font-size: 11.5px;
    color: var(--text-light);
    margin: 3px 0 0;
    font-weight: 500;
}

.chart-legend-pills {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
}

.legend-pill {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-mid);
}

.legend-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}

.chart-body {
    padding: 14px 20px 20px;
    flex: 1;
    position: relative;
    min-height: 270px;
}

/* Donut center overlay */
.donut-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.donut-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -56%);
    text-align: center;
    pointer-events: none;
}

.donut-center .dc-value {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-dark);
    line-height: 1;
    transition: all 0.2s ease;
}

.donut-center .dc-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--text-light);
    letter-spacing: 0.8px;
    text-transform: uppercase;
    margin-top: 4px;
    transition: all 0.2s ease;
}

/* Footer totals row */
.bar-totals {
    display: flex;
    gap: 16px;
    padding: 12px 20px 18px;
    flex-wrap: wrap;
    border-top: 1px solid var(--border);
    margin-top: 4px;
}

.bar-total-item {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-mid);
}

.bar-total-item strong {
    font-weight: 800;
    color: var(--text-dark);
}

.bar-total-badge {
    width: 10px; height: 10px;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    .chart-body {
        min-height: 220px;
    }
    .chart-legend-pills {
        display: none;
    }
}
</style>
@endsection

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

    {{-- ✅ ADDED: Staff summary card --}}
    <div class="dashboard-card staff-card">
        <div class="card-info">
            <p>TOTAL STAFF</p>
            <h2>{{ $totalStaff }}</h2>
        </div>
        <div class="card-icon"><i class="fas fa-id-badge"></i></div>
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
            <a href="{{ route('admin.pending-appointments') }}">View All →</a>
        </div>
        <div class="alert-box-body">
            @if($pendingAppointments > 0)
                <div class="alert-item">
                    <span class="alert-dot amber"></span>
                    <span>{{ $pendingAppointments }} appointment{{ $pendingAppointments > 1 ? 's' : '' }} need{{ $pendingAppointments == 1 ? 's' : '' }} approval</span>
                    <span class="alert-meta">
                        <a href="{{ route('admin.pending-appointments') }}" style="color:#d97706; font-weight:600; text-decoration:none;">Review →</a>
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
     CHARTS — with Staff included
     ================================================================ -->
<div class="charts-wrapper">
    <div class="charts-section-title">
        <i class="fas fa-chart-bar"></i>
        Analytics Overview
    </div>

    <div class="charts-grid">

        {{-- BAR CHART --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <p class="chart-title">Clinic System Overview</p>
                    <p class="chart-subtitle">Total active users by role</p>
                </div>
                <div class="chart-legend-pills">
                    <span class="legend-pill"><span class="legend-dot" style="background:#4f46e5;"></span>Users</span>
                    <span class="legend-pill"><span class="legend-dot" style="background:#10b981;"></span>Doctors</span>
                    <span class="legend-pill"><span class="legend-dot" style="background:#f59e0b;"></span>Staff</span>
                    <span class="legend-pill"><span class="legend-dot" style="background:#ef4444;"></span>Patients</span>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="dashboardBarChart"></canvas>
            </div>
            <div class="bar-totals">
                <span class="bar-total-item">
                    <span class="bar-total-badge" style="background:#4f46e5;"></span>
                    Users: <strong>{{ $totalUsers }}</strong>
                </span>
                <span class="bar-total-item">
                    <span class="bar-total-badge" style="background:#10b981;"></span>
                    Doctors: <strong>{{ $totalDoctors }}</strong>
                </span>
                <span class="bar-total-item">
                    <span class="bar-total-badge" style="background:#f59e0b;"></span>
                    Staff: <strong>{{ $totalStaff }}</strong>
                </span>
                <span class="bar-total-item">
                    <span class="bar-total-badge" style="background:#ef4444;"></span>
                    Patients: <strong>{{ $totalPatients }}</strong>
                </span>
            </div>
        </div>

        {{-- DONUT CHART --}}
        <div class="chart-card">
            <div class="chart-card-header">
                <div>
                    <p class="chart-title">User Distribution</p>
                    <p class="chart-subtitle">I-hover ang slice para makita ang breakdown</p>
                </div>
            </div>
            <div class="chart-body">
                <div class="donut-wrap">
                    <canvas id="dashboardPieChart"></canvas>
                    <div class="donut-center">
                        <div class="dc-value" id="donutTotal">{{ $totalUsers + $totalDoctors + $totalStaff + $totalPatients }}</div>
                        <div class="dc-label" id="donutLabel">Total</div>
                    </div>
                </div>
            </div>
            <div class="bar-totals">
                <span class="legend-pill"><span class="legend-dot" style="background:#4f46e5;"></span>Users</span>
                <span class="legend-pill"><span class="legend-dot" style="background:#10b981;"></span>Doctors</span>
                <span class="legend-pill"><span class="legend-dot" style="background:#f59e0b;"></span>Staff</span>
                <span class="legend-pill"><span class="legend-dot" style="background:#ef4444;"></span>Patients</span>
            </div>
        </div>

    </div>
</div>

<!-- ================================================================
     SCRIPTS
     ================================================================ -->
<script>
(function () {
    // ─── Shared palette ───────────────────────────────────────────
    const COLORS = {
        indigo : { solid: '#4f46e5', light: 'rgba(79,70,229,0.13)',  hover: 'rgba(79,70,229,0.85)'  },
        green  : { solid: '#10b981', light: 'rgba(16,185,129,0.13)', hover: 'rgba(16,185,129,0.85)' },
        amber  : { solid: '#f59e0b', light: 'rgba(245,158,11,0.13)', hover: 'rgba(245,158,11,0.85)' },
        red    : { solid: '#ef4444', light: 'rgba(239,68,68,0.13)',  hover: 'rgba(239,68,68,0.85)'  },
    };

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
    const ctxBar = document.getElementById('dashboardBarChart');
    if (ctxBar) {
        const barValues = [{{ $totalUsers }}, {{ $totalDoctors }}, {{ $totalStaff }}, {{ $totalPatients }}];
        const barMax    = Math.max(...barValues);

        new Chart(ctxBar.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Users', 'Doctors', 'Staff', 'Patients'],
                datasets: [{
                    label: 'Total',
                    data: barValues,
                    backgroundColor: [
                        COLORS.indigo.light,
                        COLORS.green.light,
                        COLORS.amber.light,
                        COLORS.red.light,
                    ],
                    hoverBackgroundColor: [
                        COLORS.indigo.hover,
                        COLORS.green.hover,
                        COLORS.amber.hover,
                        COLORS.red.hover,
                    ],
                    borderColor: [
                        COLORS.indigo.solid,
                        COLORS.green.solid,
                        COLORS.amber.solid,
                        COLORS.red.solid,
                    ],
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
                        displayColors: true,
                        boxWidth: 10,
                        boxHeight: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.y} active`,
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
                    const clrs = [
                        COLORS.indigo.solid,
                        COLORS.green.solid,
                        COLORS.amber.solid,
                        COLORS.red.solid,
                    ];
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

    // ─── DONUT CHART ──────────────────────────────────────────────
    const ctxPie = document.getElementById('dashboardPieChart');
    if (ctxPie) {
        const pieValues  = [{{ $totalUsers }}, {{ $totalDoctors }}, {{ $totalStaff }}, {{ $totalPatients }}];
        const pieLabels  = ['Users', 'Doctors', 'Staff', 'Patients'];
        const total      = pieValues.reduce((a, b) => a + b, 0);
        const donutValEl = document.getElementById('donutTotal');
        const donutLblEl = document.getElementById('donutLabel');

        new Chart(ctxPie.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{
                    data: pieValues,
                    backgroundColor: [
                        COLORS.indigo.solid,
                        COLORS.green.solid,
                        COLORS.amber.solid,
                        COLORS.red.solid,
                    ],
                    hoverBackgroundColor: [
                        COLORS.indigo.hover,
                        COLORS.green.hover,
                        COLORS.amber.hover,
                        COLORS.red.hover,
                    ],
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverBorderWidth: 4,
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                animation: {
                    animateRotate: true,
                    duration: 1000,
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
                            label: ctx => {
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                            }
                        }
                    },
                },
                onHover: (_, els) => {
                    if (!donutValEl || !donutLblEl) return;
                    if (els.length) {
                        const idx = els[0].index;
                        donutValEl.textContent = pieValues[idx];
                        donutLblEl.textContent = pieLabels[idx];
                    } else {
                        donutValEl.textContent = total;
                        donutLblEl.textContent = 'Total';
                    }
                }
            },
        });
    }
})();
</script>

@endsection