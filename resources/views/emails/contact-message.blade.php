<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Contact Inquiry</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

                    <tr>
                        <td style="background:#6a0dad; padding:20px; text-align:center; color:#ffffff;">
                            <h2 style="margin:0;">New Contact Inquiry</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:25px; color:#444;">

                            <p style="margin-top:0;">Someone sent an inquiry through the Contact Us form on your website.</p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; border-collapse:collapse;">
                                <tr>
                                    <td style="padding:8px 0; width:150px; color:#6b7280;">Name</td>
                                    <td style="padding:8px 0;"><strong>{{ $contact->full_name }}</strong></td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; color:#6b7280;">Email</td>
                                    <td style="padding:8px 0;">{{ $contact->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; color:#6b7280;">Contact Number</td>
                                    <td style="padding:8px 0;">{{ $contact->contact_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0; color:#6b7280;">Date &amp; Time</td>
                                    <td style="padding:8px 0;">{{ $contact->created_at->format('F j, Y \a\t g:i A') }}</td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 6px; color:#6b7280; font-size:14px;">Message</p>
                            <div style="background:#f9f5ff; border-left:4px solid #6a0dad; border-radius:6px; padding:14px 16px; font-size:14px; line-height:1.6; color:#333;">
                                {!! nl2br(e($contact->message)) !!}
                            </div>

                            <p style="margin:22px 0 0; font-size:13px; color:#6b7280;">
                                You can reply directly to this email &mdash; your reply will go to the sender.
                            </p>

                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f9f5ff; padding:14px; text-align:center; font-size:12px; color:#8a8a8a;">
                            Automated notification from the Clinic Record Management System.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
