<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — ClinicRMS</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/login.css') }}">

</head>

<body>

<!-- ===== BACK TO HOME BUTTON (fixed top-left) ===== -->
<a href="/" class="back-home">
    <i class='bx bx-arrow-back'></i> Back to Home
</a>

<!-- Background blobs -->
<div class="bg-blob bg-blob-1"></div>
<div class="bg-blob bg-blob-2"></div>
<div class="bg-blob bg-blob-3"></div>

<div class="wrapper">

    <!-- ===== LEFT ===== -->
    <div class="left">

        <!-- Floating badges -->
        <div class="left-badge left-badge-1">
            <i class='bx bxs-shield-check'></i> Secure Access
        </div>
        <div class="left-badge left-badge-2">
            <i class='bx bxs-calendar-check'></i> Easy Booking
        </div>

        <div class="left-inner">
            <div class="left-icon">
                <i class='bx bx-plus-medical'></i>
            </div>

            <h1>Health Clinic Record<br>Management System</h1>

            <div class="left-divider"></div>

            <p>Manage appointments, patient records,<br>and clinic operations — all in one place.</p>

            <p class="left-question">Don't have an account yet?</p>

            <a href="{{ route('register') }}" class="btn-register">
                <i class='bx bx-user-plus'></i> Register Now
            </a>
            
        </div>
    </div>

    <!-- ===== RIGHT ===== -->
    <div class="right">

        <div class="right-header">
            <h2>Welcome back 👋</h2>
            <p>Sign in to your account to continue.</p>
        </div>

        <!-- ERROR / SUCCESS MESSAGES -->
        <div class="messages">
            @error('login')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
            @error('password')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
            @error('role')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
            @if(session('error'))
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert-success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
        </div>

        <form method="POST" action="{{ route('login.authenticate') }}">
            @csrf

            <!-- Username / Email -->
            <label class="input-label">Username or Email</label>
            <div class="input-wrap">
                <i class='bx bxs-user input-icon'></i>
                <input type="text" name="login" placeholder="Enter your username or email" required>
            </div>

            <!-- Password -->
            <label class="input-label">Password</label>
            <div class="input-wrap">
                <i class='bx bxs-lock-alt input-icon'></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <i class='bx bxs-show input-toggle' id="togglePassword"></i>
            </div>

            <!-- Options -->
            <div class="options">
                <label>
                    <input type="checkbox"> Remember Me
                </label>
                <a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>
            </div>

            <!-- Submit -->
            <button type="submit" class="login-btn">
                <i class='bx bx-log-in'></i> Login
            </button>
        </form>

        <!-- Divider -->
        <div class="divider">or continue with</div>

        <!-- Social -->
        <div class="social-row">
            <button class="social-btn" title="Google"><i class='bx bxl-google'></i></button>
            <button class="social-btn" title="Facebook"><i class='bx bxl-facebook'></i></button>
            <button class="social-btn" title="GitHub"><i class='bx bxl-github'></i></button>
            <button class="social-btn" title="LinkedIn"><i class='bx bxl-linkedin'></i></button>
        </div>

    </div>
</div>

<script>
const toggle   = document.getElementById('togglePassword');
const password = document.getElementById('password');

toggle.addEventListener('click', () => {
    const isHidden = password.type === 'password';
    password.type  = isHidden ? 'text' : 'password';
    toggle.classList.toggle('bxs-show', !isHidden);
    toggle.classList.toggle('bxs-hide',  isHidden);
});
</script>

</body>
</html>