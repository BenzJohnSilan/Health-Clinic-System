<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — ClinicRMS</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --purple-dark: #6a11cb;
    --purple-light: #a044ff;
    --bg: #f5f7fb;
    --text-dark: #1a1a2e;
    --text-muted: #888;
    --white: #ffffff;
}

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

/* ===== BACK TO HOME ===== */
.back-home {
    position: fixed;
    top: 20px;
    left: 24px;
    z-index: 100;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 20px;
    border-radius: 30px;
    background: rgba(255,255,255,0.85);
    backdrop-filter: blur(12px);
    border: 1.5px solid rgba(106,17,203,0.18);
    color: var(--purple-dark);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: 0.3s;
    box-shadow: 0 4px 14px rgba(106,17,203,0.1);
}

.back-home:hover {
    background: var(--purple-dark);
    color: white;
    border-color: var(--purple-dark);
    box-shadow: 0 6px 20px rgba(106,17,203,0.3);
    transform: translateX(-2px);
}

.back-home i { font-size: 16px; }

/* ===== ANIMATED BACKGROUND BLOBS ===== */
.bg-blob {
    position: fixed;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.35;
    animation: drift 12s ease-in-out infinite;
    pointer-events: none;
    z-index: 0;
}

.bg-blob-1 {
    width: 500px; height: 500px;
    background: radial-gradient(circle, #a044ff, #6a11cb);
    top: -180px; left: -120px;
    animation-delay: 0s;
}

.bg-blob-2 {
    width: 380px; height: 380px;
    background: radial-gradient(circle, #6a11cb, #a044ff);
    bottom: -140px; right: -100px;
    animation-delay: -5s;
}

.bg-blob-3 {
    width: 200px; height: 200px;
    background: radial-gradient(circle, #a044ff80, transparent);
    top: 50%; right: 30%;
    animation-delay: -9s;
    animation-duration: 16s;
}

@keyframes drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33%       { transform: translate(30px, -40px) scale(1.06); }
    66%       { transform: translate(-20px, 25px) scale(0.95); }
}

/* ===== WRAPPER ===== */
.wrapper {
    position: relative;
    z-index: 1;
    display: flex;
    width: 980px;
    min-height: 560px;
    background: var(--white);
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(106, 17, 203, 0.15), 0 8px 24px rgba(0,0,0,0.06);
    animation: fadeUp 0.7s ease forwards;
    opacity: 0;
    transform: translateY(30px);
}

@keyframes fadeUp {
    to { opacity: 1; transform: translateY(0); }
}

/* ===== LEFT PANEL ===== */
.left {
    width: 46%;
    background: linear-gradient(145deg, #6a11cb 0%, #8b2fec 50%, #a044ff 100%);
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 60px 44px;
    position: relative;
    overflow: hidden;
}

.left::before {
    content: '';
    position: absolute;
    width: 320px; height: 320px;
    border: 2px solid rgba(255,255,255,0.12);
    border-radius: 50%;
    top: -100px; right: -100px;
}

.left::after {
    content: '';
    position: absolute;
    width: 220px; height: 220px;
    border: 2px solid rgba(255,255,255,0.08);
    border-radius: 50%;
    bottom: -70px; left: -70px;
}

.left-inner { position: relative; z-index: 1; }

.left-icon {
    width: 72px; height: 72px;
    background: rgba(255,255,255,0.15);
    border: 2px solid rgba(255,255,255,0.25);
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 28px;
    backdrop-filter: blur(6px);
    font-size: 30px;
    animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.2); }
    50%       { box-shadow: 0 0 0 14px rgba(255,255,255,0); }
}

.left h1 {
    font-family: 'Playfair Display', serif;
    font-size: 26px;
    line-height: 1.35;
    margin-bottom: 14px;
    font-weight: 700;
}

.left p {
    font-size: 14px;
    opacity: 0.8;
    line-height: 1.7;
    font-weight: 300;
}

.left-divider {
    width: 40px; height: 2px;
    background: rgba(255,255,255,0.4);
    border-radius: 2px;
    margin: 0 auto 24px;
}

/* Password tips */
.tips {
    list-style: none;
    text-align: left;
    width: 100%;
    margin: 0 0 28px;
}

.tips li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    opacity: 0.85;
    margin-bottom: 12px;
    font-weight: 300;
    line-height: 1.5;
}

.tips li i {
    font-size: 16px;
    opacity: 0.9;
    flex-shrink: 0;
}

.btn-back-login {
    display: inline-flex;
    align-items: center;
    gap: 7px;
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

/* Floating badges */
.left-badge {
    position: absolute;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 30px;
    padding: 6px 14px;
    font-size: 11px;
    font-weight: 500;
    display: flex; align-items: center; gap: 6px;
    animation: floatBadge 6s ease-in-out infinite;
}

.left-badge-1 { top: 18%; left: 10%; animation-delay: 0s; }
.left-badge-2 { top: 72%; right: 8%; animation-delay: -3s; }

@keyframes floatBadge {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-8px); }
}

/* ===== RIGHT PANEL ===== */
.right {
    width: 54%;
    padding: 52px 52px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: var(--white);
}

.right-header { margin-bottom: 28px; }

.right-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    color: var(--text-dark);
    font-weight: 700;
    margin-bottom: 6px;
}

.right-header p {
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 300;
}

/* ===== MESSAGES ===== */
.messages { margin-bottom: 16px; }

.alert-error, .alert-success {
    padding: 10px 16px;
    border-radius: 12px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

/* ===== INPUT ===== */
.input-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 7px;
    display: block;
}

.input-wrap {
    position: relative;
    margin-bottom: 18px;
}

.input-wrap input {
    width: 100%;
    padding: 13px 46px;
    border-radius: 14px;
    border: 1.5px solid rgba(106,17,203,0.14);
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--text-dark);
    outline: none;
    transition: 0.25s;
}

.input-wrap input:focus {
    border-color: var(--purple-dark);
    background: white;
    box-shadow: 0 0 0 4px rgba(106,17,203,0.08);
}

.input-wrap input::placeholder { color: #bbb; }

.input-icon {
    position: absolute;
    left: 16px;
    top: 50%; transform: translateY(-50%);
    color: #aaa;
    font-size: 17px;
    transition: 0.25s;
}

.input-wrap:focus-within .input-icon { color: var(--purple-dark); }

.input-toggle {
    position: absolute;
    right: 16px;
    top: 50%; transform: translateY(-50%);
    color: #aaa;
    font-size: 17px;
    cursor: pointer;
    transition: 0.2s;
}

.input-toggle:hover { color: var(--purple-dark); }

/* Password strength bar */
.strength-bar-wrap {
    margin: -10px 0 18px;
    display: flex;
    gap: 5px;
    align-items: center;
}

.strength-bar-wrap span {
    font-size: 11px;
    color: var(--text-muted);
    margin-left: 4px;
    min-width: 40px;
}

.bar-segment {
    height: 4px;
    flex: 1;
    border-radius: 10px;
    background: #e8e8e8;
    transition: background 0.3s;
}

/* ===== SUBMIT BUTTON ===== */
.submit-btn {
    width: 100%;
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
    margin-bottom: 20px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(106,17,203,0.42);
}

.submit-btn:active { transform: translateY(0); }

/* Back link */
.back-login-link {
    text-align: center;
}

.back-login-link a {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--purple-dark);
    font-weight: 500;
    text-decoration: none;
    transition: 0.2s;
}

.back-login-link a:hover {
    text-decoration: underline;
    gap: 9px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .wrapper {
        flex-direction: column;
        width: 100%;
        margin: 16px;
        min-height: auto;
        border-radius: 20px;
    }

    .left {
        width: 100%;
        padding: 44px 32px;
        border-radius: 0;
    }

    .left-badge { display: none; }

    .right {
        width: 100%;
        padding: 36px 28px;
    }

    .back-home {
        top: 14px;
        left: 14px;
        padding: 8px 16px;
        font-size: 12px;
    }
}
</style>
</head>

<body>

<!-- ===== BACK TO HOME BUTTON ===== -->
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
            <i class='bx bxs-shield-check'></i> Secure Reset
        </div>
        <div class="left-badge left-badge-2">
            <i class='bx bxs-lock-alt'></i> New Password
        </div>

        <div class="left-inner">
            <div class="left-icon">
                <i class='bx bx-shield-quarter'></i>
            </div>

            <h1>Create a New<br>Password</h1>

            <div class="left-divider"></div>

            <p>Make sure your new password<br>is strong and easy to remember.</p>

            <br>

            <ul class="tips">
                <li><i class='bx bx-check-circle'></i> At least 8 characters long</li>
                <li><i class='bx bx-check-circle'></i> Include uppercase & lowercase letters</li>
                <li><i class='bx bx-check-circle'></i> Add numbers or special characters</li>
                <li><i class='bx bx-check-circle'></i> Don't reuse old passwords</li>
            </ul>

            <a href="{{ route('login') }}" class="btn-back-login">
                <i class='bx bx-log-in'></i> Back to Login
            </a>
        </div>
    </div>

    <!-- ===== RIGHT ===== -->
    <div class="right">

        <div class="right-header">
            <h2>Reset Password 🔒</h2>
            <p>Enter your new password below to regain access.</p>
        </div>

        <!-- MESSAGES -->
        <div class="messages">
            @error('email')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
            @error('password')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
            @if(session('success'))
                <div class="alert-success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
        </div>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- EMAIL -->
            <label class="input-label">Email Address</label>
            <div class="input-wrap">
                <i class='bx bxs-envelope input-icon'></i>
                <input type="email" name="email" placeholder="Your registered email" required value="{{ request()->email }}">
            </div>

            <!-- NEW PASSWORD -->
            <label class="input-label">New Password</label>
            <div class="input-wrap">
                <i class='bx bxs-lock-alt input-icon'></i>
                <input type="password" id="password" name="password" placeholder="Enter new password" required>
                <i class='bx bxs-show input-toggle' id="togglePassword"></i>
            </div>

            <!-- Strength bar -->
            <div class="strength-bar-wrap">
                <div class="bar-segment" id="bar1"></div>
                <div class="bar-segment" id="bar2"></div>
                <div class="bar-segment" id="bar3"></div>
                <div class="bar-segment" id="bar4"></div>
                <span id="strengthLabel"></span>
            </div>

            <!-- CONFIRM PASSWORD -->
            <label class="input-label">Confirm Password</label>
            <div class="input-wrap">
                <i class='bx bxs-lock input-icon'></i>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter new password" required>
                <i class='bx bxs-show input-toggle' id="toggleConfirm"></i>
            </div>

            <button type="submit" class="submit-btn">
                <i class='bx bx-check-shield'></i> Reset Password
            </button>
        </form>

    </div>
</div>

<script>
/* ===== Toggle Password Visibility ===== */
const togglePassword = document.getElementById('togglePassword');
const passwordInput  = document.getElementById('password');

togglePassword.addEventListener('click', () => {
    const isHidden = passwordInput.type === 'password';
    passwordInput.type = isHidden ? 'text' : 'password';
    togglePassword.classList.toggle('bxs-show', !isHidden);
    togglePassword.classList.toggle('bxs-hide',  isHidden);
});

const toggleConfirm  = document.getElementById('toggleConfirm');
const confirmInput   = document.getElementById('password_confirmation');

toggleConfirm.addEventListener('click', () => {
    const isHidden = confirmInput.type === 'password';
    confirmInput.type = isHidden ? 'text' : 'password';
    toggleConfirm.classList.toggle('bxs-show', !isHidden);
    toggleConfirm.classList.toggle('bxs-hide',  isHidden);
});

/* ===== Password Strength Meter ===== */
const bars   = [
    document.getElementById('bar1'),
    document.getElementById('bar2'),
    document.getElementById('bar3'),
    document.getElementById('bar4'),
];
const label  = document.getElementById('strengthLabel');

passwordInput.addEventListener('input', () => {
    const val   = passwordInput.value;
    let score   = 0;

    if (val.length >= 8)            score++;
    if (/[A-Z]/.test(val))          score++;
    if (/[0-9]/.test(val))          score++;
    if (/[^A-Za-z0-9]/.test(val))   score++;

    const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

    bars.forEach((bar, i) => {
        bar.style.background = i < score ? colors[score] : '#e8e8e8';
    });

    label.textContent      = val.length ? labels[score] : '';
    label.style.color      = colors[score];
});
</script>

</body>
</html>