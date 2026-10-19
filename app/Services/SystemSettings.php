<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single read/write point for Admin > System Settings.
 *
 * - Every key has a default (DEFAULTS) that reproduces the system's
 *   behavior from before this module existed, so a missing row, a missing
 *   table or an unavailable cache can never break the application.
 * - Reads are cached; every write calls forget() so changes apply at once.
 * - Secrets (SMS API token, mail password) are NEVER stored here; they stay
 *   in .env / config/services.php.
 */
class SystemSettings
{
    public const CACHE_KEY = 'system.settings.v1';
    public const LOGO_DIR  = 'clinic';

    /** Allowed idle-session timeouts in minutes (null = server default). */
    public const SESSION_LIFETIMES = [15, 30, 60, 120, 240, 480];

    /**
     * Appointment notification events an Admin can switch on/off per channel.
     * Account/security mail (OTP, password reset, account approval) is NOT
     * listed on purpose: those flows must always be delivered.
     *
     * @var array<string, array{label: string, channels: string[]}>
     */
    public const NOTIFICATION_EVENTS = [
        'approved'     => ['label' => 'Appointment approved',      'channels' => ['email', 'sms']],
        'rejected'     => ['label' => 'Appointment rejected',      'channels' => ['email', 'sms']],
        'cancelled'    => ['label' => 'Appointment cancelled',     'channels' => ['email', 'sms']],
        'rescheduled'  => ['label' => 'Appointment rescheduled',   'channels' => ['email', 'sms']],
        'lab_reviewed' => ['label' => 'Laboratory result reviewed', 'channels' => ['sms']],
    ];

    public const DEFAULTS = [
        // General
        'clinic_name'    => 'Clinic RMS',
        'clinic_logo'    => '',
        'clinic_address' => '',
        'clinic_phone'   => '',
        'clinic_email'   => '',

        // Appointments (defaults = the previously hardcoded schedule)
        'slot_minutes'             => '30',
        'opening_time'             => '09:00',
        'closing_time'             => '17:00',
        'lunch_enabled'            => '1',
        'lunch_start'              => '12:00',
        'lunch_end'                => '13:00',
        'booking_min_notice_hours' => '0',   // 0 = no minimum notice
        'booking_max_advance_days' => '0',   // 0 = no limit

        // Notifications (all on, as before)
        'notify_email_enabled'      => '1',
        'notify_sms_enabled'        => '1',
        'notify_email_approved'     => '1',
        'notify_email_rejected'     => '1',
        'notify_email_cancelled'    => '1',
        'notify_email_rescheduled'  => '1',
        'notify_sms_approved'       => '1',
        'notify_sms_rejected'       => '1',
        'notify_sms_cancelled'      => '1',
        'notify_sms_rescheduled'    => '1',
        'notify_sms_lab_reviewed'   => '1',

        // Security ('' = keep the server's SESSION_LIFETIME)
        'session_lifetime' => '',
    ];

    /** @return array<string, string> every key, stored value or default */
    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $values = self::DEFAULTS;

                foreach (SystemSetting::query()->pluck('value', 'key') as $key => $value) {
                    if (array_key_exists($key, $values)) {
                        $values[$key] = (string) $value;
                    }
                }

                return $values;
            });
        } catch (Throwable $e) {
            // Table not migrated yet, DB down, cache down... fall back safely.
            Log::debug('System settings unavailable, using defaults: ' . $e->getMessage());

            return self::DEFAULTS;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    /**
     * Persist several keys at once (unknown keys are ignored).
     *
     * @param array<string, scalar|null> $values
     */
    public static function set(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                if (!array_key_exists($key, self::DEFAULTS)) {
                    continue;
                }

                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }

                SystemSetting::updateOrCreate(['key' => $key], ['value' => (string) ($value ?? '')]);
            }
        });

        self::forget();
    }

    public static function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $e) {
            // A cache outage must never block an Admin save.
        }
    }

    // ------------------------------------------------------------------
    //  General
    // ------------------------------------------------------------------

    public static function clinicName(): string
    {
        $name = trim((string) self::get('clinic_name'));

        return $name !== '' ? $name : self::DEFAULTS['clinic_name'];
    }

    /** Signature appended to SMS messages: "- <clinic name>". */
    public static function smsSignature(): string
    {
        return '- ' . Str::limit(self::clinicName(), 40, '');
    }

    /** Public URL of the uploaded logo, or null (default icon is shown). */
    public static function logoUrl(): ?string
    {
        $path = (string) self::get('clinic_logo');

        if (!self::isManagedLogo($path)) {
            return null;
        }

        try {
            return Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** A stored logo path is only trusted if it is a plain file in clinic/. */
    public static function isManagedLogo(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^' . self::LOGO_DIR . '/[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp)$#', $path) === 1;
    }

    public static function deleteLogo(?string $path): void
    {
        if (self::isManagedLogo($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    // ------------------------------------------------------------------
    //  Notifications
    // ------------------------------------------------------------------

    /** Whether $event may be sent over $channel ('email'|'sms'). */
    public static function notificationEnabled(string $channel, string $event): bool
    {
        if (!in_array($channel, ['email', 'sms'], true)) {
            return false;
        }

        return self::bool("notify_{$channel}_enabled") && self::bool("notify_{$channel}_{$event}");
    }

    // ------------------------------------------------------------------
    //  Security
    // ------------------------------------------------------------------

    /** Configured idle timeout in minutes, or null to keep the server value. */
    public static function sessionLifetime(): ?int
    {
        $value = (int) self::get('session_lifetime');

        return in_array($value, self::SESSION_LIFETIMES, true) ? $value : null;
    }

    /**
     * Apply the saved timeout to Laravel's session config. Called from
     * AppServiceProvider::boot(), i.e. before StartSession resolves the
     * session driver, so the session store, its expiry check and the
     * cookie lifetime all use the saved value on the server side.
     */
    public static function applySessionLifetime(): void
    {
        // Remember what the server (.env) says, for display in the form.
        if (config('system.session_default_lifetime') === null) {
            config(['system.session_default_lifetime' => (int) config('session.lifetime')]);
        }

        if (($minutes = self::sessionLifetime()) !== null) {
            config(['session.lifetime' => $minutes]);
        }
    }

    public static function serverSessionLifetime(): int
    {
        return (int) (config('system.session_default_lifetime') ?? config('session.lifetime'));
    }
}
