<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Account Created</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
        <tr>
            <td align="center">

                <!-- CONTAINER -->
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

                    <!-- HEADER -->
                    <tr>
                        <td style="background:#6a0dad; padding:20px; text-align:center; color:#ffffff;">
                            <h2 style="margin:0;">Welcome to Our Clinic</h2>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td style="padding:25px; color:#444;">

                            <p style="margin-top:0;">Dear <strong>{{ $user->first_name }}</strong>,</p>

                            <p>
                                Your account has been successfully created. Below are your login details:
                            </p>

                            <!-- INFO BOX -->
                            <table width="100%" cellpadding="10" cellspacing="0" style="background:#f1f5f9; border-radius:8px; margin:20px 0;">
                                <tr>
                                    <td>
                                        <p><strong>Username:</strong> {{ $user->username }}</p>
                                        <p><strong>Email Address:</strong> {{ $user->email }}</p>
                                        <p><strong>Temporary Password:</strong> {{ $password }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p>
                                For security purposes, we strongly recommend that you change your password 
                                immediately after logging in.
                            </p>

                            <p>
                                Please verify your email address before accessing your account.
                            </p>

                            <!-- CTA BUTTON -->
                            <div style="text-align:center; margin:30px 0;">
                                <a href="{{ url('/login') }}" style="
                                    display:inline-block;
                                    padding:12px 24px;
                                    background:#6a0dad;
                                    color:#ffffff;
                                    text-decoration:none;
                                    border-radius:6px;
                                    font-size:14px;
                                ">
                                    Login to Your Account
                                </a>
                            </div>

                            <p>
                                If you did not request this account, please contact our support team immediately.
                            </p>

                            <p style="margin-bottom:0;">
                                Thank you for choosing our clinic.
                            </p>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background:#f9fafb; padding:15px; text-align:center; font-size:13px; color:#777;">
                            <p style="margin:0;"><strong>Clinic Administration Team</strong></p>
                            <p style="margin:0;">&copy; {{ date('Y') }} Health Clinic Record Management System</p>
                            <p style="margin:0;">This is an automated message. Please do not reply.</p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>