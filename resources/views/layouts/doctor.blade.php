<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Panel - Clinic Record System</title>

<!-- Boxicons -->
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- CSS -->
<link rel="stylesheet" href="{{ asset('css/doctor-layout.css') }}">

@yield('head')
</head>
<body>

@php
    $doctor = auth()->user();

    $initials = strtoupper(
        substr($doctor->first_name ?? '', 0, 1) .
        substr($doctor->last_name  ?? '', 0, 1)
    );

    $pendingAppointments = \App\Models\Appointment::where('status', 'Pending')->latest()->take(5)->get();
    $notificationCount   = $pendingAppointments->count();
@endphp

<!-- ================= SIDEBAR ================= -->
<div class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class='bx bx-plus-medical'></i>
        </div>
        <div class="brand-text">
            <h2>Clinic Record</h2>
            <p>Doctor Panel</p>
        </div>
    </div>

    <div class="sidebar-nav-wrapper">

        <span class="nav-section-label">Main</span>
        <a href="{{ route('doctor.dashboard') }}"
           class="{{ request()->routeIs('doctor.dashboard') ? 'active' : '' }}">
            <i class='bx bx-home-alt'></i>
            <span>Dashboard</span>
        </a>

        <span class="nav-section-label">Appointments</span>
        <a href="{{ route('doctor.appointments.index') }}"
           class="{{ request()->routeIs('doctor.appointments.*') ? 'active' : '' }}">
            <i class='bx bx-calendar'></i>
            <span>Appointments</span>
        </a>

        <span class="nav-section-label">Records</span>
        <a href="{{ route('doctor.patient') }}"
           class="{{ request()->routeIs('doctor.patient') ? 'active' : '' }}">
            <i class='bx bx-user-circle'></i>
            <span>Patient</span>
        </a>
        <a href="{{ route('doctor.medical-records.index') }}"
           class="{{ request()->routeIs('doctor.medical-records.*') ? 'active' : '' }}">
            <i class='bx bx-folder'></i>
            <span>Medical Records</span>
        </a>

        <span class="nav-section-label">Inventory</span>
        <a href="{{ route('doctor.medicines.index') }}"
           class="{{ request()->routeIs('doctor.medicines.*') ? 'active' : '' }}">
            <i class='bx bx-capsule'></i>
            <span>Medicine</span>
        </a>

        <span class="nav-section-label">Account</span>
        <a href="{{ route('doctor.account-settings') }}"
           class="{{ request()->routeIs('doctor.account-settings') ? 'active' : '' }}">
            <i class='bx bx-cog'></i>
            <span>Account Settings</span>
        </a>

    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user-card">
            @if($doctor->avatar)
                <img class="user-avatar"
                     src="{{ asset('storage/'.$doctor->avatar) }}"
                     alt="Avatar">
            @else
                <div class="user-avatar-placeholder">{{ $initials }}</div>
            @endif
            <div class="user-info">
                <div class="user-name">{{ $doctor->first_name }} {{ $doctor->last_name }}</div>
                <div class="user-role">{{ $doctor->role }}</div>
            </div>
        </div>
    </div>

</div>
<!-- END SIDEBAR -->


<!-- ================= MAIN CONTENT ================= -->
<div class="main-content" id="mainContent">

    <!-- ================= HEADER ================= -->
    <div class="admin-header">

        <div class="header-left">
            <i class='bx bx-menu hamburger' id="hamburgerBtn"></i>

            <div class="breadcrumb">
                <span class="bc-role">
                    <i class='bx bx-user'></i>
                    {{ $doctor->role }}
                </span>
                <span class="bc-sep">›</span>
                <span class="bc-page" id="bcPageLabel">Dashboard</span>
            </div>
        </div>

        <div class="header-right">

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
                            <span class="notif-panel-count">{{ $notificationCount }} pending</span>
                        @endif
                    </div>

                    <!-- Notification List -->
                    <div class="notif-list" id="notifList">

                        @if($pendingAppointments->count() > 0)

                            @foreach($pendingAppointments as $index => $app)
                                <a href="{{ route('doctor.appointments.index') }}"
                                   class="notif-item {{ $index === 0 ? 'notif-item--unread' : '' }}"
                                   data-index="{{ $index }}">

                                    <div class="notif-item-icon">
                                        <i class='bx bx-calendar-check'></i>
                                    </div>

                                    <div class="notif-item-body">
                                        <div class="notif-item-top">
                                            <span class="notif-item-label">Pending Appointment</span>
                                            @if($index === 0)
                                                <span class="notif-item-new">New</span>
                                            @endif
                                        </div>
                                        <p class="notif-item-patient">
                                            {{ $app->patient->first_name }} {{ $app->patient->last_name }}
                                        </p>
                                        <p class="notif-item-reason">{{ $app->reason }}</p>
                                        <span class="notif-item-time">
                                            <i class='bx bx-time-five'></i>
                                            {{ $app->created_at->format('M j, Y · g:i a') }}
                                        </span>
                                    </div>

                                    @if($index === 0)
                                        <div class="notif-item-dot"></div>
                                    @endif

                                </a>

                                @if($index === 0 && $pendingAppointments->count() > 1)
                                    <div class="notif-divider" id="notifDivider">
                                        <button class="notif-show-more" id="notifShowMore">
                                            <i class='bx bx-chevron-down'></i>
                                            Show {{ $pendingAppointments->count() - 1 }} more
                                        </button>
                                    </div>
                                    <div class="notif-hidden" id="notifHidden">
                                @endif

                            @endforeach

                            @if($pendingAppointments->count() > 1)
                                    </div>
                            @endif

                        @else
                            <div class="notif-empty">
                                <div class="notif-empty-icon">
                                    <i class='bx bx-bell-off'></i>
                                </div>
                                <p>No pending appointments</p>
                                <small>You're all caught up!</small>
                            </div>
                        @endif

                    </div>

                    @if($notificationCount > 0)
                        <div class="notif-panel-footer">
                            <a href="{{ route('doctor.appointments.index') }}" class="notif-view-all">
                                View all pending appointments
                                <i class='bx bx-right-arrow-alt'></i>
                            </a>
                        </div>
                    @endif

                </div>
            </div>
            <!-- ===== END NOTIFICATION ===== -->

            <!-- Avatar + name + chevron → dropdown -->
            <div class="header-avatar-group" id="avatarGroup">
                @if($doctor->avatar)
                    <img class="h-avatar"
                         src="{{ asset('storage/'.$doctor->avatar) }}"
                         alt="Avatar">
                @else
                    <div class="h-avatar-initials">{{ $initials }}</div>
                @endif

                <span class="h-name">{{ $doctor->first_name }}</span>
                <i class='bx bx-chevron-down h-chevron'></i>

                <!-- Dropdown -->
                <div class="h-dropdown" id="hDropdown">
                    <div class="h-dropdown-header">
                        @if($doctor->avatar)
                            <img src="{{ asset('storage/'.$doctor->avatar) }}" alt="Avatar">
                        @else
                            <div class="hd-initials">{{ $initials }}</div>
                        @endif
                        <div class="hd-info">
                            <span class="hd-name">{{ $doctor->first_name }} {{ $doctor->last_name }}</span>
                            <span class="hd-role">{{ $doctor->role }}</span>
                        </div>
                    </div>
                    <a href="{{ route('doctor.account-settings') }}">
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

/* ---- Show more notifications ---- */
const notifShowMore = document.getElementById('notifShowMore');
const notifHidden   = document.getElementById('notifHidden');
const notifDivider  = document.getElementById('notifDivider');

if (notifShowMore) {
    notifShowMore.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        notifHidden.classList.add('visible');
        notifDivider.style.display = 'none';
    });
}

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
        'dashboard':       'Dashboard',
        'appointments':    'Appointments',
        'patient':         'Patient',
        'medical-records': 'Medical Records',
        'medicines':       'Medicine',
        'profile':         'Account Settings',
        'change-password': 'Change Password',
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