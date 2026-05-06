<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel - Clinic Record System</title>

<!-- Boxicons -->
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- CSS -->
<link rel="stylesheet" href="{{ asset('css/admin-layout.css') }}">
<!-- 
<link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-users.css') }}">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
<link rel="stylesheet" href="{{ asset('css/change-password.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-appointments.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-pending-accounts.css') }}">
 -->
@yield('head')
<style>
/* ================= SCROLLABLE NOTIFICATION DROPDOWN ================= */
.notification-dropdown {
    max-height: 400px; /* Default height */
    overflow-y: auto;
}
.notification-item {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-decoration: none;
    display: block;
    color: inherit;
}
.notification-item:hover {
    background-color: #f4f6f8;
}
.show-all-alerts {
    display: block;
    text-align: center;
    padding: 5px;
    cursor: pointer;
    color: #007BFF;
}
</style>

</head>
<body>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar" id="sidebar">
    <h2>Admin Panel</h2>
    <nav>
        <a href="{{ route('admin.dashboard') }}">
            <i class='bx bx-home'></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('admin.users.index') }}">
            <i class='bx bx-user'></i>
            <span>Manage Users</span>
        </a>
        <a href="{{ route('admin.appointments.index') }}">
            <i class='bx bx-calendar'></i>
            <span>Appointments</span>
        </a>
        <a href="{{ route('admin.patients.index') }}">
            <i class='bx bx-group'></i>
            <span>Patients</span>
        </a>
        <a href="{{ route('admin.pending') }}">
            <i class='bx bx-time'></i>
            <span>Pending Accounts</span>
        </a>
        <a href="{{ route('admin.pending-appointments') }}" class="{{ request()->routeIs('admin.pending-appointments') ? 'active' : '' }}">
            <i class='bx bx-time'></i>
            <span>Pending Appointments</span>
        </a>

        <a href="{{ route('admin.medicines.index') }}">
            <i class='bx bx-capsule'></i>
            <span>Medicine</span>
        </a>

        <a href="{{ route('admin.user-logs') }}">
            <i class='bx bx-history'></i>
            <span>User Logs</span>
        </a>

        <button class="logout-link" onclick="openLogoutModal()">
            <i class='bx bx-log-out'></i>
            <span>Logout</span>
        </button>
    </nav>
</div>

<!-- ================= MAIN CONTENT ================= -->
<div class="main-content" id="mainContent">

    @php
        $admin = auth()->user();
        $pendingAccounts = \App\Models\User::where('approval_status','Pending')->latest()->take(5)->get();
        $pendingAppointments = \App\Models\Appointment::where('status','Pending')->latest()->take(5)->get();
        $notificationCount = $pendingAccounts->count() + $pendingAppointments->count();
    @endphp

    <div class="admin-header">
        <div class="left">
            <i class='bx bx-menu hamburger' id="hamburgerBtn"></i>
            <div>
                <h2>Hello, {{ $admin->first_name }} {{ $admin->last_name }}</h2>
                <p style="font-size:13px; color:#777; margin-top:3px;">
                    Logged in as: <strong>{{ $admin->role }}</strong>
                </p>
            </div>
        </div>

        <!-- RIGHT SECTION -->
        <div class="right">

            <!-- Notification Bell -->
            <div class="notification-wrapper" style="position: relative;">
                <button class="notification-btn" id="notificationBtn">
                    <i class='bx bx-bell'></i>
                    @if($notificationCount > 0)
                        <span class="notification-count">{{ $notificationCount }}</span>
                    @endif
                </button>

                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <h4>Notifications</h4>

                    @php
                        $allNotifications = collect();

                        foreach($pendingAccounts as $user){
                            $allNotifications->push((object)[
                                'type' => 'pending-account',
                                'user_name' => $user->first_name.' '.$user->last_name,
                                'created_at' => $user->created_at
                            ]);
                        }

                        foreach($pendingAppointments as $app){
                            $allNotifications->push((object)[
                                'type' => 'pending-appointment',
                                'reason' => $app->reason,
                                'patient_name' => $app->patient->first_name.' '.$app->patient->last_name,
                                'created_at' => $app->created_at
                            ]);
                        }

                        $allNotifications = $allNotifications->sortByDesc('created_at')->values();
                        $firstNotification = $allNotifications->first();
                    @endphp

                    <!-- First Notification -->
                    @if($firstNotification)
                        @php
                            $link = $firstNotification->type === 'pending-appointment'
                                    ? route('admin.pending-appointments')
                                    : route('admin.pending');
                        @endphp
                        <a href="{{ $link }}" class="notification-item {{ $firstNotification->type }}">
                            <span class="notification-type">
                                {{ $firstNotification->type == 'pending-appointment' ? 'Pending Appointment' : 'Pending Account' }}
                            </span>
                            <small class="notification-date">
                                {{ $firstNotification->created_at->format('F j, Y g:i a') }}
                            </small>
                            @if($firstNotification->type === 'pending-appointment')
                                <p>Patient: {{ $firstNotification->patient_name }}</p>
                                <p>Reason: {{ $firstNotification->reason }}</p>
                            @else
                                <p>{{ $firstNotification->user_name }} - Pending Account</p>
                            @endif
                        </a>
                    @endif

                    <!-- Show All Alerts Button -->
                    @if($allNotifications->count() > 1)
                        <a href="javascript:void(0);" class="show-all-alerts" id="showAllAlerts">Show All Alerts</a>
                    @endif

                    <!-- All Notifications Hidden by Default -->
                    <div id="allNotifications" style="display: none;">
                        @foreach($allNotifications as $notification)
                            @if($loop->first) @continue @endif
                            @php
                                $link = $notification->type === 'pending-appointment'
                                        ? route('admin.pending-appointments')
                                        : route('admin.pending');
                            @endphp
                            <a href="{{ $link }}" class="notification-item {{ $notification->type }}">
                                <span class="notification-type">
                                    {{ $notification->type == 'pending-appointment' ? 'Pending Appointment' : 'Pending Account' }}
                                </span>
                                <small class="notification-date">
                                    {{ $notification->created_at->format('F j, Y g:i a') }}
                                </small>
                                @if($notification->type === 'pending-appointment')
                                    <p>Patient: {{ $notification->patient_name }}</p>
                                    <p>Reason: {{ $notification->reason }}</p>
                                @else
                                    <p>{{ $notification->user_name }} - Pending Account</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Vertical Separator -->
            <div class="right-separator"></div>

            <!-- Avatar + Dropdown -->
            <div class="dropdown-toggle" id="avatarDropdown">
                <strong>{{ $admin->username }}</strong>
                @php
                    $avatar = ($admin->avatar && file_exists(storage_path('app/public/' . $admin->avatar)))
                        ? asset('storage/' . $admin->avatar)
                        : asset('images/default-avatar.jpg');
                @endphp

                <img src="{{ $avatar }}" alt="Admin Avatar">
            </div>

            <div class="dropdown-menu" id="dropdownMenu">
                <a href="{{ route('admin.profile') }}">
                    <i class="bx bx-user"></i> Manage Profile
                </a>
                <a href="{{ route('admin.change-password') }}">
                    <i class="bx bx-lock"></i> Change Password
                </a>
                <button onclick="openLogoutModal()">
                    <i class="bx bx-log-out"></i> Logout
                </button>
            </div>

        </div>
    </div>

    <div class="page-content">
        @yield('content')
    </div>

</div>

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
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn-confirm">Logout</button>
            </form>
        </div>
    </div>
</div>

<!-- ================= SCRIPTS ================= -->
<script>
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const hamburger = document.getElementById('hamburgerBtn');

// Sidebar toggle
hamburger.addEventListener('click', () => {
    if(window.innerWidth <= 900){
        sidebar.classList.toggle('open');
        mainContent.classList.toggle('shift');
    } else {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
    }
});

// Avatar dropdown
const avatarDropdown = document.getElementById('avatarDropdown');
const dropdownMenu = document.getElementById('dropdownMenu');

avatarDropdown.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdownMenu.classList.toggle('show');
});

document.addEventListener('click', () => {
    dropdownMenu.classList.remove('show');
});

// Logout modal
function openLogoutModal() {
    document.getElementById('logoutModal').classList.add('active');
}
function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('active');
}

// Notification dropdown toggle
const notificationBtn = document.getElementById('notificationBtn');
const notificationDropdown = document.getElementById('notificationDropdown');
const showAllAlerts = document.getElementById('showAllAlerts');
const allNotifications = document.getElementById('allNotifications');

notificationBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notificationDropdown.classList.toggle('show');

    // Reset "Show All Alerts" button and hide all notifications when dropdown toggled
    if (!notificationDropdown.classList.contains('show')) {
        allNotifications.style.display = 'none';
        if(showAllAlerts) showAllAlerts.style.display = 'block';
    }
});

// Prevent dropdown from closing when clicking inside
notificationDropdown.addEventListener('click', (e) => {
    e.stopPropagation();
});

// Show all alerts with scrollable effect
if(showAllAlerts){
    showAllAlerts.addEventListener('click', () => {
        allNotifications.style.display = 'block';
        showAllAlerts.style.display = 'none';
        allNotifications.scrollIntoView({ behavior: 'smooth' });
    });
}
</script>

@yield('scripts')
</body>
</html>
