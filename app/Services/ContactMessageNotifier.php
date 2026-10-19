<?php

namespace App\Services;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails a saved Contact Us inquiry to the clinic.
 *
 * The inquiry is always stored BEFORE this runs, and a failure here never
 * removes it: the outcome is recorded on the row (notified_at /
 * notification_error) so an Admin can see it and press "Resend notification".
 * Only a generic reason is stored/shown; the real exception goes to the log.
 */
class ContactMessageNotifier
{
    /**
     * Recipient: CONTACT_NOTIFICATION_EMAIL (.env) first, then the Official
     * email saved in Admin > System Settings.
     */
    public function recipient(): ?string
    {
        $candidates = [config('mail.contact_notification_email')];

        try {
            $candidates[] = SystemSetting::query()->where('key', 'clinic_email')->value('value');
        } catch (Throwable $e) {
            // settings table unavailable -> .env value only
        }

        foreach ($candidates as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }

    public function send(ContactMessage $contact): bool
    {
        $to = $this->recipient();

        if ($to === null) {
            Log::warning('Contact inquiry #' . $contact->id . ' saved but no notification email is configured.');
            $contact->forceFill(['notification_error' => 'No clinic notification email is configured.'])->save();

            return false;
        }

        try {
            Mail::to($to)->send(new ContactMessageReceived($contact));
        } catch (Throwable $e) {
            Log::error('Contact inquiry #' . $contact->id . ' notification email failed: ' . $e->getMessage());
            $contact->forceFill(['notification_error' => 'The email notification could not be sent.'])->save();

            return false;
        }

        $contact->forceFill(['notified_at' => now(), 'notification_error' => null])->save();

        return true;
    }
}
