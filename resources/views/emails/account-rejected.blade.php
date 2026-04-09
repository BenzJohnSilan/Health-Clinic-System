<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Account Application Status</title>
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
                            <h2 style="margin:0;">Account Application Update</h2>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td style="padding:25px; color:#444;">

                            <p style="margin-top:0;">Dear <strong>{{ $user->first_name }}</strong>,</p>

                            <p>
                                Thank you for your interest in registering with our clinic. 
                                After careful review, we regret to inform you that your account application 
                                has been <strong style="color:#dc2626;">rejected</strong>.
                            </p>

                            <!-- INFO BOX -->
                            <table width="100%" cellpadding="10" cellspacing="0" style="background:#f1f5f9; border-radius:8px; margin:20px 0;">
                                <tr>
                                    <td>
                                        <p><strong>Full Name:</strong> {{ $user->first_name }} {{ $user->last_name }}</p>
                                        <p><strong>Email Address:</strong> {{ $user->email }}</p>
                                        <p><strong>Reason for Rejection:</strong> {{ $reason }}</p>
                                    </td>
                                </tr>
                            </table>

                            <p>
                                We encourage you to review the reason stated above and make the necessary corrections. 
                                You may submit a new application once the issue has been resolved.
                            </p>

                            <p>
                                If you believe this decision was made in error or require further clarification, 
                                please do not hesitate to contact our support team.
                            </p>

                            <p style="margin-bottom:0;">
                                Thank you for your understanding.
                            </p>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background:#f9fafb; padding:15px; text-align:center; font-size:13px; color:#777;">
                            <p style="margin:0;"><strong>Clinic Administration Team</strong></p>
                            <p style="margin:0;">This is an automated message. Please do not reply.</p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>