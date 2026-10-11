<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — ClinicRMS</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/login.css') }}">
<link rel="stylesheet" href="{{ asset('css/auth-extras.css') }}">

</head>

<body class="page-login">

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

        <!-- ACCOUNT-LEVEL MESSAGES (wrong credentials, lockout, pending approval, etc.) -->
        <div class="messages">
            @error('login')
                <div class="alert-error" role="alert"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
            @error('role')
                <div class="alert-error" role="alert"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
            @if(session('error'))
                <div class="alert-error" role="alert"><i class='bx bx-error-circle'></i> {{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert-success" data-auto-dismiss role="status"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
        </div>

        {{-- data-prevent-double-submit stops the double-click 419 error --}}
        <form method="POST" action="{{ route('login.authenticate') }}" data-prevent-double-submit>
            @csrf

            <!-- Username / Email -->
            <label class="input-label" for="login">Username or Email</label>
            <div class="input-wrap">
                <i class='bx bxs-user input-icon'></i>
                <input type="text" id="login" name="login" value="{{ old('login') }}"
                       placeholder="Enter your username or email"
                       autocomplete="username" autocapitalize="none" spellcheck="false"
                       @unless($errors->any() || session('error')) autofocus @endunless
                       required>
            </div>

            <!-- Password -->
            <label class="input-label" for="password">Password</label>
            <div class="input-wrap">
                <i class='bx bxs-lock-alt input-icon'></i>
                <input type="password" id="password" name="password"
                       placeholder="Enter your password"
                       autocomplete="current-password"
                       @class(['is-invalid' => $errors->has('password')])
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                       required>
                <button type="button" class="input-toggle" id="togglePassword"
                        aria-label="Show password" aria-pressed="false">
                    <i class='bx bxs-show'></i>
                </button>
            </div>
            <x-auth.field-error name="password" />

            <!-- Options -->
            <div class="options">
                <label>
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}> Remember Me
                </label>
                <a href="{{ route('password.request') }}" class="forgot-link">Forgot Password?</a>
            </div>

            <!-- Submit -->
            <button type="submit" class="login-btn" data-loading-text="Logging in...">
                <i class='bx bx-log-in'></i> Login
            </button>
        </form>

    </div>
</div>

<script src="{{ asset('js/prevent-double-submit.js') }}"></script>
<script>
const toggleBtn = document.getElementById('togglePassword');
const password  = document.getElementById('password');
const toggleIcon = toggleBtn.querySelector('i');

toggleBtn.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    toggleIcon.className = show ? 'bx bxs-hide' : 'bx bxs-show';
    toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    toggleBtn.setAttribute('aria-pressed', String(show));
});

// Auto-hide success messages (e.g. "You have been logged out.") after a few seconds.
// Error messages are never auto-hidden.
document.querySelectorAll('.alert-success[data-auto-dismiss]').forEach(el => {
    setTimeout(() => {
        el.classList.add('is-hiding');
        setTimeout(() => el.remove(), 600);
    }, 4000);
});
</script>

</body>
</html>