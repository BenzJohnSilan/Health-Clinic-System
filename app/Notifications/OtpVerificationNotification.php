<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtpVerificationNotification extends Notification
{
    public function __construct(public string $otp) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Email Verification Code — ClinicRMS')
            ->view('emails.otp', [
                'otp'       => $this->otp,
                'firstName' => $notifiable->first_name,
            ]);
    }
}