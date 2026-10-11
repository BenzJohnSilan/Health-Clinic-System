@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">

<!-- Chart.js (already used by this dashboard) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')

@php
    $greetingIcon = match($greeting) {
        'Good Morning'   => 'bx-sun',
        'Good Afternoon' => 'bxs-sun',
        default          => 'bx-moon',
    };

    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);

    $moduleIcons = [
        'Appointments'       => 'bx-calendar',
        'Patients'           => 'bx-user',
        'Billing & Payments' => 'bx-receipt',
        'Medicine Inventory' => 'bx-capsule',
        'Medical Records'    => 'bx-folder',
        'Laboratory'         => 'bx-test-tube',
        'Services'           => 'bx-list-ul',
        'Account'            => 'bx-user-circle',
    ];

    $apptIndex = fn (array $q = []) => route('admin.appointments.index', array_merge(['date' => $todayDate], $q));
@endphp

<div class="adm-dash">

    {{-- ================================================================
         HERO — greeting (same card as the Staff / Patient dashboards)
         ================================================================ --}}
    <div class="adm-hero">
        <div class="adm-hero-left">
            <div class="adm-hero-badge"><i class='bx {{ $greetingIcon }}'></i></div>
            <div>
                <h2>{{ $greeting }}, {{ $adminUser->first_name }}! 👋</h2>
                <p>Here's how the clinic is doing today.</p>
            </div>
        </div>
    </div>

    {{-- ================================================================
         1. TODAY'S OVERVIEW
         ================================================================ --}}
    <div class="adm-section-head">
        <h3>Today's Overview</h3>
    </div>

    <div class="adm-overview">
        <a href="{{ $apptIndex() }}" class="adm-stat">
            <div class="adm-stat-icon"><i class='bx bx-calendar'></i></div>
            <div class="adm-stat-info"><p>Today's Appointments</p><h2>{{ $overview['total'] }}</h2></div>
        </a>

        <a href="{{ $apptIndex(['status' => 'Pending']) }}" class="adm-stat adm-stat--pending">
            <div class="adm-stat-icon"><i class='bx bx-hourglass'></i></div>
            <div class="adm-stat-info"><p>Pending</p><h2>{{ $overview['pending'] }}</h2></div>
        </a>

        <a href="{{ $apptIndex(['status' => 'Completed']) }}" class="adm-stat adm-stat--done">
            <div class="adm-stat-icon"><i class='bx bx-task'></i></div>
            <div class="adm-stat-info"><p>Completed</p><h2>{{ $overview['completed'] }}</h2></div>
        </a>

        <a href="{{ $apptIndex(['status' => 'No Show']) }}" class="adm-stat adm-stat--noshow">
            <div class="adm-stat-icon"><i class='bx bx-user-x'></i></div>
            <div class="adm-stat-info"><p>No Show</p><h2>{{ $overview['noShow'] }}</h2></div>
        </a>
    </div>

    {{-- ================================================================
         2. TODAY'S APPOINTMENTS  +  3. PENDING ACTIONS
         ================================================================ --}}
    <div class="adm-grid-main">

        {{-- ---------- 2. TODAY'S APPOINTMENTS ---------- --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-calendar-check'></i></span>
                    <h3>Today's Appointments</h3>
                </div>
                <a href="{{ $apptIndex() }}" class="adm-view-btn">View All <i class='bx bx-chevron-right'></i></a>
            </div>

            <div class="adm-card-body">
                @if($todayAppointments['rows']->isEmpty())
                    <div class="adm-empty">
                        <i class='bx bx-calendar-x'></i>
                        <p>No appointments scheduled for today.</p>
                    </div>
                @else
                    <div class="adm-table-wrap">
                        <table class="adm-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Time</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($todayAppointments['rows'] as $appt)
                                    <tr>
                                        <td class="t-patient">
                                            {{-- Appointment::patientName() resolves registered OR walk-in --}}
                                            {{ $appt->patientName() }}
                                            @if($appt->walkin_patient_id && !$appt->patient_id)
                                                <span class="adm-walkin-tag">Walk-in</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($appt->doctor)
                                                Dr. {{ $appt->doctor->first_name }} {{ $appt->doctor->last_name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="t-time">{{ $appt->formatted_time }}</td>
                                        <td class="t-reason" title="{{ $appt->reason }}">{{ \Illuminate\Support\Str::limit($appt->reason, 40, '...') ?: '—' }}</td>
                                        <td><span class="adm-badge {{ \Illuminate\Support\Str::slug($appt->status) }}">{{ $appt->status }}</span></td>
                                        <td class="t-action">
                                            <a href="{{ route('admin.appointments.show', $appt->id) }}" class="adm-row-link">
                                                View <i class='bx bx-chevron-right'></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($todayAppointments['total'] > $todayAppointments['rows']->count())
                        <div class="adm-caption">
                            Showing {{ $todayAppointments['rows']->count() }} of {{ $todayAppointments['total'] }} appointments today
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- ---------- 3. PENDING ACTIONS ---------- --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-bell'></i></span>
                    <h3>Pending Actions</h3>
                </div>
                @if($pendingActions['total'] > 0)
                    <span class="adm-pill adm-pill--amber"><i class='bx bx-time-five'></i> {{ $pendingActions['total'] }} pending</span>
                @else
                    <span class="adm-pill adm-pill--green"><i class='bx bx-check-circle'></i> All clear</span>
                @endif
            </div>

            <div class="adm-card-body adm-action-list">
                <a href="{{ route('admin.pending') }}"
                   class="adm-action-row {{ $pendingActions['accounts'] > 0 ? 'has-items' : '' }}">
                    <span class="adm-action-icon"><i class='bx bx-user-check'></i></span>
                    <span class="adm-action-text">
                        <strong>Account approvals</strong>
                        <small>New accounts waiting for review</small>
                    </span>
                    <span class="adm-count">{{ $pendingActions['accounts'] }}</span>
                    <i class='bx bx-chevron-right adm-chevron'></i>
                </a>

                <a href="{{ route('admin.appointments.index', ['status' => 'Pending']) }}"
                   class="adm-action-row {{ $pendingActions['appointments'] > 0 ? 'has-items' : '' }}">
                    <span class="adm-action-icon"><i class='bx bx-calendar-check'></i></span>
                    <span class="adm-action-text">
                        <strong>Appointment approvals</strong>
                        <small>Pending appointment requests</small>
                    </span>
                    <span class="adm-count">{{ $pendingActions['appointments'] }}</span>
                    <i class='bx bx-chevron-right adm-chevron'></i>
                </a>

                <a href="{{ route('admin.billing.index', ['status' => 'Unpaid']) }}"
                   class="adm-action-row {{ $pendingActions['unpaidInvoices'] > 0 ? 'has-items' : '' }}">
                    <span class="adm-action-icon"><i class='bx bx-receipt'></i></span>
                    <span class="adm-action-text">
                        <strong>Unpaid bills</strong>
                        <small>{{ $peso($pendingActions['unpaidBalance']) }} outstanding</small>
                    </span>
                    <span class="adm-count">{{ $pendingActions['unpaidInvoices'] }}</span>
                    <i class='bx bx-chevron-right adm-chevron'></i>
                </a>
            </div>
        </div>
    </div>

    {{-- ================================================================
         4. PATIENT STATISTICS
         ================================================================ --}}
    <div class="adm-section-head">
        <h3>Patient Statistics</h3>
        <span>
            {{ $patientStats['total'] }} patients
            · {{ $patientStats['registered'] }} registered
            · {{ $patientStats['walkIn'] }} walk-in
        </span>
    </div>

    <div class="adm-grid-2">

        {{-- A. GENDER DISTRIBUTION (donut) --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-pie-chart-alt-2'></i></span>
                    <h3>Gender Distribution</h3>
                </div>
            </div>

            <div class="adm-card-body">
                @if($patientStats['genderTotal'] === 0)
                    <div class="adm-empty">
                        <i class='bx bx-group'></i>
                        <p>No patient records yet.</p>
                    </div>
                @else
                    <div class="adm-donut">
                        <div class="adm-donut-chart">
                            <canvas id="genderChart" role="img" aria-label="Patient gender distribution"></canvas>
                            <div class="adm-donut-center">
                                <strong>{{ $patientStats['genderTotal'] }}</strong>
                                <span>Patients</span>
                            </div>
                        </div>

                        <ul class="adm-legend">
                            @foreach($patientStats['gender'] as $g)
                                <li>
                                    <span class="dot" style="background: {{ $g['color'] }}"></span>
                                    <span class="name">{{ $g['label'] }}</span>
                                    <span class="num">{{ $g['value'] }}</span>
                                    <span class="pct">{{ round($g['value'] / $patientStats['genderTotal'] * 100) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($patientStats['genderUnknown'] > 0)
                    <p class="adm-note">
                        {{ $patientStats['genderUnknown'] }} patient{{ $patientStats['genderUnknown'] > 1 ? 's' : '' }}
                        with no gender recorded {{ $patientStats['genderUnknown'] > 1 ? 'are' : 'is' }} not included.
                    </p>
                @endif
            </div>
        </div>

        {{-- B. AGE GROUP DISTRIBUTION (bar) --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-bar-chart-alt-2'></i></span>
                    <h3>Age Group Distribution</h3>
                </div>
            </div>

            <div class="adm-card-body">
                @if($patientStats['ageTotal'] === 0)
                    <div class="adm-empty">
                        <i class='bx bx-bar-chart-alt-2'></i>
                        <p>No birthdate data available yet.</p>
                    </div>
                @else
                    <div class="adm-chart-box">
                        <canvas id="ageChart" role="img" aria-label="Patients grouped by age"></canvas>
                    </div>
                @endif

                @if($patientStats['ageUnknown'] > 0)
                    <p class="adm-note">
                        {{ $patientStats['ageUnknown'] }} patient{{ $patientStats['ageUnknown'] > 1 ? 's' : '' }}
                        without a valid birthdate {{ $patientStats['ageUnknown'] > 1 ? 'are' : 'is' }} not included.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- ================================================================
         5. APPOINTMENT STATISTICS  +  6. MEDICINE / INVENTORY ALERTS
         ================================================================ --}}
    <div class="adm-grid-2">

        {{-- ---------- 5. APPOINTMENT STATISTICS ---------- --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-bar-chart-alt-2'></i></span>
                    <h3>Appointment Statistics</h3>
                </div>
                <span class="adm-pill adm-pill--purple">By status</span>
            </div>

            <div class="adm-card-body">
                @if($appointmentStats['total'] === 0)
                    <div class="adm-empty">
                        <i class='bx bx-calendar-x'></i>
                        <p>No appointments recorded yet.</p>
                    </div>
                @else
                    <div class="adm-chart-box adm-chart-box--tall">
                        <canvas id="statusChart" role="img" aria-label="Appointments by status"></canvas>
                    </div>
                    <div class="adm-chart-foot">
                        <span>Total appointments: <strong>{{ $appointmentStats['total'] }}</strong></span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ---------- 6. MEDICINE / INVENTORY ALERTS ---------- --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-capsule'></i></span>
                    <h3>Medicine / Inventory Alerts</h3>
                </div>
                @if($inventory['flagged'] > 0)
                    <span class="adm-pill adm-pill--amber"><i class='bx bx-error'></i> {{ $inventory['flagged'] }} need attention</span>
                @else
                    <span class="adm-pill adm-pill--green"><i class='bx bx-check-circle'></i> All good</span>
                @endif
            </div>

            <div class="adm-card-body">
                <div class="adm-med-tiles">
                    <a href="{{ route('admin.medicines.index') }}" class="adm-med-tile amber">
                        <i class='bx bx-error'></i>
                        <div><h4>{{ $inventory['counts']['low'] }}</h4><p>Low Stock</p></div>
                    </a>
                    <a href="{{ route('admin.medicines.index') }}" class="adm-med-tile red">
                        <i class='bx bx-x-circle'></i>
                        <div><h4>{{ $inventory['counts']['out'] }}</h4><p>Out of Stock</p></div>
                    </a>
                    <a href="{{ route('admin.medicines.index') }}" class="adm-med-tile orange">
                        <i class='bx bx-time-five'></i>
                        <div><h4>{{ $inventory['counts']['expiring'] }}</h4><p>Expiring Soon</p></div>
                    </a>
                    <a href="{{ route('admin.medicines.index') }}" class="adm-med-tile gray">
                        <i class='bx bx-calendar-x'></i>
                        <div><h4>{{ $inventory['counts']['expired'] }}</h4><p>Expired</p></div>
                    </a>
                </div>

                @if(count($inventory['list']) > 0)
                    <div class="adm-med-list">
                        @foreach($inventory['list'] as $med)
                            <div class="adm-med-item">
                                <div class="info">
                                    <strong>{{ $med['name'] }}</strong>
                                    <small>
                                        {{ $med['detail'] ? $med['detail'] . ' · ' : '' }}{{ $med['quantity'] }} {{ $med['unit'] }}
                                        @if($med['expires']) · Exp {{ $med['expires'] }} @endif
                                    </small>
                                </div>
                                <span class="adm-tone {{ $med['tone'] }}">{{ $med['badge'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="adm-empty">
                        <i class='bx bx-check-circle'></i>
                        <p>All medicines are in good condition.</p>
                    </div>
                @endif
            </div>

            <div class="adm-card-footer">
                <a href="{{ route('admin.medicines.index') }}" class="adm-view-btn">Manage Inventory <i class='bx bx-chevron-right'></i></a>
            </div>
        </div>
    </div>

    {{-- ================================================================
         7. BILLING OVERVIEW  +  8. RECENT ACTIVITY
         ================================================================ --}}
    <div class="adm-grid-2">

        {{-- ---------- 7. BILLING OVERVIEW ---------- --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-wallet'></i></span>
                    <h3>Billing Overview</h3>
                </div>
            </div>

            <div class="adm-card-body">
                <div class="adm-bill-top">
                    <div class="adm-bill-box">
                        <p>Today's Billing</p>
                        <h4>{{ $peso($billing['billedToday']) }}</h4>
                        <small>{{ $billing['invoicesToday'] }} invoice{{ $billing['invoicesToday'] === 1 ? '' : 's' }}</small>
                    </div>
                    <div class="adm-bill-box">
                        <p>Collected Today</p>
                        <h4>{{ $peso($billing['collectedToday']) }}</h4>
                        <small>Payments received</small>
                    </div>
                </div>

                <a href="{{ route('admin.billing.index', ['status' => 'Paid']) }}" class="adm-bill-row">
                    <span class="label"><span class="dot" style="background: var(--accent-green)"></span> Paid</span>
                    <span class="val">{{ $billing['paid'] }}</span>
                </a>
                <a href="{{ route('admin.billing.index', ['status' => 'Partially Paid']) }}" class="adm-bill-row">
                    <span class="label"><span class="dot" style="background: var(--accent-blue)"></span> Partially Paid</span>
                    <span class="val">{{ $billing['partial'] }}</span>
                </a>
                <a href="{{ route('admin.billing.index', ['status' => 'Unpaid']) }}" class="adm-bill-row">
                    <span class="label"><span class="dot" style="background: var(--accent-amber)"></span> Unpaid</span>
                    <span class="val">{{ $billing['unpaid'] }}</span>
                </a>
                <div class="adm-bill-row static">
                    <span class="label"><span class="dot" style="background: var(--accent-red)"></span> Outstanding Balance</span>
                    <span class="val">{{ $peso($billing['outstanding']) }}</span>
                </div>
            </div>

            <div class="adm-card-footer">
                <a href="{{ route('admin.billing.index') }}" class="adm-view-btn">View Billing <i class='bx bx-chevron-right'></i></a>
            </div>
        </div>

        {{-- ---------- 8. RECENT ACTIVITY ---------- --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <div class="adm-card-title">
                    <span class="adm-card-icon"><i class='bx bx-history'></i></span>
                    <h3>Recent Activity</h3>
                </div>
                <a href="{{ route('admin.user-logs') }}" class="adm-view-btn">View All <i class='bx bx-chevron-right'></i></a>
            </div>

            <div class="adm-card-body">
                @if($recentActivity->isEmpty())
                    <div class="adm-empty">
                        <i class='bx bx-history'></i>
                        <p>No activity recorded yet.</p>
                    </div>
                @else
                    <div class="adm-activity">
                        @foreach($recentActivity as $log)
                            <div class="adm-activity-item">
                                <span class="adm-activity-icon"><i class='bx {{ $moduleIcons[$log->module] ?? 'bx-history' }}'></i></span>
                                <div class="adm-activity-text">
                                    <strong title="{{ $log->action }}">{{ $log->action }}</strong>
                                    <small>
                                        by {{ $log->user ? trim($log->user->first_name . ' ' . $log->user->last_name) . ' (' . $log->user->role . ')' : 'Unknown user' }}
                                        @if($log->module) · {{ $log->module }} @endif
                                    </small>
                                </div>
                                <div class="adm-activity-time">
                                    {{ $log->created_at->format('M d, Y') }}<br>{{ $log->created_at->format('h:i A') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- ================================================================
     CHARTS — data comes from AdminDashboardService (no queries here)
     ================================================================ --}}
<script>
(function () {
    if (typeof Chart === 'undefined') return;   // CDN unreachable: cards still show their numbers

    Chart.defaults.font.family = '"Poppins", Arial, sans-serif';
    Chart.defaults.animation = false;           // no decorative animation

    const tooltip = {
        backgroundColor: '#1a1a2e',
        padding: 10,
        cornerRadius: 8,
    };

    // Draws each bar's value at its end (above vertical bars, right of horizontal bars).
    const valueLabels = {
        id: 'valueLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const horizontal = chart.options.indexAxis === 'y';
            ctx.save();
            ctx.fillStyle = '#444';
            ctx.font = '700 12px "Poppins", Arial, sans-serif';
            ctx.textAlign = horizontal ? 'left' : 'center';
            ctx.textBaseline = horizontal ? 'middle' : 'bottom';
            chart.getDatasetMeta(0).data.forEach((bar, i) => {
                const value = chart.data.datasets[0].data[i];
                if (horizontal) ctx.fillText(value, bar.x + 6, bar.y);
                else            ctx.fillText(value, bar.x, bar.y - 4);
            });
            ctx.restore();
        }
    };

    // ---------- Gender distribution (donut) ----------
    const genderEl = document.getElementById('genderChart');
    if (genderEl) {
        const gender = {{ \Illuminate\Support\Js::from($patientStats['gender']) }};
        const genderTotal = gender.reduce((sum, g) => sum + g.value, 0);

        new Chart(genderEl, {
            type: 'doughnut',
            data: {
                labels: gender.map(g => g.label),
                datasets: [{
                    data: gender.map(g => g.value),
                    backgroundColor: gender.map(g => g.color),
                    borderColor: '#fff',
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...tooltip,
                        callbacks: {
                            label: c => ` ${c.label}: ${c.parsed} (${genderTotal ? Math.round(c.parsed / genderTotal * 100) : 0}%)`
                        }
                    }
                }
            }
        });
    }

    // ---------- Age group distribution (bar) ----------
    const ageEl = document.getElementById('ageChart');
    if (ageEl) {
        const age = {{ \Illuminate\Support\Js::from($patientStats['ageGroups']) }};

        new Chart(ageEl, {
            type: 'bar',
            data: {
                labels: age.map(a => a.label),
                datasets: [{
                    label: 'Patients',
                    data: age.map(a => a.value),
                    backgroundColor: '#6a0dad',
                    borderRadius: 6,
                    maxBarThickness: 46,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 20 } },
                plugins: { legend: { display: false }, tooltip },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#888', font: { size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        border: { display: false },
                    },
                    x: {
                        ticks: { color: '#444', font: { size: 12, weight: '600' } },
                        grid: { display: false },
                    }
                }
            },
            plugins: [valueLabels]
        });
    }

    // ---------- Appointment status distribution (horizontal bar) ----------
    const statusEl = document.getElementById('statusChart');
    if (statusEl) {
        const status = {{ \Illuminate\Support\Js::from($appointmentStats['items']) }};

        new Chart(statusEl, {
            type: 'bar',
            data: {
                labels: status.map(s => s.label),
                datasets: [{
                    label: 'Appointments',
                    data: status.map(s => s.value),
                    backgroundColor: status.map(s => s.color),
                    borderRadius: 6,
                    maxBarThickness: 20,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 28 } },
                plugins: { legend: { display: false }, tooltip },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#888', font: { size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        border: { display: false },
                    },
                    y: {
                        ticks: { color: '#444', font: { size: 12, weight: '600' } },
                        grid: { display: false },
                    }
                }
            },
            plugins: [valueLabels]
        });
    }
})();
</script>

@endsection