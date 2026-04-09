<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Appointment Status Update</title>
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
                            <h2 style="margin:0;">Clinic Appointment Update</h2>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td style="padding:25px; color:#444;">

                            <p style="margin-top:0;">Dear <strong>{{ $appointment->patient->first_name }}</strong>,</p>

                            <p>
                                We would like to inform you that your appointment request has been 
                                <strong style="
                                    color: {{ $status == 'Approved' ? '#16a34a' : '#dc2626' }};
                                ">
                                    {{ $status }}
                                </strong>.
                            </p>

                            <!-- INFO BOX -->
                            <table width="100%" cellpadding="10" cellspacing="0" style="background:#f1f5f9; border-radius:8px; margin:20px 0;">
                                <tr>
                                    <td>
                                        <p><strong>Doctor:</strong> {{ $appointment->doctor->first_name }} {{ $appointment->doctor->last_name }}</p>
                                        <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('F j, Y') }}</p>
                                        <p><strong>Time:</strong> {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') }}</p>

                                        @if($status == 'Rejected')
                                            <p><strong>Reason:</strong> {{ $appointment->reason }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if($status == 'Approved')
                            <p>
                                Please arrive at least <strong>10–15 minutes early</strong> before your scheduled time. 
                                If you need to reschedule or cancel, kindly inform us ahead of time.
                            </p>
                            @endif

                            @if($status == 'Rejected')
                            <p>
                                You may request a new appointment after addressing the concern mentioned above. 
                                If you need assistance, feel free to contact our clinic.
                            </p>
                            @endif

                            <p style="margin-bottom:0;">
                                Thank you for choosing our clinic.
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