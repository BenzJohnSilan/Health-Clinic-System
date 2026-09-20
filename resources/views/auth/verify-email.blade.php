<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email — ClinicRMS</title>
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
    position: relative;
}

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
    0%, 100% { transform: translate(0,0) scale(1); }
    33%       { transform: translate(30px,-40px) scale(1.06); }
    66%       { transform: translate(-20px,25px) scale(0.95); }
}

.wrapper {
    position: relative;
    z-index: 1;
    display: flex;
    width: 980px;
    min-height: 580px;
    background: var(--white);
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(106,17,203,0.15), 0 8px 24px rgba(0,0,0,0.06);
    animation: fadeUp 0.7s ease forwards;
    opacity: 0;
    transform: translateY(30px);
}

@keyframes fadeUp {
    to { opacity: 1; transform: translateY(0); }
}

/* ===== LEFT ===== */
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
.left-desc {
    font-size: 14px;
    opacity: 0.8;
    line-height: 1.7;
    margin-bottom: 24px;
    font-weight: 300;
}
.left-divider {
    width: 40px; height: 2px;
    background: rgba(255,255,255,0.4);
    border-radius: 2px;
    margin: 0 auto 24px;
}

.steps { display: flex; flex-direction: column; gap: 10px; width: 100%; }

.step {
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 14px;
    padding: 11px 16px;
    text-align: left;
    backdrop-filter: blur(4px);
}
.step.faded {
    opacity: 0.45;
    background: rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.08);
}
.step-num {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 500; flex-shrink: 0;
}
.step-num.done { background: rgba(255,255,255,0.9); color: var(--purple-dark); }
.step-num.active { border: 1.5px solid rgba(255,255,255,0.75); background: rgba(255,255,255,0.12); }
.step-text { font-size: 12.5px; font-weight: 400; opacity: 0.92; line-height: 1.4; }
.step-text strong { display: block; font-weight: 500; margin-bottom: 1px; font-size: 13px; }

.left-badge {
    position: absolute;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 30px;
    padding: 6px 14px;
    font-size: 11px; font-weight: 500;
    display: flex; align-items: center; gap: 6px;
    animation: floatBadge 6s ease-in-out infinite;
}
.left-badge-1 { top: 12%; left: 8%; }
.left-badge-2 { bottom: 12%; right: 6%; animation-delay: -3s; }
@keyframes floatBadge {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-8px); }
}

/* ===== RIGHT ===== */
.right {
    width: 54%;
    padding: 48px 52px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: var(--white);
}

.right-header { margin-bottom: 20px; }
.right-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    color: var(--text-dark);
    font-weight: 700;
    margin-bottom: 6px;
}
.right-header p { font-size: 14px; color: var(--text-muted); font-weight: 300; }

/* Email box */
.email-box {
    background: var(--bg);
    border: 1.5px solid rgba(106,17,203,0.14);
    border-radius: 14px;
    padding: 13px 18px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 16px;
}
.email-box i { font-size: 20px; color: var(--purple-dark); flex-shrink: 0; }
.email-box-text { display: flex; flex-direction: column; gap: 2px; }
.email-box-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.email-box-value { font-size: 14px; font-weight: 500; color: var(--purple-dark); }

/* OTP Boxes */
.otp-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 10px;
    display: block;
}

.otp-group {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 6px;
}

.otp-box {
    width: 54px; height: 60px;
    border: 2px solid #e2e2e2;
    border-radius: 14px;
    text-align: center;
    font-size: 26px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    color: var(--purple-dark);
    background: var(--bg);
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    outline: none;
    caret-color: transparent;
}
.otp-box:focus {
    border-color: var(--purple-dark);
    background: white;
    box-shadow: 0 0 0 4px rgba(106,17,203,0.1);
}
.otp-box.filled {
    border-color: var(--purple-light);
    background: rgba(160,68,255,0.06);
}

.otp-timer {
    font-size: 12px;
    color: var(--text-muted);
    text-align: center;
    margin-bottom: 16px;
}
.otp-timer span { color: var(--purple-dark); font-weight: 500; }

/* Notices */
.approval-notice {
    font-size: 13px; color: #1e40af; line-height: 1.65;
    margin-bottom: 16px; padding: 11px 14px;
    background: #eff6ff; border: 1px solid #bfdbfe;
    border-radius: 12px; display: flex; gap: 10px; align-items: flex-start;
}
.approval-notice i { font-size: 16px; color: #2563eb; margin-top: 1px; flex-shrink: 0; }

/* Messages */
.messages { margin-bottom: 12px; }
.alert-error, .alert-success {
    padding: 10px 16px; border-radius: 12px;
    font-size: 13px; display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
}
.alert-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

/* Buttons */
.btn-primary {
    width: 100%; padding: 14px;
    border-radius: 30px; border: none;
    background: linear-gradient(135deg, var(--purple-dark), var(--purple-light));
    color: white;
    font-family: 'DM Sans', sans-serif; font-size: 15px; font-weight: 500;
    cursor: pointer; transition: 0.3s;
    box-shadow: 0 6px 20px rgba(106,17,203,0.32);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-bottom: 12px;
}
.btn-primary:hover    { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(106,17,203,0.42); }
.btn-primary:active   { transform: translateY(0); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.btn-secondary {
    width: 100%; padding: 13px;
    border-radius: 30px;
    border: 1.5px solid rgba(106,17,203,0.3);
    background: white; color: var(--purple-dark);
    font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500;
    cursor: pointer; transition: 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-bottom: 16px;
}
.btn-secondary:hover { background: rgba(106,17,203,0.05); }

.divider {
    display: flex; align-items: center; gap: 12px;
    margin: 0 0 14px; color: #ccc; font-size: 12px;
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e8e8e8; }

.secondary-action { text-align: center; font-size: 13px; color: var(--text-muted); }
.secondary-action a {
    color: var(--purple-dark); font-weight: 500; text-decoration: none;
    display: inline-flex; align-items: center; gap: 5px;
}
.secondary-action a:hover { text-decoration: underline; }

@media (max-width: 768px) {
    .wrapper { flex-direction: column; width: 100%; margin: 16px; border-radius: 20px; }
    .left { width: 100%; padding: 44px 32px; }
    .left-badge { display: none; }
    .right { width: 100%; padding: 36px 24px; }
    .otp-box { width: 44px; height: 52px; font-size: 22px; }
}
</style>
</head>

<body>

<div class="bg-blob bg-blob-1"></div>
<div class="bg-blob bg-blob-2"></div>
<div class="bg-blob bg-blob-3"></div>

<div class="wrapper">

    <!-- ===== LEFT ===== -->
    <div class="left">

        <div class="left-badge left-badge-1">
            <i class='bx bxs-shield-check'></i> Secure
        </div>
        <div class="left-badge left-badge-2">
            <i class='bx bxs-lock-alt'></i> OTP Verified
        </div>

        <div class="left-inner">
            <div class="left-icon">
                <i class='bx bx-lock-open-alt'></i>
            </div>

            <h1>Almost There!</h1>
            <div class="left-divider"></div>
            <p class="left-desc">Follow these steps to complete your patient account registration.</p>

            <div class="steps">
                <div class="step">
                    <div class="step-num done"><i class='bx bx-check'></i></div>
                    <div class="step-text">
                        <strong>Create Account</strong>
                        Registration complete
                    </div>
                </div>
                <div class="step">
                    <div class="step-num active">2</div>
                    <div class="step-text">
                        <strong>Verify Your Email</strong>
                        Enter the OTP sent to your inbox
                    </div>
                </div>
                <div class="step faded">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        <strong>Wait for Admin Approval</strong>
                        Admin will review your account
                    </div>
                </div>
                <div class="step faded">
                    <div class="step-num">4</div>
                    <div class="step-text">
                        <strong>Login to Your Account</strong>
                        You'll be notified once approved
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT ===== -->
    <div class="right">

        <div class="right-header">
            <h2>Verify your email 🔐</h2>
            <p>Enter the 6-digit code we sent to your email address.</p>
        </div>

        <div class="email-box">
            <i class='bx bxs-envelope'></i>
            <div class="email-box-text">
                <span class="email-box-label">OTP sent to</span>
                <span class="email-box-value">{{ auth()->user()->email }}</span>
            </div>
        </div>

        <!-- Messages -->
        <div class="messages">
            @if(session('success'))
                <div class="alert-success"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ session('error') }}</div>
            @endif
        </div>

        <!-- OTP Form -->
        <form method="POST" action="{{ route('verification.verify') }}" id="otpForm">
            @csrf

            <span class="otp-label">Enter 6-digit code</span>

            <div class="otp-group">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
                <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off">
            </div>

            <input type="hidden" name="otp" id="otpHidden">

            <!-- Countdown Timer -->
            <div class="otp-timer">
                Code expires in <span id="countdown">10:00</span>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn" disabled>
                <i class='bx bx-check-shield'></i> Verify Email
            </button>
        </form>

        <div class="divider">or</div>

        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="btn-secondary">
                <i class='bx bx-refresh'></i> Resend OTP
            </button>
        </form>

        <div class="approval-notice">
            <i class='bx bx-time-five'></i>
            After verifying your email, your account will be reviewed by an admin before you can log in. You will be notified via email once approved.
        </div>

        <div class="secondary-action">
            Wrong email address? &nbsp;
            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class='bx bx-log-out'></i> Sign out and try again
            </a>
        </div>

        <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
            @csrf
        </form>

    </div>
</div>

<script>
// ===== OTP BOXES — auto-jump, backspace, paste =====
const boxes     = document.querySelectorAll('.otp-box');
const hidden    = document.getElementById('otpHidden');
const submitBtn = document.getElementById('submitBtn');

function syncOtp() {
    const val = [...boxes].map(b => b.value).join('');
    hidden.value  = val;
    submitBtn.disabled = val.length !== 6;
    boxes.forEach(b => b.classList.toggle('filled', b.value !== ''));
}

boxes.forEach((box, i) => {
    box.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
        if (this.value && i < 5) boxes[i + 1].focus();
        syncOtp();
    });

    box.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && i > 0) {
            boxes[i - 1].focus();
        }
    });

    box.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, j) => { if (boxes[j]) boxes[j].value = ch; });
        boxes[Math.min(pasted.length, 5)].focus();
        syncOtp();
    });
});

// ===== COUNTDOWN TIMER (10 minutes) =====
@if(auth()->user()->otp_expires_at)
    const expiresAt  = new Date("{{ auth()->user()->otp_expires_at->toIso8601String() }}").getTime();
@else
    const expiresAt  = Date.now() + (10 * 60 * 1000);
@endif

const countdownEl = document.getElementById('countdown');

function updateCountdown() {
    const now  = Date.now();
    const diff = expiresAt - now;

    if (diff <= 0) {
        countdownEl.textContent = '00:00';
        countdownEl.style.color = '#ef4444';
        return;
    }

    const mins = Math.floor(diff / 60000);
    const secs = Math.floor((diff % 60000) / 1000);
    countdownEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

    if (diff < 60000) countdownEl.style.color = '#ef4444';

    setTimeout(updateCountdown, 1000);
}

updateCountdown();
</script>

</body>
</html>