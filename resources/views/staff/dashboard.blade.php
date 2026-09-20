@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-dashboard.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')

{{-- ================================================================
     HERO — Dynamic Greeting Card
     Skeleton + real content share the same grid cell (same crossfade
     mechanic used on the Patient Dashboard) so revealing never shifts
     the layout — only opacity crossfades.
     ================================================================ --}}
<div class="hero-wrapper" id="heroWrapper">

    {{-- SKELETON --}}
    <div class="hero-section skeleton-hero" id="skeletonHero">
        <div class="dashboard-hero">
            <div class="hero-greeting">
                <div class="skeleton-line skeleton-greeting-title"></div>
                <div class="skeleton-line skeleton-greeting-subtitle"></div>
            </div>
        </div>
    </div>

    {{-- REAL CONTENT --}}
    <div class="hero-section real-hero" id="realHero">
        <div class="dashboard-hero">
            <div class="hero-greeting">
                <h2 class="greeting-text">{{ $greeting }}, {{ $staff->first_name }}! 👋</h2>
                <p class="greeting-subtitle">Here's your clinic activity for today.</p>
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     APPOINTMENT SUMMARY — 5 clickable filter cards
     ================================================================ --}}
<div class="stats-wrapper" id="statsWrapper">

    {{-- SKELETON --}}
    <div class="stats-section skeleton-stats" id="skeletonStats">
        @for ($i = 0; $i < 5; $i++)
        <div class="appt-card skeleton-card">
            <div class="skeleton-icon"></div>
            <div class="appt-info">
                <div class="skeleton-line skeleton-label"></div>
                <div class="skeleton-line skeleton-value"></div>
            </div>
        </div>
        @endfor
    </div>

    {{-- REAL CONTENT --}}
    <div class="stats-section real-stats" id="realStats">

        <a href="{{ route('staff.appointments.index') }}"
           class="appt-card appt-total"
           aria-label="View all {{ $totalAppointments }} appointments">
            <div class="appt-icon"><i class="fas fa-calendar-alt" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Total Appointments</p>
                <h2>{{ $totalAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Pending']) }}"
           class="appt-card appt-pending"
           aria-label="View {{ $pendingAppointments }} pending appointments">
            <div class="appt-icon"><i class="fas fa-hourglass-half" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Pending</p>
                <h2>{{ $pendingAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Approved']) }}"
           class="appt-card appt-approved"
           aria-label="View {{ $approvedAppointments }} approved appointments">
            <div class="appt-icon"><i class="fas fa-check-circle" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Approved</p>
                <h2>{{ $approvedAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Completed']) }}"
           class="appt-card appt-completed"
           aria-label="View {{ $completedAppointments }} completed appointments">
            <div class="appt-icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Completed</p>
                <h2>{{ $completedAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Cancelled']) }}"
           class="appt-card appt-cancelled"
           aria-label="View {{ $cancelledAppointments }} cancelled appointments">
            <div class="appt-icon"><i class="fas fa-times-circle" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Cancelled</p>
                <h2>{{ $cancelledAppointments }}</h2>
            </div>
        </a>

    </div>
</div>

{{-- ================================================================
     MAIN DASHBOARD GRID
     Left/large: Today's Appointments
     Right: Needs Attention + Quick Actions
     ================================================================ --}}
<div class="dashboard-grid">

    {{-- ============================================================
         TODAY'S APPOINTMENTS (main/largest section)
         ============================================================ --}}
    <div class="grid-main">
        <div class="today-wrapper" id="todayWrapper">

            {{-- SKELETON --}}
            <div class="today-section skeleton-today" id="skeletonToday">
                <div class="appt-table-wrap">
                    <div class="table-header-row">
                        <div class="skeleton-line skeleton-th-title"></div>
                        <div class="skeleton-line skeleton-th-link"></div>
                    </div>
                    <div class="today-list">
                        {{-- FIX: 5 skeleton rows now (was 4) to match the
                             real max of 5 rows shown + the reserved
                             .today-list min-height, so skeleton → real
                             never jumps in height. --}}
                        @for ($i = 0; $i < 5; $i++)
                        <div class="today-row-skeleton">
                            <div class="skeleton-line skeleton-time"></div>
                            <div class="skeleton-line skeleton-name"></div>
                            <div class="skeleton-line skeleton-badge-sm"></div>
                        </div>
                        @endfor
                    </div>
                </div>
            </div>

            {{-- REAL CONTENT --}}
            <div class="today-section real-today" id="realToday">
                <div class="appt-table-wrap">
                    <div class="table-header-row">
                        <span><i class="fas fa-calendar-day" style="color:var(--primary); margin-right:8px;" aria-hidden="true"></i>Today's Appointments</span>
                        <a href="{{ route('staff.appointments.index') }}">View All <span aria-hidden="true">→</span></a>
                    </div>

                    <div class="today-list">
                        @forelse($todayAppointments as $appt)
                            <div class="today-row">
                                <div class="today-time">
                                    {{ \Carbon\Carbon::parse($appt->appointment_time)->format('h:i A') }}
                                </div>
                                <div class="today-details">
                                    <p class="today-patient">
                                        @if($appt->patient)
                                            {{ $appt->patient->first_name }} {{ $appt->patient->last_name }}
                                        @elseif($appt->walkinPatient)
                                            {{ $appt->walkinPatient->first_name }} {{ $appt->walkinPatient->last_name }}
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                    <p class="today-meta">
                                        Dr. {{ $appt->doctor->first_name ?? 'N/A' }} {{ $appt->doctor->last_name ?? '' }}
                                        &middot; {{ Str::limit($appt->reason, 40, '...') ?: 'No reason provided' }}
                                    </p>
                                </div>
                                <span class="status-badge {{ Str::slug($appt->status) }}">
                                    {{ $appt->status }}
                                </span>
                            </div>
                        @empty
                            <div class="empty-state">
                                <i class="fas fa-calendar-times" aria-hidden="true"></i>
                                <p>No appointments scheduled for today.</p>
                            </div>
                        @endforelse
                    </div>

                    @if($totalTodayAppointments > 5)
                        <div class="appointments-summary">
                            Showing 5 of {{ $totalTodayAppointments }} appointments
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- ============================================================
         RIGHT COLUMN: Needs Attention + Quick Actions
         ============================================================ --}}
    <div class="grid-side">

        {{-- ---------- NEEDS ATTENTION ---------- --}}
        <div class="side-wrapper" id="attentionWrapper">

            {{-- SKELETON --}}
            <div class="side-section skeleton-attention" id="skeletonAttention">
                <div class="side-card">
                    <div class="skeleton-line skeleton-side-title"></div>
                    <div class="skeleton-line skeleton-side-body"></div>
                    <div class="skeleton-line skeleton-side-btn"></div>
                </div>
            </div>

            {{-- REAL CONTENT --}}
            <div class="side-section real-attention" id="realAttention">
                <div class="side-card">
                    <h3 class="side-card-title">
                        <i class="fas fa-bell" aria-hidden="true"></i> Needs Attention
                    </h3>

                    @if($pendingAppointments > 0)
                        <p class="attention-count">{{ $pendingAppointments }} Pending Appointment{{ $pendingAppointments > 1 ? 's' : '' }}</p>
                        <p class="attention-desc">These appointments are waiting for your action.</p>
                        <a href="{{ route('staff.appointments.index', ['status' => 'Pending']) }}" class="side-btn amber">
                            Review <span aria-hidden="true">→</span>
                        </a>
                    @else
                        <p class="attention-count ok"><i class="fas fa-check-circle" aria-hidden="true"></i> You're all caught up!</p>
                        <p class="attention-desc">No appointments require your attention.</p>
                    @endif
                </div>
            </div>

        </div>

        {{-- ---------- QUICK ACTIONS ---------- --}}
        <div class="side-wrapper" id="actionsWrapper">

            {{-- SKELETON --}}
            <div class="side-section skeleton-actions" id="skeletonActions">
                <div class="side-card">
                    <div class="skeleton-line skeleton-side-title"></div>
                    @for ($i = 0; $i < 3; $i++)
                    <div class="skeleton-line skeleton-action-row"></div>
                    @endfor
                </div>
            </div>

            {{-- REAL CONTENT --}}
            <div class="side-section real-actions" id="realActions">
                <div class="side-card">
                    <h3 class="side-card-title">
                        <i class="fas fa-bolt" aria-hidden="true"></i> Quick Actions
                    </h3>

                    <a href="{{ route('staff.appointments.index') }}" class="quick-action-item">
                        <span class="qa-icon"><i class="fas fa-calendar-alt" aria-hidden="true"></i></span>
                        <span>View Appointments</span>
                        <i class="fas fa-chevron-right qa-arrow" aria-hidden="true"></i>
                    </a>

                    @if($staff->hasPermission('view_patients'))
                    <a href="{{ route('staff.patients.index') }}" class="quick-action-item">
                        <span class="qa-icon"><i class="fas fa-user-injured" aria-hidden="true"></i></span>
                        <span>View Patients</span>
                        <i class="fas fa-chevron-right qa-arrow" aria-hidden="true"></i>
                    </a>
                    @endif

                    <a href="{{ route('staff.billing.index') }}" class="quick-action-item">
                        <span class="qa-icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                        <span>Billing &amp; Payments</span>
                        <i class="fas fa-chevron-right qa-arrow" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

        </div>

    </div>
</div>

{{-- ================================================================
     SCRIPTS
     ================================================================ --}}
<script>
(function () {
    // ─── Crossfade reveal helper (same pattern as the Patient Dashboard) ───
    function reveal(skeletonId, realId) {
        const skeleton = document.getElementById(skeletonId);
        const real = document.getElementById(realId);
        if (!skeleton || !real) return;

        real.classList.add('fade-in');
        skeleton.classList.add('fade-out');

        setTimeout(() => skeleton.classList.add('removed'), 320);
    }

    // Minimum skeleton display time so the transition never flashes
    // even when the page/data is already ready.
    const MIN_SKELETON_TIME = 450;
    const pageLoadedAt = Date.now();

    function revealAfterMinimumDelay(fn) {
        const elapsed = Date.now() - pageLoadedAt;
        const remaining = Math.max(0, MIN_SKELETON_TIME - elapsed);
        setTimeout(fn, remaining);
    }

    revealAfterMinimumDelay(() => reveal('skeletonHero', 'realHero'));
    revealAfterMinimumDelay(() => reveal('skeletonStats', 'realStats'));
    revealAfterMinimumDelay(() => reveal('skeletonToday', 'realToday'));
    revealAfterMinimumDelay(() => reveal('skeletonAttention', 'realAttention'));
    revealAfterMinimumDelay(() => reveal('skeletonActions', 'realActions'));
})();
</script>

@endsection