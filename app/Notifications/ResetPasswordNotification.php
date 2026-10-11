<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded "reset your password" email.
 *
 * Replaces Laravel's default message. The reset link itself is still built
 * by the framework (signed token + email), so the flow does not change:
 * Forgot Password -> email link -> Reset Password page.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject('Reset Your Password — ClinicRMS')
            ->view('emails.reset-password', [
                'firstName'     => $notifiable->first_name ?? null,
                'resetUrl'      => $this->resetUrl($notifiable),
                'expireMinutes' => (int) config("auth.passwords.{$broker}.expire", 60),
            ]);
    }
}