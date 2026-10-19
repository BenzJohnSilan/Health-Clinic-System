<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $replySubject }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; background:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

                    <tr>
                        <td style="background:#6a0dad; padding:22px; text-align:center; color:#ffffff;">
                            <h2 style="margin:0; font-size:20px;">{{ $clinicName }}</h2>
                            <p style="margin:6px 0 0; font-size:13px; opacity:.9;">Reply to your inquiry</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px; color:#333; font-size:15px; line-height:1.7;">

                            <p style="margin:0 0 14px;">Hello {{ $contact->first_name }},</p>

                            <div>{!! nl2br(e($replyBody)) !!}</div>

                            <p style="margin:24px 0 0;">
                                Regards,<br>
                                <strong>{{ $clinicName }}</strong>
                                @if($clinicPhone)<br><span style="color:#6b7280; font-size:13px;">{{ $clinicPhone }}</span>@endif
                                @if($clinicEmail)<br><span style="color:#6b7280; font-size:13px;">{{ $clinicEmail }}</span>@endif
                            </p>

                            <p style="margin:26px 0 6px; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:.4px;">
                                Your original message &middot; {{ $contact->created_at->format('F j, Y \a\t g:i A') }}
                            </p>
                            <div style="background:#f9f5ff; border-left:4px solid #6a0dad; border-radius:6px; padding:12px 16px; font-size:13px; line-height:1.6; color:#555;">
                                {!! nl2br(e($contact->message)) !!}
                            </div>

                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f9f5ff; padding:14px; text-align:center; font-size:12px; color:#8a8a8a;">
                            You can reply to this email to continue the conversation.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
