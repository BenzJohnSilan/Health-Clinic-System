<?php

namespace App\Services;

use App\Models\DoctorScheduleException;
use App\Models\DoctorWeeklySchedule;
use Carbon\Carbon;

/**
 * Doctor availability rules — the single source of truth shared between
 * the doctor's own "My Schedule" page and patient appointment booking.
 *
 * BUSINESS RULES (enforced strictly, no silent fallbacks):
 *   1. Doctor has no weekly schedule configured at all -> UNAVAILABLE.
 *   2. No schedule row exists for the specific day of week -> UNAVAILABLE
 *      on that day.
 *   3. A day's status is "Unavailable" -> booking blocked that day.
 *   4. A day is "Available" but has zero configured time slots -> booking
 *      blocked that day.
 *   5. Patients may only book inside a configured time slot.
 *   6. Schedule exceptions override the weekly schedule entirely:
 *        - "Unavailable" exception blocks the whole date.
 *        - "Custom Hours" exception only allows booking inside that
 *          specific time range (the weekly schedule is ignored for that
 *          date).
 */
class DoctorAvailabilityService
{
    /**
     * Determine whether a doctor can be booked at the given date/time.
     * $time accepts "H:i" or "H:i:s". Appointment slots are assumed 30 mins.
     *
     * @return array{0: bool, 1: ?string} [isAvailable, reasonIfNot]
     */
    public static function isAvailable(int $doctorId, string $date, string $time, int $slotMinutes = 30): array
    {
        try {
            $date = Carbon::parse($date)->startOfDay();
            $time = Carbon::createFromFormat('H:i', substr($time, 0, 5));
        } catch (\Exception $e) {
            return [false, 'The selected date or time is invalid.'];
        }

        // Rule 1: the doctor must have configured a weekly schedule at all.
        // A doctor with no rows in doctor_weekly_schedules is NEVER
        // bookable — the "Weekly Availability" page shows every day as
        // Unavailable in that state, so booking must match.
        if (!DoctorWeeklySchedule::where('doctor_id', $doctorId)->exists()) {
            return [false, 'This doctor has not set up a weekly schedule yet and cannot be booked.'];
        }

        // Rule 6: schedule exceptions override the weekly schedule.
        $exception = DoctorScheduleException::where('doctor_id', $doctorId)
            ->whereDate('exception_date', $date->toDateString())
            ->first();

        if ($exception) {
            if ($exception->type === 'Unavailable') {
                return [false, 'The doctor is unavailable on the selected date.'];
            }

            // Custom Hours — only that range is bookable for this date.
            if (
                $exception->start_time
                && $exception->end_time
                && self::timeWithinRange($time, $exception->start_time, $exception->end_time, $slotMinutes)
            ) {
                return [true, null];
            }

            return [false, "The selected time is outside the doctor's custom hours for this date."];
        }

        // Rules 2-5: fall back to the weekly schedule for this day of week.
        $dayName = $date->format('l'); // Monday..Sunday

        $weekly = DoctorWeeklySchedule::with('slots')
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $dayName)
            ->first();

        // Rule 2: no row for this specific day -> unavailable.
        if (!$weekly) {
            return [false, "The doctor has no schedule configured for {$dayName} and cannot be booked that day."];
        }

        // Rule 3: day explicitly marked Unavailable.
        if ($weekly->status !== 'Available') {
            return [false, "The doctor is unavailable on {$dayName}."];
        }

        // Rule 4: Available but no time slots configured.
        if ($weekly->slots->isEmpty()) {
            return [false, "The doctor has no available time slots configured for {$dayName}."];
        }

        // Rule 5: the requested time must fit inside a configured slot.
        foreach ($weekly->slots as $slot) {
            if (self::timeWithinRange($time, $slot->start_time, $slot->end_time, $slotMinutes)) {
                return [true, null];
            }
        }

        return [false, "The selected time is outside the doctor's available hours for {$dayName}."];
    }

    /**
     * Whether a full [time, time + slotMinutes] appointment fits inside
     * a [start, end] range. $start/$end may be "H:i:s" or "H:i" strings.
     */
    private static function timeWithinRange(Carbon $time, string $start, string $end, int $slotMinutes): bool
    {
        $slotStart = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $slotEnd   = Carbon::createFromFormat('H:i', substr($end, 0, 5));
        $apptEnd   = $time->copy()->addMinutes($slotMinutes);

        return $time->greaterThanOrEqualTo($slotStart) && $apptEnd->lessThanOrEqualTo($slotEnd);
    }
}
