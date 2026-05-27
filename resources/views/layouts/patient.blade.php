<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Panel - Clinic Record System</title>

<!-- Boxicons -->
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- CSS -->
<link rel="stylesheet" href="{{ asset('css/patient-layout.css') }}">

@yield('head')
</head>
<body>

@php
    $patient = auth()->user();

    $initials = strtoupper(
        substr($patient->first_name ?? '', 0, 1) .
        substr($patient->last_name  ?? '', 0, 1)
    );

    // Status update notifications: appointments that were recently Approved, Rejected, or Cancelled
    $statusUpdates = \App\Models\Appointment::where('patient_id', $patient->id)
        ->whereIn('status', ['Approved', 'Rejected', 'Cancelled'])
        ->latest('updated_at')
        ->take(5)
        ->get();

    // Upcoming appointment reminders: Approved appointments within the next 7 days
    $upcomingAppointments = \App\Models\Appointment::where('patient_id', $patient->id)
        ->where('status', 'Approved')
        ->whereBetween('appointment_date', [now(), now()->addDays(7)])
        ->orderBy('appointment_date')
        ->take(5)
        ->get();

    $notificationCount = $statusUpdates->count() + $upcomingAppointments->count();
@endphp

<!-- ================= SIDEBAR ================= -->
<div class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class='bx bx-plus-medical'></i>
        </div>
        <div class="brand-text">
            <h2>Clinic Record</h2>
            <p>Patient Panel</p>
        </div>
    </div>

    <!-- Nav Items -->
    <div class="sidebar-nav-wrapper">

        <span class="nav-section-label">Main</span>
        <a href="{{ route('patient.dashboard') }}"
           class="{{ request()->routeIs('patient.dashboard') ? 'active' : '' }}">
            <i class='bx bx-home-alt'></i>
            <span>Dashboard</span>
        </a>

        <span class="nav-section-label">Appointments</span>
        <a href="{{ route('patient.appointments.index') }}"
           class="{{ request()->routeIs('patient.appointments.*') ? 'active' : '' }}">
            <i class='bx bx-calendar'></i>
            <span>Appointments</span>
        </a>
        <a href="{{ route('patient.appointment.history') }}"
           class="{{ request()->routeIs('patient.appointment.history') ? 'active' : '' }}">
            <i class='bx bx-history'></i>
            <span>Appointment History</span>
        </a>

        <span class="nav-section-label">Account</span>
        <a href="{{ route('patient.activity.logs') }}"
           class="{{ request()->routeIs('patient.activity.logs') ? 'active' : '' }}">
            <i class='bx bx-time-five'></i>
            <span>Activity Logs</span>
        </a>
        <a href="{{ route('patient.settings') }}"
           class="{{ request()->routeIs('patient.settings') ? 'active' : '' }}">
            <i class='bx bx-cog'></i>
            <span>Account Settings</span>
        </a>

    </div>

    <!-- Sidebar Footer: user info -->
    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            @if($patient->avatar)
                <img class="user-avatar"
                     src="{{ asset('storage/'.$patient->avatar) }}"
                     alt="Avatar">
            @else
                <div class="user-avatar-placeholder">{{ $initials }}</div>
            @endif
            <div class="user-info">
                <div class="user-name">{{ $patient->first_name }} {{ $patient->last_name }}</div>
                <div class="user-role">{{ $patient->role }}</div>
            </div>
        </div>
    </div>

</div>
<!-- END SIDEBAR -->


<!-- ================= MAIN CONTENT ================= -->
<div class="main-content" id="mainContent">

    <!-- ================= HEADER ================= -->
    <div class="admin-header">

        <!-- Left: hamburger + breadcrumb -->
        <div class="header-left">
            <i class='bx bx-menu hamburger' id="hamburgerBtn"></i>

            <div class="breadcrumb">
                <span class="bc-role">
                    <i class='bx bx-user'></i>
                    {{ $patient->role }}
                </span>
                <span class="bc-sep">›</span>
                <span class="bc-page" id="bcPageLabel">Dashboard</span>
            </div>
        </div>

        <!-- Right: date + bell + avatar group + logout -->
        <div class="header-right">

            <!-- Live date & time -->
            <div class="header-date">
                <i class='bx bx-time-five'></i>
                <span id="headerDateTime">Loading...</span>
            </div>

            <!-- ===== NOTIFICATION BELL ===== -->
            <div class="notif-wrapper">
                <button class="header-bell" id="notifBtn" title="Notifications" aria-label="Notifications">
                    <i class='bx bx-bell'></i>
                    @if($notificationCount > 0)
                        <span class="notif-badge">{{ $notificationCount }}</span>
                    @endif
                </button>

                <!-- Notification Panel -->
                <div class="notif-panel" id="notifPanel">

                    <!-- Panel Header -->
                    <div class="notif-panel-header">
                        <div class="notif-panel-title">
                            <i class='bx bx-bell'></i>
                            <span>Notifications</span>
                        </div>
                        @if($notificationCount > 0)
                            <span class="notif-panel-count">{{ $notificationCount }} new</span>
                        @endif
                    </div>

                    <!-- Notification List -->
                    <div class="notif-list" id="notifList">

                        @if($notificationCount > 0)

                            {{-- ---- UPCOMING REMINDERS ---- --}}
                            @foreach($upcomingAppointments as $index => $app)
                                <a href="{{ route('patient.appointments.index') }}"
                                   class="notif-item notif-item--unread">

                                    <div class="notif-item-icon notif-item-icon--upcoming">
                                        <i class='bx bx-calendar-event'></i>
                                    </div>

                                    <div class="notif-item-body">
                                        <div class="notif-item-top">
                                            <span class="notif-item-label notif-label--upcoming">Upcoming</span>
                                            <span class="notif-item-new">Reminder</span>
                                        </div>
                                        <p class="notif-item-patient">{{ $app->reason }}</p>
                                        <p class="notif-item-reason">
                                            {{ \Carbon\Carbon::parse($app->appointment_date)->format('M j, Y') }}
                                            @if($app->appointment_time)
                                                · {{ \Carbon\Carbon::parse($app->appointment_time)->format('g:i a') }}
                                            @endif
                                        </p>
                                        <span class="notif-item-time">
                                            <i class='bx bx-time-five'></i>
                                            {{ \Carbon\Carbon::parse($app->appointment_date)->diffForHumans() }}
                                        </span>
                                    </div>

                                    <div class="notif-item-dot notif-item-dot--upcoming"></div>

                                </a>
                            @endforeach

                            {{-- ---- STATUS UPDATES ---- --}}
                            @foreach($statusUpdates as $index => $app)
                                @php
                                    $statusIcon  = match($app->status) {
                                        'Approved'  => 'bx-check-circle',
                                        'Rejected'  => 'bx-x-circle',
                                        'Cancelled' => 'bx-minus-circle',
                                        default     => 'bx-info-circle',
                                    };
                                    $statusClass = match($app->status) {
                                        'Approved'  => 'notif-item-icon--approved',
                                        'Rejected'  => 'notif-item-icon--rejected',
                                        'Cancelled' => 'notif-item-icon--cancelled',
                                        default     => '',
                                    };
                                    $labelClass  = match($app->status) {
                                        'Approved'  => 'notif-label--approved',
                                        'Rejected'  => 'notif-label--rejected',
                                        'Cancelled' => 'notif-label--cancelled',
                                        default     => '',
                                    };
                                @endphp

                                <a href="{{ route('patient.appointments.index') }}"
                                   class="notif-item {{ $index === 0 && $upcomingAppointments->count() === 0 ? 'notif-item--unread' : '' }}">

                                    <div class="notif-item-icon {{ $statusClass }}">
                                        <i class='bx {{ $statusIcon }}'></i>
                                    </div>

                                    <div class="notif-item-body">
                                        <div class="notif-item-top">
                                            <span class="notif-item-label {{ $labelClass }}">{{ $app->status }}</span>
                                        </div>
                                        <p class="notif-item-patient">{{ $app->reason }}</p>
                                        <p class="notif-item-reason">
                                            @if($app->appointment_date)
                                                {{ \Carbon\Carbon::parse($app->appointment_date)->format('M j, Y') }}
                                            @endif
                                        </p>
                                        <span class="notif-item-time">
                                            <i class='bx bx-time-five'></i>
                                            {{ $app->updated_at->format('M j, Y · g:i a') }}
                                        </span>
                                    </div>

                                </a>
                            @endforeach

                        @else
                            <div class="notif-empty">
                                <div class="notif-empty-icon">
                                    <i class='bx bx-bell-off'></i>
                                </div>
                                <p>No notifications</p>
                                <small>You're all caught up!</small>
                            </div>
                        @endif

                    </div>

                    @if($notificationCount > 0)
                        <div class="notif-panel-footer">
                            <a href="{{ route('patient.appointments.index') }}" class="notif-view-all">
                                View all appointments
                                <i class='bx bx-right-arrow-alt'></i>
                            </a>
                        </div>
                    @endif

                </div>
            </div>
            <!-- ===== END NOTIFICATION ===== -->

            <!-- Avatar + name + chevron → dropdown -->
            <div class="header-avatar-group" id="avatarGroup">
                @if($patient->avatar)
                    <img class="h-avatar"
                         src="{{ asset('storage/'.$patient->avatar) }}"
                         alt="Avatar">
                @else
                    <div class="h-avatar-initials">{{ $initials }}</div>
                @endif

                <span class="h-name">{{ $patient->first_name }}</span>
                <i class='bx bx-chevron-down h-chevron'></i>

                <!-- Dropdown (profile only) -->
                <div class="h-dropdown" id="hDropdown">
                    <div class="h-dropdown-header">
                        @if($patient->avatar)
                            <img src="{{ asset('storage/'.$patient->avatar) }}" alt="Avatar">
                        @else
                            <div class="hd-initials">{{ $initials }}</div>
                        @endif
                        <div class="hd-info">
                            <span class="hd-name">{{ $patient->first_name }} {{ $patient->last_name }}</span>
                            <span class="hd-role">{{ $patient->role }}</span>
                        </div>
                    </div>
                    <a href="{{ route('patient.settings') }}">
                        <i class='bx bx-user-circle'></i> View Profile
                    </a>
                </div>
            </div>

            <!-- Logout icon button -->
            <button class="header-logout-btn" onclick="openLogoutModal()" title="Logout">
                <i class='bx bx-log-out'></i>
            </button>

        </div>
    </div>
    <!-- END HEADER -->

    <!-- Page Content -->
    <div class="page-content">
        @yield('content')
    </div>

</div>
<!-- END MAIN CONTENT -->


<!-- ================= LOGOUT MODAL ================= -->
<div class="logout-modal" id="logoutModal">
    <div class="logout-box">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3>Ready to Leave?</h3>
        <p>Are you sure you want to logout from your account?</p>
        <div class="logout-buttons">
            <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-confirm">Logout</button>
            </form>
        </div>
    </div>
</div>


<!-- ================= SCRIPTS ================= -->
<script>
/* ---- Sidebar toggle ---- */
const sidebar     = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const hamburger   = document.getElementById('hamburgerBtn');

hamburger.addEventListener('click', () => {
    if (window.innerWidth <= 900) {
        sidebar.classList.toggle('open');
        mainContent.classList.toggle('shift');
    } else {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
    }
});

/* ---- Avatar dropdown ---- */
const avatarGroup = document.getElementById('avatarGroup');
const hDropdown   = document.getElementById('hDropdown');

avatarGroup.addEventListener('click', (e) => {
    e.stopPropagation();
    hDropdown.classList.toggle('show');
    notifPanel.classList.remove('show');
});

/* ---- Notification panel ---- */
const notifBtn   = document.getElementById('notifBtn');
const notifPanel = document.getElementById('notifPanel');

notifBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notifPanel.classList.toggle('show');
    hDropdown.classList.remove('show');
});

/* ---- Close on outside click ---- */
document.addEventListener('click', () => {
    hDropdown.classList.remove('show');
    notifPanel.classList.remove('show');
});

/* ---- Live date & time ---- */
function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    document.getElementById('headerDateTime').textContent =
        now.toLocaleString('en-US', options);
}
updateDateTime();
setInterval(updateDateTime, 1000);

/* ---- Active breadcrumb page label ---- */
(function () {
    const map = {
        'dashboard':           'Dashboard',
        'appointments':        'Appointments',
        'appointment/history': 'Appointment History',
        'activity':            'Activity Logs',
        'settings':            'Account Settings',
    };
    const path = window.location.pathname.toLowerCase();
    let label = 'Dashboard';
    for (const [key, val] of Object.entries(map)) {
        if (path.includes(key)) { label = val; break; }
    }
    document.getElementById('bcPageLabel').textContent = label;
})();

/* ---- Logout modal ---- */
function openLogoutModal()  { document.getElementById('logoutModal').classList.add('active'); }
function closeLogoutModal() { document.getElementById('logoutModal').classList.remove('active'); }
</script>

@yield('scripts')
</body>
</html>