@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-dashboard.css') }}">
@endsection

@section('content')

{{-- ================================================================
     PERMISSION-AWARE VIEW MODEL
     One dashboard layout for every Staff account; each section below is
     only rendered when the account holds the matching permission ($can,
     resolved once in StaffController::dashboard). The Position text
     (e.g. "Cashier") is never consulted — access follows permissions.
     ================================================================ --}}
@php
    $showAppointments = $can['appointments'];
    $showTransactions = $can['billing'] && $can['payment_history'];
    $showBillingStats = $can['billing'] && $can['billing_reports'];

    // Billing summary cards, in display order. They are display-only metrics
    // (not links). The four money figures are report data
    // (view_billing_reports); Unpaid Invoices only needs view_billing.
    $billingCards = [];
    if ($showBillingStats) {
        $billingCards[] = ['label' => "Today's Collections", 'value' => '₱' . number_format($billing['todaySales'], 2),     'icon' => 'bx-wallet', 'money' => true];
        $billingCards[] = ['label' => 'Transactions Today', 'value' => number_format($billing['transactionsToday']),        'icon' => 'bx-transfer-alt', 'money' => false];
    }
    if ($can['billing']) {
        $billingCards[] = ['label' => 'Unpaid Invoices',    'value' => number_format($billing['unpaidInvoices']),           'icon' => 'bx-receipt', 'money' => false];
    }
    if ($showBillingStats) {
        $billingCards[] = ['label' => "Today's Discounts",  'value' => '₱' . number_format($billing['todayDiscounts'], 2), 'icon' => 'bx-purchase-tag', 'money' => true];
        $billingCards[] = ['label' => 'Cash Collections',   'value' => '₱' . number_format($billing['cashRevenue'], 2),    'icon' => 'bx-money', 'money' => true];
    }

    // Needs Attention — only items this account can actually act on.
    $attentionItems = [];
    if ($showAppointments && $pendingAppointments > 0) {
        $attentionItems[] = [
            'count' => $pendingAppointments . ' Pending Appointment' . ($pendingAppointments > 1 ? 's' : ''),
            'desc'  => 'These appointments are waiting for your action.',
            'url'   => route('staff.appointments.index', ['status' => 'Pending']),
        ];
    }
    if ($can['billing'] && $billing['unpaidInvoices'] > 0) {
        $attentionItems[] = [
            'count' => $billing['unpaidInvoices'] . ' Unpaid Invoice' . ($billing['unpaidInvoices'] > 1 ? 's' : ''),
            'desc'  => 'These invoices are waiting for payment.',
            'url'   => route('staff.billing.index', ['status' => 'Unpaid']),
        ];
    }
    if ($can['billing'] && $billing['partiallyPaidInvoices'] > 0) {
        $attentionItems[] = [
            'count' => $billing['partiallyPaidInvoices'] . ' Partially Paid Invoice' . ($billing['partiallyPaidInvoices'] > 1 ? 's' : ''),
            'desc'  => 'These invoices still have a remaining balance.',
            'url'   => route('staff.billing.index', ['status' => 'Partially Paid']),
        ];
    }

    if ($showAppointments && !$can['billing']) {
        $attentionEmptyText = 'No appointments require your attention.';
    } elseif ($can['billing'] && !$showAppointments) {
        $attentionEmptyText = 'No payment-related items require your attention.';
    } else {
        $attentionEmptyText = 'Nothing requires your attention right now.';
    }

    // Quick Actions — each one only if the account holds the permission for
    // its destination page, so no link ever leads to a 403. Billing is
    // intentionally NOT a quick action (it is already in the sidebar), so a
    // billing-only Staff (Cashier) has none and the whole card is omitted.
    $quickActions = [];
    if ($showAppointments) {
        $quickActions[] = ['label' => 'View Appointments', 'icon' => 'bx-calendar', 'url' => route('staff.appointments.index')];
    }
    if ($can['patients']) {
        $quickActions[] = ['label' => 'View Patients', 'icon' => 'bx-user', 'url' => route('staff.patients.index')];
    }
    if ($can['medicine']) {
        $quickActions[] = ['label' => 'Medicine', 'icon' => 'bx-capsule', 'url' => route('staff.medicines.index')];
    }
@endphp

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
            <div class="hero-greeting-group">
                <div class="skeleton-line skeleton-hero-icon"></div>
                <div class="hero-greeting">
                    <div class="skeleton-line skeleton-greeting-title"></div>
                    <div class="skeleton-line skeleton-greeting-subtitle"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- REAL CONTENT --}}
    <div class="hero-section real-hero" id="realHero">
        <div class="dashboard-hero">
            @php
                $greetingIcon = match($greeting) {
                    'Good Morning'   => 'bx-sun',
                    'Good Afternoon' => 'bxs-sun',
                    default          => 'bx-moon',
                };
            @endphp
            <div class="hero-greeting-group">
                <div class="hero-icon-badge"><i class='bx {{ $greetingIcon }}'></i></div>
                <div class="hero-greeting">
                    <h2 class="greeting-text">{{ $greeting }}, {{ $staff->first_name }}! 👋</h2>
                    <p class="greeting-subtitle">
                        {{ ($can['billing'] && !$showAppointments) ? "Here's your billing activity for today." : "Here's your clinic activity for today." }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     APPOINTMENT SUMMARY — 5 clickable filter cards  (view_appointments)
     ================================================================ --}}
@if($showAppointments)
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
            <div class="appt-icon"><i class="bx bx-calendar" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Total Appointments</p>
                <h2>{{ $totalAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Pending']) }}"
           class="appt-card appt-pending"
           aria-label="View {{ $pendingAppointments }} pending appointments">
            <div class="appt-icon"><i class="bx bx-hourglass" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Pending</p>
                <h2>{{ $pendingAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Approved']) }}"
           class="appt-card appt-approved"
           aria-label="View {{ $approvedAppointments }} approved appointments">
            <div class="appt-icon"><i class="bx bx-check-circle" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Approved</p>
                <h2>{{ $approvedAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Completed']) }}"
           class="appt-card appt-completed"
           aria-label="View {{ $completedAppointments }} completed appointments">
            <div class="appt-icon"><i class="bx bx-task" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Completed</p>
                <h2>{{ $completedAppointments }}</h2>
            </div>
        </a>

        <a href="{{ route('staff.appointments.index', ['status' => 'Cancelled']) }}"
           class="appt-card appt-cancelled"
           aria-label="View {{ $cancelledAppointments }} cancelled appointments">
            <div class="appt-icon"><i class="bx bx-x-circle" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>Cancelled</p>
                <h2>{{ $cancelledAppointments }}</h2>
            </div>
        </a>

    </div>
</div>
@endif

{{-- ================================================================
     BILLING SUMMARY — clickable cards  (view_billing; money cards also
     need view_billing_reports)
     ================================================================ --}}
@if(count($billingCards) > 0)
<div class="stats-wrapper" id="billingStatsWrapper">

    {{-- SKELETON (same card count as the real row so nothing jumps) --}}
    <div class="stats-section skeleton-stats" id="skeletonBillingStats">
        @foreach($billingCards as $card)
        <div class="appt-card skeleton-card">
            <div class="skeleton-icon"></div>
            <div class="appt-info">
                <div class="skeleton-line skeleton-label"></div>
                <div class="skeleton-line skeleton-value"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- REAL CONTENT --}}
    <div class="stats-section real-stats" id="realBillingStats">
        {{-- Display-only metrics: plain <div>, no link, no click behaviour. --}}
        @foreach($billingCards as $card)
        <div class="appt-card appt-card--static {{ $card['money'] ? 'appt-card--money' : '' }}">
            <div class="appt-icon"><i class="bx {{ $card['icon'] }}" aria-hidden="true"></i></div>
            <div class="appt-info">
                <p>{{ $card['label'] }}</p>
                <h2>{{ $card['value'] }}</h2>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ================================================================
     MAIN DASHBOARD GRID
     Left/large: Today's Appointments
     Right: Needs Attention + Quick Actions
     ================================================================ --}}
<div class="dashboard-grid">

    {{-- ============================================================
         TODAY'S APPOINTMENTS (main/largest section)
         ============================================================ --}}
    <div class="grid-main {{ ($showAppointments && $showTransactions) ? 'grid-main--stack' : '' }}">

        @if($showAppointments)
        <div class="today-wrapper" id="todayWrapper">

            {{-- SKELETON --}}
            <div class="today-section skeleton-today" id="skeletonToday">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <div class="skeleton-line sk-icon"></div>
                            <div class="skeleton-line sk-title"></div>
                        </div>
                        <div class="skeleton-line sk-btn"></div>
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
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <span class="info-card-icon info-card-icon--purple"><i class='bx bx-calendar-check' aria-hidden="true"></i></span>
                            <h3>Today's Appointments</h3>
                        </div>
                        <a href="{{ route('staff.appointments.index') }}" class="card-view-btn">View All <i class='bx bx-chevron-right' aria-hidden="true"></i></a>
                    </div>

                    <div class="today-list">
                        @forelse($todayAppointments as $appt)
                            <div class="today-row">
                                <div class="today-time">
                                    {{ $appt->timeLabel('h:i A') }}
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
                            <div class="info-card-empty">
                                <i class='bx bx-calendar-x' aria-hidden="true"></i>
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
        @endif

        {{-- ============================================================
             TODAY'S TRANSACTIONS  (view_billing + view_payment_history)
             Latest recorded payments today. Each row opens the invoice's
             billing page (gated by view_billing).
             ============================================================ --}}
        @if($showTransactions)
        <div class="today-wrapper" id="txnWrapper">

            {{-- SKELETON --}}
            <div class="today-section skeleton-today" id="skeletonTxns">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <div class="skeleton-line sk-icon"></div>
                            <div class="skeleton-line sk-title"></div>
                        </div>
                        <div class="skeleton-line sk-btn"></div>
                    </div>
                    <div class="today-list">
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
            <div class="today-section real-today" id="realTxns">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <span class="info-card-icon info-card-icon--purple"><i class='bx bx-receipt' aria-hidden="true"></i></span>
                            <h3>Today's Transactions</h3>
                        </div>
                        <a href="{{ route('staff.billing.index') }}" class="card-view-btn">View All <i class='bx bx-chevron-right' aria-hidden="true"></i></a>
                    </div>

                    <div class="today-list">
                        @forelse($billing['transactions'] as $payment)
                            @php $txnInvoice = $payment->invoice; @endphp
                            <a href="{{ route('staff.billing.show', $payment->invoice_id) }}"
                               class="today-row"
                               title="View invoice">
                                <div class="today-time">{{ $payment->paid_at->format('h:i A') }}</div>
                                <div class="today-details">
                                    <p class="today-patient">{{ $txnInvoice ? $txnInvoice->patientName() : 'Unknown Patient' }}</p>
                                    <p class="today-meta">
                                        {{ $txnInvoice->invoice_no ?? 'N/A' }}
                                        &middot; {{ $payment->payment_method }}
                                    </p>
                                </div>
                                <div class="txn-side">
                                    <span class="txn-amount">₱{{ number_format((float) $payment->amount, 2) }}</span>
                                    @if($txnInvoice)
                                        <span class="status-badge {{ Str::slug($txnInvoice->status) }}">{{ $txnInvoice->status }}</span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="info-card-empty">
                                <i class='bx bx-receipt' aria-hidden="true"></i>
                                <p>No payments recorded today.</p>
                            </div>
                        @endforelse
                    </div>

                    @if($billing['totalTodayTransactions'] > 5)
                        <div class="appointments-summary">
                            Showing 5 of {{ $billing['totalTodayTransactions'] }} transactions
                        </div>
                    @endif
                </div>
            </div>

        </div>
        @endif

        {{-- Neither main section is available for this account's permissions:
             keep the grid intact with a neutral card instead of an empty hole. --}}
        @if(!$showAppointments && !$showTransactions)
        <div class="today-wrapper">
            <div class="today-section">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <span class="info-card-icon info-card-icon--purple"><i class='bx bx-layout' aria-hidden="true"></i></span>
                            <h3>Your Workspace</h3>
                        </div>
                    </div>
                    <div class="info-card-empty">
                        <i class='bx bx-info-circle' aria-hidden="true"></i>
                        <p>No dashboard summary is available for your current access. Use the menu to open the sections you have been given access to.</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>

    {{-- ============================================================
         RIGHT COLUMN: Needs Attention + Quick Actions
         ============================================================ --}}
    <div class="grid-side">

        {{-- ---------- NEEDS ATTENTION ---------- --}}
        <div class="side-wrapper" id="attentionWrapper">

            {{-- SKELETON --}}
            <div class="side-section skeleton-attention" id="skeletonAttention">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <div class="skeleton-line sk-icon"></div>
                            <div class="skeleton-line sk-title"></div>
                        </div>
                        <div class="skeleton-line sk-pill"></div>
                    </div>
                    <div class="skeleton-line skeleton-side-body"></div>
                    <div class="skeleton-line skeleton-side-btn"></div>
                </div>
            </div>

            {{-- REAL CONTENT --}}
            <div class="side-section real-attention" id="realAttention">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <span class="info-card-icon info-card-icon--purple"><i class='bx bx-bell' aria-hidden="true"></i></span>
                            <h3>Needs Attention</h3>
                        </div>
                        @if(count($attentionItems) > 0)
                            <span class="status-pill status-pill--amber"><i class='bx bx-time-five' aria-hidden="true"></i> Pending</span>
                        @else
                            <span class="status-pill status-pill--green"><i class='bx bx-check-circle' aria-hidden="true"></i> Clear</span>
                        @endif
                    </div>

                    @if(count($attentionItems) > 0)
                        @foreach($attentionItems as $item)
                            <div class="attention-item">
                                <div class="info-card-body">
                                    <p class="attention-count">{{ $item['count'] }}</p>
                                    <p class="attention-desc">{{ $item['desc'] }}</p>
                                </div>

                                <div class="info-card-footer">
                                    <a href="{{ $item['url'] }}" class="card-view-btn">
                                        Review <i class='bx bx-chevron-right' aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="info-card-body">
                            <p class="attention-count ok"><i class='bx bx-check-circle' aria-hidden="true"></i> You're all caught up!</p>
                            <p class="attention-desc">{{ $attentionEmptyText }}</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ---------- QUICK ACTIONS ----------
             Omitted entirely (no empty container) when the account has no
             quick actions, e.g. a billing-only Cashier. --}}
        @if(count($quickActions) > 0)
        <div class="side-wrapper" id="actionsWrapper">

            {{-- SKELETON --}}
            <div class="side-section skeleton-actions" id="skeletonActions">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <div class="skeleton-line sk-icon"></div>
                            <div class="skeleton-line sk-title"></div>
                        </div>
                    </div>
                    @for ($i = 0; $i < 3; $i++)
                    <div class="skeleton-line skeleton-action-row"></div>
                    @endfor
                </div>
            </div>

            {{-- REAL CONTENT --}}
            <div class="side-section real-actions" id="realActions">
                <div class="info-card">
                    <div class="info-card-header">
                        <div class="info-card-title">
                            <span class="info-card-icon info-card-icon--purple"><i class='bx bx-bolt-circle' aria-hidden="true"></i></span>
                            <h3>Quick Actions</h3>
                        </div>
                    </div>

                    @foreach($quickActions as $action)
                        <a href="{{ $action['url'] }}" class="quick-action-item">
                            <span class="qa-icon"><i class="bx {{ $action['icon'] }}" aria-hidden="true"></i></span>
                            <span>{{ $action['label'] }}</span>
                            <i class="bx bx-chevron-right qa-arrow" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </div>

        </div>
        @endif

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
    revealAfterMinimumDelay(() => reveal('skeletonBillingStats', 'realBillingStats'));
    revealAfterMinimumDelay(() => reveal('skeletonToday', 'realToday'));
    revealAfterMinimumDelay(() => reveal('skeletonTxns', 'realTxns'));
    revealAfterMinimumDelay(() => reveal('skeletonAttention', 'realAttention'));
    revealAfterMinimumDelay(() => reveal('skeletonActions', 'realActions'));
})();
</script>

@endsection