<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusEvent;
use App\Models\DoctorNotificationRead;
use App\Models\LaboratoryRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the Doctor notification feed (appointment lifecycle events for
 * THIS doctor's appointments + laboratory results awaiting THIS doctor's
 * review) and cross-references it against the `doctor_notification_reads`
 * table to decide read/unread state.
 *
 * Architecture mirrors StaffNotificationService / PatientNotificationService
 * on purpose (same derive-on-the-fly + persisted-read-state approach), but
 * this service is entirely independent:
 *
 *  - Reads/writes `doctor_notification_reads`, NEVER the admin/staff/patient
 *    read tables.
 *  - Notification keys are prefixed `doctor_` and are never shared with
 *    other roles' keys.
 *
 * SECURITY: every source query is filtered server-side by the authenticated
 * doctor's own user id (`appointments.doctor_id` / `laboratory_requests.doctor_id`).
 * A Doctor can never see, or mark as read, another Doctor's notification.
 *
 * IMPORTANT: notifications themselves are NOT stored in the database — they
 * are derived on the fly from `appointment_status_events` (the append-only
 * appointment history) and `laboratory_requests`. Only the READ STATE is
 * persisted, keyed by a stable "notification_key" per event so a new status
 * change always produces a new, unread notification while previously-read
 * ones stay read.
 */
class DoctorNotificationService
{
    /** How many rows to pull per source query (keeps things bounded/fast). */
    protected const PER_SOURCE_LIMIT = 15;

    /** How many notifications to keep after merging + sorting all sources. */
    protected const TOTAL_LIMIT = 30;

    /** Appointment status events that produce a Doctor notification. */
    protected const RELEVANT_STATUSES = [
        'Pending', 'Approved', 'Rejected', 'Cancelled', 'Rescheduled',
        'Checked In', 'In Progress', 'Completed',
    ];

    /** Statuses for which the Doctor consultation page is accessible. */
    protected const CONSULTATION_STATUSES = ['Checked In', 'In Progress', 'Completed'];

    /**
     * Build the full notification list for this doctor, each item
     * annotated with an accurate 'unread' flag from the database.
     */
    public function build(User $doctor): Collection
    {
        $items = $this->rawNotifications($doctor);

        $readKeys = DoctorNotificationRead::where('user_id', $doctor->id)
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
     * window as the panel — consistent with what the doctor can see).
     */
    public function unreadCount(User $doctor): int
    {
        return $this->build($doctor)->where('unread', true)->count();
    }

    /**
     * Mark a single notification as read for this doctor. Only succeeds if
     * the key corresponds to a real, currently-generated notification for
     * THIS doctor — never trusts the key blindly, so a key belonging to
     * another Doctor's appointment/lab is rejected.
     */
    public function markRead(User $doctor, string $key): bool
    {
        $validKeys = $this->rawNotifications($doctor)->pluck('key');

        if (!$validKeys->contains($key)) {
            return false;
        }

        DoctorNotificationRead::firstOrCreate(
            ['user_id' => $doctor->id, 'notification_key' => $key],
            ['read_at' => now()]
        );

        return true;
    }

    /**
     * Mark every currently-unread notification for this doctor as read.
     */
    public function markAllRead(User $doctor): int
    {
        $items = $this->build($doctor)->where('unread', true);

        $now = now();

        foreach ($items as $item) {
            DoctorNotificationRead::firstOrCreate(
                ['user_id' => $doctor->id, 'notification_key' => $item['key']],
                ['read_at' => $now]
            );
        }

        return $items->count();
    }

    // =========================================================================
    // SOURCES — derive the raw (unread-state-agnostic) notification list
    // =========================================================================

    protected function rawNotifications(User $doctor): Collection
    {
        $items = collect()
            ->concat($this->appointmentStatusNotifications($doctor))
            ->concat($this->laboratoryResultNotifications($doctor));

        return $items
            ->sortByDesc('timestamp')
            ->take(self::TOTAL_LIMIT)
            ->values();
    }

    /**
     * Appointment lifecycle events for appointments ASSIGNED TO THIS DOCTOR
     * only: New/Pending, Approved, Rejected, Cancelled, Rescheduled,
     * Checked In, In Progress, Completed.
     *
     * Reads from `appointment_status_events` (the same append-only history
     * Staff/Patient use) rather than the live `appointments.status`
     * column, so an OLD status notification stays correctly marked as read
     * even after the appointment later moves to a new status.
     *
     * The doctor filter is applied in the database query via
     * `appointments.doctor_id = <authenticated doctor>`.
     *
     * Key includes the event's own ID (unique per status change), so the
     * same appointment having several status changes always produces
     * distinct, independently-readable notifications.
     */
    protected function appointmentStatusNotifications(User $doctor): Collection
    {
        return AppointmentStatusEvent::whereIn('status', self::RELEVANT_STATUSES)
            ->whereHas('appointment', fn ($q) => $q->where('doctor_id', $doctor->id))
            ->with(['appointment.patient', 'appointment.walkinPatient'])
            // Tie-break by event id — two status changes can land in the
            // same second and the most recently RECORDED one must sort
            // first deterministically.
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
                        "{$patientName} requested an appointment with you.",
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
                    'Checked In' => [
                        'bx-log-in-circle', 'notif-item-icon--checkedin', 'Checked In', 'notif-label--checkedin',
                        'Patient Checked In',
                        "{$patientName} has checked in and is ready for consultation.",
                    ],
                    'In Progress' => [
                        'bx-loader-circle', 'notif-item-icon--inprogress', 'In Progress', 'notif-label--inprogress',
                        'Consultation In Progress',
                        "Consultation with {$patientName} is in progress.",
                    ],
                    'Completed' => [
                        'bx-check-double', 'notif-item-icon--approved', 'Completed', 'notif-label--approved',
                        'Consultation Completed',
                        "Consultation with {$patientName} has been completed.",
                    ],
                    default => [
                        'bx-info-circle', '', $event->status, '',
                        'Appointment Update',
                        "{$patientName}'s appointment status was updated.",
                    ],
                };

                return [
                    'key'        => "doctor_appointment_{$app->id}_{$event->status}_{$event->id}",
                    'category'   => 'appointments',
                    'icon'       => $icon,
                    'icon_class' => $iconClass,
                    'label'      => $label,
                    'label_class'=> $labelClass,
                    'title'      => $title,
                    'message'    => $message,
                    'url'        => $this->appointmentUrl($app),
                    'timestamp'  => $event->occurred_at,
                ];
            });
    }

    /**
     * "Laboratory result is ready for review." — one item per Laboratory
     * Request THIS doctor requested (`laboratory_requests.doctor_id`) that
     * is currently in `Result Received` (i.e. awaiting the Doctor's
     * review). Other doctors' requests are never selected.
     *
     * Key = request id + result_received_at + newest active result id, so a
     * corrected/re-uploaded result (which moves the request Correction
     * Required -> Result Received again) produces a fresh, unread
     * notification, while a page refresh never does. Once the Doctor
     * reviews the request it leaves this source (status = Reviewed),
     * exactly like the previous bell behaved.
     */
    protected function laboratoryResultNotifications(User $doctor): Collection
    {
        return LaboratoryRequest::where('doctor_id', $doctor->id)
            ->where('status', 'Result Received')
            ->with(['patient', 'walkinPatient'])
            ->withMax('results', 'id')
            ->orderByDesc('result_received_at')
            ->orderByDesc('id')
            ->take(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(function (LaboratoryRequest $lab) {
                $timestamp = $lab->result_received_at ?? $lab->updated_at;

                return [
                    'key'        => "doctor_lab_{$lab->id}_{$timestamp->timestamp}_" . ($lab->results_max_id ?? 0),
                    'category'   => 'laboratory',
                    'icon'       => 'bxs-flask',
                    'icon_class' => 'notif-item-icon--new',
                    'label'      => 'New Laboratory Result',
                    'label_class'=> 'notif-label--new',
                    'title'      => $lab->patientName(),
                    'message'    => 'Laboratory result is ready for review.',
                    'url'        => route('doctor.laboratory-requests.show', $lab->id),
                    'timestamp'  => $timestamp,
                ];
            });
    }

    /**
     * Where a notification should take the Doctor. Decided from the
     * appointment's CURRENT status, because the consultation page
     * (doctor.appointments.show) redirects away for anything that is not
     * Checked In / In Progress / Completed. Approved/Rescheduled land on
     * the Appointments list pre-filtered to this appointment via the
     * list's existing search box (reference no.). Pending / Rejected /
     * Cancelled / No Show appointments do not appear on any Doctor page
     * other than the plain list, so they link there.
     */
    protected function appointmentUrl(Appointment $app): string
    {
        if (in_array($app->status, self::CONSULTATION_STATUSES, true)) {
            return route('doctor.appointments.show', $app->id);
        }

        if (in_array($app->status, ['Approved', 'Rescheduled'], true) && $app->reference_no) {
            return route('doctor.appointments.index', ['search' => $app->reference_no]);
        }

        return route('doctor.appointments.index');
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
