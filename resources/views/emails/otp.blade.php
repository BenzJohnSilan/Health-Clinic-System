<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Verification — ClinicRMS</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        background-color: #f0f2f8;
        padding: 40px 16px;
        color: #1a1a2e;
        -webkit-font-smoothing: antialiased;
    }

    .email-wrapper {
        max-width: 520px;
        margin: 0 auto;
    }

    .email-header {
        background: #6a11cb;
        border-radius: 16px 16px 0 0;
        padding: 32px 40px 28px;
        text-align: center;
    }

    .logo-wrap {
        display: inline-block;
        width: 52px;
        height: 52px;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.28);
        border-radius: 14px;
        margin-bottom: 14px;
        text-align: center;
        line-height: 52px;
        vertical-align: middle;
    }

    .logo-wrap svg {
        width: 26px;
        height: 26px;
        fill: white;
        vertical-align: middle;
    }

    .email-header h1 {
        font-size: 18px;
        font-weight: 700;
        color: #ffffff;
        letter-spacing: 0.2px;
        margin-bottom: 4px;
    }

    .email-header .tagline {
        font-size: 12px;
        color: rgba(255,255,255,0.65);
    }

    .email-body {
        background: #ffffff;
        padding: 32px 40px;
        border-left: 1px solid #e8e8e8;
        border-right: 1px solid #e8e8e8;
    }

    .greeting {
        font-size: 15px;
        font-weight: 600;
        color: #1a1a2e;
        margin-bottom: 8px;
    }

    .message {
        font-size: 13.5px;
        color: #555555;
        line-height: 1.75;
        margin-bottom: 26px;
    }

    .otp-section-label {
        font-size: 11px;
        font-weight: 600;
        color: #999999;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        text-align: center;
        margin-bottom: 10px;
    }

    .otp-box {
        border: 1.5px dashed #b07dff;
        border-radius: 14px;
        background: #f8f3ff;
        padding: 22px 16px 20px;
        margin-bottom: 20px;
        text-align: center;
    }

    /* TABLE-BASED OTP — works in Gmail */
    .otp-table {
        border-collapse: separate;
        border-spacing: 10px 0;
        margin: 0 auto 12px auto;
    }

    .otp-table td {
        width: 52px;
        height: 60px;
        background: #ffffff;
        border: 1.5px solid #d4b8ff;
        border-radius: 10px;
        text-align: center;
        vertical-align: middle;
        font-size: 28px;
        font-weight: 800;
        color: #6a11cb;
        font-family: 'Courier New', monospace;
        line-height: 60px;
        padding: 0;
    }

    .otp-hint {
        font-size: 11px;
        color: #a085cc;
        text-align: center;
    }

    .notice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 12px;
    }

    .notice-warn {
        background: #fffbeb;
        border: 1px solid #fde68a;
    }

    .notice-info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
    }

    .notice-icon {
        font-size: 15px;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .notice p {
        font-size: 12.5px;
        line-height: 1.65;
    }

    .notice-warn p { color: #92400e; }
    .notice-info p { color: #1e40af; }
    .notice p strong { font-weight: 600; }

    .divider {
        border: none;
        border-top: 1px solid #f0f0f0;
        margin: 20px 0;
    }

    .closing {
        font-size: 13px;
        color: #666666;
        line-height: 1.75;
    }

    .email-footer {
        background: #f9f6ff;
        border: 1px solid #e8d9ff;
        border-top: none;
        border-radius: 0 0 16px 16px;
        padding: 18px 40px;
        text-align: center;
    }

    .footer-brand {
        font-size: 13px;
        font-weight: 600;
        color: #6a11cb;
        margin-bottom: 8px;
    }

    .footer-divider {
        border: none;
        border-top: 1px solid #e8d9ff;
        margin: 8px 0;
    }

    .email-footer p {
        font-size: 11.5px;
        color: #aaaaaa;
        line-height: 1.6;
        margin-bottom: 3px;
    }

    .outer-note {
        text-align: center;
        margin-top: 16px;
        font-size: 11.5px;
        color: #bbbbbb;
    }
</style>
</head>
<body>

<div class="email-wrapper">

    <!-- HEADER -->
    <div class="email-header">
        <div class="logo-wrap">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/>
            </svg>
        </div>
        <h1>ClinicRMS</h1>
        <p class="tagline">Health Clinic Record Management System</p>
    </div>

    <!-- BODY -->
    <div class="email-body">

        <p class="greeting">Hello, {{ $firstName }}! 👋</p>

        <p class="message">
            Thank you for registering with ClinicRMS. To complete your email verification,
            please use the one-time password below. Do not share this code with anyone.
        </p>

        <!-- OTP CODE -->
        <p class="otp-section-label">Your verification code</p>
        <div class="otp-box">
            <!-- TABLE-BASED layout for email client compatibility -->
            <table class="otp-table" role="presentation">
                <tr>
                    @foreach(str_split($otp) as $digit)
                    <td>{{ $digit }}</td>
                    @endforeach
                </tr>
            </table>
            <p class="otp-hint">Enter this code on the verification page</p>
        </div>

        <!-- EXPIRY NOTICE -->
        <div class="notice notice-warn">
            <span class="notice-icon">⏱</span>
            <p>This code expires in <strong>10 minutes</strong>. If it expires, you can request a new one from the verification page.</p>
        </div>

        <!-- SECURITY NOTICE -->
        <div class="notice notice-info">
            <span class="notice-icon">🔒</span>
            <p>If you did not create an account with ClinicRMS, please <strong>ignore this email</strong>. No action is required.</p>
        </div>

        <hr class="divider">

        <p class="closing">
            After verifying your email, your account will be reviewed by our admin team
            before you can log in. You will be notified via email once your account has been approved.
        </p>

    </div>

    <!-- FOOTER -->
    <div class="email-footer">
        <p class="footer-brand">ClinicRMS — Health Clinic Record Management System</p>
        <hr class="footer-divider">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>© {{ date('Y') }} ClinicRMS. All rights reserved.</p>
    </div>

</div>

<p class="outer-note">
    You're receiving this because you registered at ClinicRMS.
</p>

</body>
</html>