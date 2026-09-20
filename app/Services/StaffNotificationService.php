<?php

namespace App\Services;

use App\Models\AppointmentStatusEvent;
use App\Models\StaffNotificationRead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the Staff notification feed (appointment lifecycle events) and
 * cross-references it against the `staff_notification_reads` table to
 * decide read/unread state.
 *
 * Architecture mirrors PatientNotificationService on purpose (same
 * derive-on-the-fly + persisted-read-state approach), but this service is
 * entirely independent:
 *
 *  - Reads/writes `staff_notification_reads`, NEVER `patient_notification_reads`.
 *  - Notification keys are prefixed `staff_` and are never shared with the
 *    Patient notification keys, even though both sources ultimately read
 *    from the same underlying `appointment_status_events` table.
 *
 * IMPORTANT: notifications themselves are NOT stored in the database —
 * they are derived on the fly from `appointment_status_events` (the
 * existing append-only appointment history). Only the READ STATE is
 * persisted, keyed by a stable "notification_key" per event so a new
 * status change always produces a new, unread notification while
 * previously-read ones stay read.
 */
class StaffNotificationService
{
    /** How many rows to pull per source query (keeps things bounded/fast). */
    protected const PER_SOURCE_LIMIT = 15;

    /** How many notifications to keep after merging + sorting all sources. */
    protected const TOTAL_LIMIT = 30;

    /** Appointment status events that produce a Staff notification. */
    protected const RELEVANT_STATUSES = ['Pending', 'Approved', 'Rejected', 'Cancelled', 'Rescheduled'];

    /**
     * Build the full notification list for this staff member, each item
     * annotated with an accurate 'unread' flag from the database.
     */
    public function build(User $staff): Collection
    {
        $items = $this->rawNotifications();

        $readKeys = StaffNotificationRead::where('user_id', $staff->id)
            ->whereIn('notification_key', $items->pluck('key'))
            ->pluck('notification_key')
            ->flip(); // key => index, for O(1) isset() lookups

        return $items->map(function ($item) use ($readKeys) {
            $item['unread'] = !isset($readKeys[$item['key']]);
            return $item;
        })->values();
    }

    /**
     * Unread count for the header badge (bounded by the same history
     * window as the panel — consistent with what staff can see).
     */
    public function unreadCount(User $staff): int
    {
        return $this->build($staff)->where('unread', true)->count();
    }

    /**
     * Mark a single notification as read for this staff member. Only
     * succeeds if the key corresponds to a real, currently-generated
     * notification — never trusts the key blindly, and never allows one
     * staff member to touch another staff member's read state.
     */
    public function markRead(User $staff, string $key): bool
    {
        $validKeys = $this->rawNotifications()->pluck('key');

        if (!$validKeys->contains($key)) {
            return false;
        }

        StaffNotificationRead::firstOrCreate(
            ['user_id' => $staff->id, 'notification_key' => $key],
            ['read_at' => now()]
        );

        return true;
    }

    /**
     * Mark every currently-unread notification for this staff member as read.
     */
    public function markAllRead(User $staff): int
    {
        $items = $this->build($staff)->where('unread', true);

        $now = now();

        foreach ($items as $item) {
            StaffNotificationRead::firstOrCreate(
                ['user_id' => $staff->id, 'notification_key' => $item['key']],
                ['read_at' => $now]
            );
        }

        return $items->count();
    }

    // =========================================================================
    // SOURCES — derive the raw (unread-state-agnostic) notification list
    // =========================================================================

    protected function rawNotifications(): Collection
    {
        $items = $this->appointmentStatusNotifications();

        return $items
            ->sortByDesc('timestamp')
            ->take(self::TOTAL_LIMIT)
            ->values();
    }

    /**
     * Appointment lifecycle events: New/Pending, Approved, Rejected,
     * Cancelled, Rescheduled — for ALL appointments (Staff manage every
     * patient's appointments, unlike Patient notifications which are
     * scoped to a single patient_id).
     *
     * Reads from `appointment_status_events` (the same append-only history
     * the Patient notification system uses) rather than the live
     * `appointments.status` column, so an OLD status notification (e.g.
     * Approved) stays correctly marked as read even after the appointment
     * later moves to a new status (e.g. Cancelled).
     *
     * Key includes the event's own ID (`appointment_status_events.id`),
     * which is already unique per status change — so the same appointment
     * having multiple status changes always produces distinct,
     * independently-readable notifications.
     */
    protected function appointmentStatusNotifications(): Collection
    {
        return AppointmentStatusEvent::whereIn('status', self::RELEVANT_STATUSES)
            ->with(['appointment.patient', 'appointment.walkinPatient'])
            // Tie-break by event id (not just occurred_at) — two status
            // changes can land in the same second, and the most recently
            // RECORDED event must still sort first and deterministically,
            // both for display order and for tests/consumers that inspect
            // "the latest notification" for an appointment.
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->take(self::PER_SOURCE_LIMIT)
            ->get()
            ->filter(fn ($event) => $event->appointment !== null)
            ->map(function ($event) {
                $app = $event->appointment;
                $patientName = $this->resolvePatientName($app);

                [$icon, $iconClass, $label, $labelClass, $title, $message] = match ($event->status) {
                    'Pending' => [
                        'bx-calendar-plus', 'notif-item-icon--new', 'New', 'notif-label--new',
                        'New Appointment Request',
                        "{$patientName} submitted an appointment request.",
                    ],
                    'Approved' => [
                        'bx-check-circle', 'notif-item-icon--approved', 'Approved', 'notif-label--approved',
                        'Appointment Approved',
                        "{$patientName}'s appointment has been approved.",
                    ],
                    'Rejected' => [
                        'bx-x-circle', 'notif-item-icon--rejected', 'Rejected', 'notif-label--rejected',
                        'Appointment Rejected',
                        "{$patientName}'s appointment has been rejected.",
                    ],
                    'Cancelled' => [
                        'bx-minus-circle', 'notif-item-icon--cancelled', 'Cancelled', 'notif-label--cancelled',
                        'Appointment Cancelled',
                        "{$patientName}'s appointment has been cancelled.",
                    ],
                    'Rescheduled' => [
                        'bx-calendar-edit', 'notif-item-icon--rescheduled', 'Rescheduled', 'notif-label--rescheduled',
                        'Appointment Rescheduled',
                        "{$patientName}'s appointment has been rescheduled.",
                    ],
                    default => [
                        'bx-info-circle', '', $event->status, '',
                        'Appointment Update',
                        "{$patientName}'s appointment status was updated.",
                    ],
                };

                return [
                    'key'        => "staff_appointment_{$app->id}_{$event->status}_{$event->id}",
                    'category'   => 'appointments',
                    'icon'       => $icon,
                    'icon_class' => $iconClass,
                    'label'      => $label,
                    'label_class'=> $labelClass,
                    'title'      => $title,
                    'message'    => $message,
                    'url'        => route('staff.appointments.show', $app->id),
                    'timestamp'  => $event->occurred_at,
                ];
            });
    }

    /**
     * Resolve a display name for the appointment's patient, supporting
     * both registered (users table) and walk-in (patients table) patients,
     * with a safe fallback so a missing relationship never causes an error.
     */
    protected function resolvePatientName($appointment): string
    {
        if ($appointment->patient) {
            return trim($appointment->patient->first_name . ' ' . $appointment->patient->last_name);
        }

        if ($appointment->walkinPatient) {
            return trim($appointment->walkinPatient->first_name . ' ' . $appointment->walkinPatient->last_name) . ' (Walk-in)';
        }

        return 'Unknown Patient';
    }
}
