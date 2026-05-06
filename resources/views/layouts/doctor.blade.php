<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Panel</title>

<!-- Boxicons -->
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- CSS -->
<link rel="stylesheet" href="{{ asset('css/doctor-layout.css') }}">

@yield('head')
</head>
<body>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar" id="sidebar">
    <h2>Doctor Panel</h2>

    <nav>
        <a href="{{ route('doctor.dashboard') }}">
            <i class='bx bx-home'></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('doctor.appointments.index') }}">
            <i class='bx bx-calendar'></i>
            <span>Appointments</span>
        </a>

        <a href="{{ route('doctor.patient') }}">
            <i class='bx bx-user-circle'></i>
            <span>Patient</span>
        </a>

        <a href="#" class="disabled-link">
            <i class='bx bx-folder'></i>
            <span>Medical Report</span>
        </a>

        <a href="{{ route('doctor.medicines.index') }}">
            <i class='bx bx-capsule'></i>
            <span>Medicine</span>
        </a>

        <!-- Logout triggers modal -->
        <button class="logout-link" onclick="openLogoutModal()">
            <i class='bx bx-log-out'></i>
            <span>Logout</span>
        </button>
    </nav>
</div>

<!-- ================= MAIN CONTENT ================= -->
<div class="main-content" id="mainContent">

    @php $doctor = auth()->user(); @endphp

    <div class="admin-header">
        <div class="left">
            <i class='bx bx-menu hamburger' id="hamburgerBtn"></i>
            <div>
                <h2>Hello, {{ $doctor->first_name }} {{ $doctor->last_name }}</h2>
                <p style="font-size:13px; color:#777; margin-top:3px;">
                    Logged in as: <strong>{{ $doctor->role }}</strong>
                </p>
            </div>
        </div>

        <!-- DROPDOWN -->
        <div class="right dropdown">
            <div class="dropdown-toggle" id="avatarDropdown">
                <strong>{{ $doctor->username }}</strong>
                @if($doctor->avatar)
                    <img src="{{ asset('storage/'.$doctor->avatar) }}">
                @else
                    <img src="https://via.placeholder.com/45">
                @endif
            </div>

            <div class="dropdown-menu" id="dropdownMenu">
                <a href="{{ route('doctor.profile') }}">
                    <i class="bx bx-user"></i> Manage Profile
                </a>
                <a href="{{ route('doctor.change-password') }}">
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

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-confirm">Logout</button>
            </form>
        </div>
    </div>
</div>

<!-- ================= SCRIPTS ================= -->
<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const hamburger = document.getElementById('hamburgerBtn');

hamburger.addEventListener('click', () => {
    if(window.innerWidth <= 900){
        sidebar.classList.toggle('open');
        mainContent.classList.toggle('shift');
    } else {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
    }
});

// Dropdown toggle
const avatarDropdown = document.getElementById('avatarDropdown');
const dropdownMenu = document.getElementById('dropdownMenu');

avatarDropdown.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdownMenu.classList.toggle('show');
});

document.addEventListener('click', () => {
    dropdownMenu.classList.remove('show');
});

// Logout modal functions
function openLogoutModal() {
    document.getElementById('logoutModal').classList.add('active');
}

function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('active');
}
</script>

</body>
</html>
