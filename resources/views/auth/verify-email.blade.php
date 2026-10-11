<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email — ClinicRMS</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
{{-- Shared card size, panels, alerts and responsive rules (same as the Login and Forgot Password pages) --}}
<link rel="stylesheet" href="{{ asset('css/login.css') }}">
<link rel="stylesheet" href="{{ asset('css/auth-extras.css') }}">

<style>
/* ===== Verify-email-only pieces ===== */
/* tighter vertical rhythm so the card stays close to the Login card height */
.right { padding-top: 34px; padding-bottom: 34px; }
.right-header { margin-bottom: 16px; }
.messages { margin-bottom: 12px; }

/* Email box */
.email-box {
    background: var(--bg);
    border: 1.5px solid rgba(106,17,203,0.14);
    border-radius: 14px;
    padding: 10px 16px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 14px;
    min-width: 0;
}
.email-box i { font-size: 20px; color: var(--purple-dark); flex-shrink: 0; }
.email-box-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.email-box-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.email-box-value { font-size: 14px; font-weight: 500; color: var(--purple-dark); overflow-wrap: anywhere; }

/* OTP boxes: flexible so six always fit the available width */
.otp-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 10px;
    display: block;
}

.otp-group {
    display: flex;
    gap: clamp(6px, 2vw, 10px);
    justify-content: center;
    margin-bottom: 10px;
}

.otp-box {
    flex: 1 1 0;
    min-width: 0;
    max-width: 56px;
    height: 58px;
    padding: 0;
    border: 2px solid #e2e2e2;
    border-radius: 14px;
    text-align: center;
    font-size: 24px;
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
    font-size: 12.5px;
    color: var(--text-muted);
    text-align: center;
    margin-bottom: 14px;
}
.otp-timer span { color: var(--purple-dark); font-weight: 500; }

/* Buttons */
.btn-primary {
    width: 100%; min-height: 48px; padding: 14px;
    border-radius: 30px; border: none;
    background: linear-gradient(135deg, var(--purple-dark), var(--purple-light));
    color: white;
    font-family: 'DM Sans', sans-serif; font-size: 15px; font-weight: 500;
    cursor: pointer; transition: 0.3s;
    box-shadow: 0 6px 20px rgba(106,17,203,0.32);
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-primary:hover    { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(106,17,203,0.42); }
.btn-primary:active   { transform: translateY(0); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.btn-secondary {
    width: 100%; min-height: 46px; padding: 12px;
    border-radius: 30px;
    border: 1.5px solid rgba(106,17,203,0.3);
    background: white; color: var(--purple-dark);
    font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500;
    cursor: pointer; transition: 0.3s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-secondary:hover { background: rgba(106,17,203,0.05); }

/* overrides the larger login divider spacing */
.divider { margin: 10px 0; }

.secondary-action {
    margin-top: 12px;
    text-align: center;
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.6;
}
.secondary-action a {
    color: var(--purple-dark); font-weight: 500; text-decoration: none;
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 0;
}
.secondary-action a:hover { text-decoration: underline; }

@media (max-width: 1024px) {
    .right { padding-top: 32px; padding-bottom: 32px; }
}
@media (max-width: 768px) {
    .right { padding-top: 28px; padding-bottom: 30px; }
}
@media (max-width: 480px) {
    .otp-box { height: 52px; font-size: 22px; border-radius: 12px; }
    .email-box { padding: 10px 12px; }
    .btn-primary { font-size: 14px; padding: 13px; }
}
@media (max-width: 360px) {
    .otp-box { height: 48px; font-size: 20px; border-radius: 10px; }
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
        <div class="left-inner">
            <div class="left-icon">
                <i class='bx bx-lock-open-alt'></i>
            </div>

            <h1>Almost There!</h1>
            <div class="left-divider"></div>
            <p class="left-desc">Verify your email address to complete registration.</p>
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
                <div class="alert-success" data-auto-dismiss role="status"><i class='bx bx-check-circle'></i> {{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ session('error') }}</div>
            @endif
            @error('otp')
                <div class="alert-error"><i class='bx bx-error-circle'></i> {{ $message }}</div>
            @enderror
        </div>

        <!-- OTP Form -->
        <form method="POST" action="{{ route('verification.verify') }}" id="otpForm">
            @csrf

            <span class="otp-label" id="otpLabel">Enter 6-digit code</span>

            <div class="otp-group" role="group" aria-labelledby="otpLabel">
                @for($i = 1; $i <= 6; $i++)
                    <input class="otp-box" type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                           autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}"
                           aria-label="Digit {{ $i }} of 6">
                @endfor
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
// ===== OTP BOXES — auto-jump, backspace, arrows, paste / autofill =====
const boxes     = document.querySelectorAll('.otp-box');
const hidden    = document.getElementById('otpHidden');
const submitBtn = document.getElementById('submitBtn');

function syncOtp() {
    const val = [...boxes].map(b => b.value).join('');
    hidden.value  = val;
    submitBtn.disabled = val.length !== 6;
    boxes.forEach(b => b.classList.toggle('filled', b.value !== ''));
}

// Spread a string of digits across the boxes starting at `start`
function fillFrom(start, digits) {
    digits.split('').forEach((ch, j) => { if (boxes[start + j]) boxes[start + j].value = ch; });
    boxes[Math.min(start + digits.length, 5)].focus();
    syncOtp();
}

boxes.forEach((box, i) => {
    box.addEventListener('input', function () {
        const digits = this.value.replace(/\D/g, '');
        // More than one character at once = SMS autofill / keyboard suggestion
        if (digits.length > 1) { this.value = ''; fillFrom(i, digits.slice(0, 6 - i)); return; }
        this.value = digits;
        if (this.value && i < 5) boxes[i + 1].focus();
        syncOtp();
    });

    box.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && i > 0) {
            boxes[i - 1].focus();
        } else if (e.key === 'ArrowLeft' && i > 0) {
            e.preventDefault(); boxes[i - 1].focus();
        } else if (e.key === 'ArrowRight' && i < 5) {
            e.preventDefault(); boxes[i + 1].focus();
        }
    });

    box.addEventListener('focus', function () { this.select(); });

    box.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        if (!pasted) return;
        // A full code always fills from the first box; a partial one from this box
        fillFrom(pasted.length === 6 ? 0 : i, pasted.slice(0, pasted.length === 6 ? 6 : 6 - i));
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

// Auto-hide success messages (e.g. "Account created successfully!") after a few seconds.
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