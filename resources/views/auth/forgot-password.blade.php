<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — ClinicRMS</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
{{-- Shared layout/responsive rules (same card, panels, inputs and alerts as the login page) --}}
<link rel="stylesheet" href="{{ asset('css/login.css') }}">
<link rel="stylesheet" href="{{ asset('css/auth-extras.css') }}">

<style>
/* Forgot-password-only pieces */
.btn-back-login {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 44px;
    padding: 11px 28px;
    border-radius: 30px;
    border: 2px solid rgba(255,255,255,0.6);
    background: transparent;
    color: white;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: 0.3s;
}

.btn-back-login:hover {
    background: white;
    color: var(--purple-dark);
    border-color: white;
}

.info-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(106,17,203,0.05);
    border: 1px solid rgba(106,17,203,0.12);
    border-radius: 12px;
    margin-bottom: 22px;
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.6;
}

.info-note i {
    color: var(--purple-dark);
    font-size: 17px;
    margin-top: 1px;
    flex-shrink: 0;
}

.submit-btn {
    width: 100%;
    min-height: 48px;
    padding: 14px;
    border-radius: 30px;
    border: none;
    background: linear-gradient(135deg, var(--purple-dark), var(--purple-light));
    color: white;
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 6px 20px rgba(106,17,203,0.32);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    letter-spacing: 0.3px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(106,17,203,0.42);
}

.submit-btn:active { transform: translateY(0); }

@media (max-width: 480px) {
    .info-note { padding: 10px 12px; font-size: 12.5px; }
    .submit-btn { font-size: 14px; padding: 13px; }
}
</style>
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
        <div class="left-inner">
            <div class="left-icon">
                <i class='bx bx-key'></i>
            </div>

            <h1>Reset Your<br>Password</h1>

            <div class="left-divider"></div>

            <p>No worries, it happens to everyone.<br>We'll help you get back into your account.</p>

            <a href="{{ route('login') }}" class="btn-back-login">
                <i class='bx bx-log-in'></i> Back to Login
            </a>
        </div>
    </div>

    <!-- ===== RIGHT ===== -->
    <div class="right">

        <div class="right-header">
            <h2>Forgot Password? 🔑</h2>
            <p>Enter your email and we'll send you a reset link.</p>
        </div>

        <!-- MESSAGES -->
        <div class="messages">
            @if(session('success'))
                <div class="alert-success" data-auto-dismiss role="status"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
            @error('email')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
        </div>

        {{-- data-prevent-double-submit stops repeated clicks while the email is being sent --}}
        <form method="POST" action="{{ route('password.email') }}" data-prevent-double-submit>
            @csrf

            <label class="input-label" for="email">Email Address</label>
            <div class="input-wrap">
                <i class='bx bxs-envelope input-icon'></i>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       placeholder="Enter your registered email"
                       autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false"
                       required>
            </div>

            <div class="info-note">
                <i class='bx bxs-info-circle'></i>
                <span>Make sure to check your <strong>spam or junk folder</strong> if you don't see the email within a few minutes.</span>
            </div>

            <button type="submit" class="submit-btn" data-loading-text="Sending...">
                <i class='bx bx-send'></i> Send Reset Link
            </button>
        </form>

    </div>
</div>

<script src="{{ asset('js/prevent-double-submit.js') }}"></script>
<script>
// Auto-hide success messages (e.g. "Password reset link sent") after a few seconds.
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