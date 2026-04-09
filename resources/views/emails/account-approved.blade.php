<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Account Approved</title>
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
                            <h2 style="margin:0;">Account Approved</h2>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td style="padding:25px; color:#444;">

                            <p style="margin-top:0;">Dear <strong>{{ $user->first_name }}</strong>,</p>

                            <p>
                                We are pleased to inform you that your account application has been 
                                <strong style="color:#16a34a;">approved</strong>.
                            </p>

                            <p>
                                You may now access your account and begin using our system to manage your appointments 
                                and clinic records.
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
                                If you encounter any issues logging in or have any questions, 
                                please do not hesitate to contact our support team.
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