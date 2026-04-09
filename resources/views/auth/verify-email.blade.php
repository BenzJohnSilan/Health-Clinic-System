<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f6f8;
        }

        .container {
            max-width: 600px;
            margin: 100px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }

        h2 {
            color: #4f46e5;
            margin-bottom: 20px;
        }

        p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .email-box {
            background: #eef2ff;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .email-box strong {
            color: #4f46e5;
        }

        .note {
            font-size: 13px;
            color: gray;
            margin-bottom: 20px;
        }

        button {
            padding: 10px 20px;
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #3730a3;
        }

        .success {
            color: green;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Verify Your Email</h2>

    <p>
        Thanks for registering! Before you can access your dashboard, please verify your email.
    </p>

    <div class="email-box">
        📧 Verification email sent to:<br>
        <strong>{{ auth()->user()->email }}</strong>
    </div>

    <p class="note">
        Didn't receive the email? Check your spam folder or click resend below.
    </p>

    @if (session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

    <form method="POST" action="{{ route('verification.resend') }}">
        @csrf
        <button type="submit">Resend Verification Email</button>
    </form>
</div>

</body>
</html>