<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * The clinic's appointment time grid, driven by Admin > System Settings.
 *
 * One source of truth for: the time-slot dropdowns (patient, staff, admin),
 * server-side validation of a booked/rescheduled time, the appointment
 * length used by "No Show", and the patient booking window rules.
 *
 * Defaults (30-minute slots, 9:00 AM - 5:00 PM, lunch 12:00-1:00 PM) match
 * the values that used to be hardcoded. Stored values are re-validated here,
 * so a corrupted row can only ever fall back to those defaults.
 */
class ClinicSchedule
{
    public const DURATIONS = [15, 20, 30, 45, 60];

    /**
     * Sanitized schedule settings.
     *
     * @return array{slot: int, open: string, close: string, lunch: bool, lunch_start: string, lunch_end: string}
     */
    public static function config(): array
    {
        $d = SystemSettings::DEFAULTS;

        $slot  = SystemSettings::int('slot_minutes');
        $open  = (string) SystemSettings::get('opening_time');
        $close = (string) SystemSettings::get('closing_time');
        $lunch = SystemSettings::bool('lunch_enabled');
        $ls    = (string) SystemSettings::get('lunch_start');
        $le    = (string) SystemSettings::get('lunch_end');

        $valid = in_array($slot, self::DURATIONS, true)
            && self::isTime($open) && self::isTime($close) && $open < $close
            && (self::minutes($close) - self::minutes($open)) >= $slot
            && (!$lunch || (self::isTime($ls) && self::isTime($le) && $ls < $le && $ls >= $open && $le <= $close));

        if (!$valid) {
            return [
                'slot'        => (int) $d['slot_minutes'],
                'open'        => $d['opening_time'],
                'close'       => $d['closing_time'],
                'lunch'       => $d['lunch_enabled'] === '1',
                'lunch_start' => $d['lunch_start'],
                'lunch_end'   => $d['lunch_end'],
            ];
        }

        return [
            'slot' => $slot, 'open' => $open, 'close' => $close,
            'lunch' => $lunch, 'lunch_start' => $ls, 'lunch_end' => $le,
        ];
    }

    public static function slotMinutes(): int
    {
        return self::config()['slot'];
    }

    /**
     * Build the grid from raw values (also used to validate the Admin form).
     *
     * Each row: ['value' => 'H:i' start, 'label' => '9:00 AM – 9:30 AM', 'lunch' => bool].
     * Slots overlapping lunch collapse into ONE disabled "Lunch Break" row,
     * exactly like the old hardcoded dropdown.
     *
     * @return array<int, array{value: string, label: string, lunch: bool}>
     */
    public static function buildSlots(int $slot, string $open, string $close, bool $lunch, string $lunchStart, string $lunchEnd): array
    {
        $rows       = [];
        $lunchAdded = false;
        $lStart     = self::minutes($lunchStart);
        $lEnd       = self::minutes($lunchEnd);
        $end        = self::minutes($close);

        for ($start = self::minutes($open); $start + $slot <= $end; $start += $slot) {
            $slotEnd = $start + $slot;

            if ($lunch && $start < $lEnd && $slotEnd > $lStart) {
                if (!$lunchAdded) {
                    $rows[] = [
                        'value' => self::hhmm($start),
                        'label' => self::label($lStart) . ' – ' . self::label($lEnd) . ' (Lunch Break)',
                        'lunch' => true,
                    ];
                    $lunchAdded = true;
                }
                continue;
            }

            $rows[] = [
                'value' => self::hhmm($start),
                'label' => self::label($start) . ' – ' . self::label($slotEnd),
                'lunch' => false,
            ];
        }

        return $rows;
    }

    /** @return array<int, array{value: string, label: string, lunch: bool}> */
    public static function slots(): array
    {
        $c = self::config();

        return self::buildSlots($c['slot'], $c['open'], $c['close'], $c['lunch'], $c['lunch_start'], $c['lunch_end']);
    }

    /** @return string[] start times ("H:i") that can actually be booked */
    public static function bookableTimes(): array
    {
        return array_values(array_map(
            fn ($row) => $row['value'],
            array_filter(self::slots(), fn ($row) => !$row['lunch'])
        ));
    }

    /** Whether $time ("H:i" or "H:i:s") is the start of a bookable slot. */
    public static function isBookableTime(?string $time): bool
    {
        if (!is_string($time) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/D', $time)) {
            return false;
        }

        return in_array(substr($time, 0, 5), self::bookableTimes(), true);
    }

    public const INVALID_TIME_MESSAGE = 'Please choose a valid time slot within the clinic\'s booking hours (lunch break excluded).';

    /**
     * Extra rules for PATIENT self-booking only (staff/admin schedule
     * in person and are not limited by these): minimum notice and how far
     * ahead a booking may be. Returns an error message, or null if allowed.
     */
    public static function patientBookingError(Carbon $appointmentAt): ?string
    {
        $minHours = SystemSettings::int('booking_min_notice_hours');
        $maxDays  = SystemSettings::int('booking_max_advance_days');

        if ($minHours > 0 && $appointmentAt->lt(now()->addHours($minHours))) {
            return "Appointments must be booked at least {$minHours} hour" . ($minHours === 1 ? '' : 's') . ' in advance.';
        }

        if ($maxDays > 0 && $appointmentAt->gt(now()->addDays($maxDays)->endOfDay())) {
            return "Appointments can only be booked up to {$maxDays} day" . ($maxDays === 1 ? '' : 's') . ' in advance.';
        }

        return null;
    }

    /** Latest bookable date for the patient date picker, or null for no limit. */
    public static function patientMaxDate(): ?string
    {
        $maxDays = SystemSettings::int('booking_max_advance_days');

        return $maxDays > 0 ? now()->addDays($maxDays)->toDateString() : null;
    }

    // ------------------------------------------------------------------

    public static function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $value) === 1;
    }

    public static function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));

        return $h * 60 + $m;
    }

    private static function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private static function label(int $minutes): string
    {
        return Carbon::createFromTime(intdiv($minutes, 60), $minutes % 60)->format('g:i A');
    }
}
