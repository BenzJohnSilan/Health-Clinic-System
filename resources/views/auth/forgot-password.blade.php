<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — ClinicRMS</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --purple-dark: #6a11cb;
    --purple-light: #a044ff;
    --bg: #f5f7fb;
    --card-bg: #eef1f6;
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

/* ===== BACK TO HOME (top-left fixed) ===== */
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
    margin-bottom: 32px;
    font-weight: 300;
}

.left-divider {
    width: 40px; height: 2px;
    background: rgba(255,255,255,0.4);
    border-radius: 2px;
    margin: 0 auto 24px;
}

/* Steps list on left panel */
.steps {
    list-style: none;
    text-align: left;
    width: 100%;
    margin-bottom: 28px;
}

.steps li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 13px;
    opacity: 0.85;
    margin-bottom: 14px;
    font-weight: 300;
    line-height: 1.5;
}

.step-num {
    width: 22px; height: 22px;
    min-width: 22px;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.35);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
    font-weight: 600;
    margin-top: 1px;
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

/* Floating pill badges */
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
    margin-bottom: 22px;
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

/* Info note */
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

/* Back to login link */
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
            <i class='bx bxs-lock-alt'></i> Secure Reset
        </div>
        <div class="left-badge left-badge-2">
            <i class='bx bxs-envelope'></i> Email Link
        </div>

        <div class="left-inner">
            <div class="left-icon">
                <i class='bx bx-key'></i>
            </div>

            <h1>Reset Your<br>Password</h1>

            <div class="left-divider"></div>

            <p>No worries! Just follow these<br>simple steps to regain access.</p>

            <ul class="steps">
                <li>
                    <span class="step-num">1</span>
                    Enter the email address linked to your account.
                </li>
                <li>
                    <span class="step-num">2</span>
                    Check your inbox for the password reset link.
                </li>
                <li>
                    <span class="step-num">3</span>
                    Click the link and set a new password.
                </li>
            </ul>

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
                <div class="alert-success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
            @error('email')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
        </div>

        <label class="input-label">Email Address</label>
        <div class="input-wrap">
            <i class='bx bxs-envelope input-icon'></i>
            <input type="email" name="email" placeholder="Enter your registered email" required>
        </div>

        <div class="info-note">
            <i class='bx bxs-info-circle'></i>
            <span>Make sure to check your <strong>spam or junk folder</strong> if you don't see the email within a few minutes.</span>
        </div>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <input type="hidden" name="email" id="hiddenEmail">
            <button type="submit" class="submit-btn" onclick="syncEmail()">
                <i class='bx bx-send'></i> Send Reset Link
            </button>
        </form>

    </div>
</div>

<script>
// Sync the visible email input into the hidden form field
function syncEmail() {
    const visible = document.querySelector('input[name="email"]:not([type="hidden"])');
    const hidden  = document.getElementById('hiddenEmail');
    if (visible && hidden) hidden.value = visible.value;
}
</script>

</body>
</html>