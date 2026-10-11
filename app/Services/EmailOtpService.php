<?php

namespace App\Services;

use App\Models\User;

/**
 * Issues and verifies the 6-digit email OTP used during patient registration.
 *
 * Only a keyed hash (HMAC-SHA256 with the app key) of the code is stored in
 * users.email_otp — never the code itself. A plain bcrypt hash would be a poor
 * fit here: a 6-digit code has only 1,000,000 possibilities, so an unkeyed or
 * low-cost hash could be brute-forced offline in seconds if the table leaked.
 * Keying it with APP_KEY (and binding it to the user id) means a database
 * leak alone is not enough.
 */
class EmailOtpService
{
    public const TTL_MINUTES  = 10;
    public const MAX_ATTEMPTS = 5;

    public const VERIFIED = 'verified';
    public const INVALID  = 'invalid';
    public const EXPIRED  = 'expired';
    public const LOCKED   = 'locked';

    /**
     * Generate a new code, store its hash + expiry, and return the raw code
     * (to be emailed — it is never persisted or logged).
     */
    public function issue(User $user): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'email_otp'      => $this->hash($user, $otp),
            'otp_expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'otp_attempts'   => 0,
        ])->save();

        return $otp;
    }

    /**
     * Check a submitted code. On success the email is marked verified and the
     * code is destroyed so it can never be reused.
     */
    public function verify(User $user, string $submitted): string
    {
        if (!$user->email_otp || !$user->otp_expires_at || now()->isAfter($user->otp_expires_at)) {
            $this->clear($user);

            return self::EXPIRED;
        }

        if (hash_equals((string) $user->email_otp, $this->hash($user, $submitted))) {
            $user->forceFill([
                'email_verified_at' => now(),
                'email_otp'         => null,
                'otp_expires_at'    => null,
                'otp_attempts'      => 0,
            ])->save();

            return self::VERIFIED;
        }

        $user->increment('otp_attempts');

        if ((int) $user->otp_attempts >= self::MAX_ATTEMPTS) {
            $this->clear($user);

            return self::LOCKED;
        }

        return self::INVALID;
    }

    /**
     * Invalidate whatever code is currently stored.
     */
    public function clear(User $user): void
    {
        if ($user->email_otp === null && $user->otp_expires_at === null && (int) $user->otp_attempts === 0) {
            return;
        }

        $user->forceFill([
            'email_otp'      => null,
            'otp_expires_at' => null,
            'otp_attempts'   => 0,
        ])->save();
    }

    private function hash(User $user, string $otp): string
    {
        return hash_hmac('sha256', $user->getKey() . '|' . $otp, (string) config('app.key'));
    }
}
