<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentStatusEvent;
use App\Models\MedicalCertificate;
use App\Models\MedicalCertificateCorrection;
use App\Models\PatientNotificationRead;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the Patient notification feed (appointments, medical
 * certificates, account) and cross-references it against the
 * `patient_notification_reads` table to decide read/unread state.
 *
 * IMPORTANT: notifications themselves are NOT stored in the database —
 * they are derived on the fly from existing Appointment / MedicalCertificate
 * / MedicalCertificateCorrection records (same approach the project already
 * used). Only the READ STATE is persisted, keyed by a stable
 * "notification_key" per event so a status change always produces a new,
 * unread notification while previously-read ones stay read.
 */
class PatientNotificationService
{
    /** How many rows to pull per source query (keeps things bounded/fast). */
    protected const PER_SOURCE_LIMIT = 15;

    /** How many notifications to keep after merging + sorting all sources. */
    protected const TOTAL_LIMIT = 30;

    /**
     * Build the full notification list for this patient, each item
     * annotated with an accurate 'unread' flag from the database.
     */
    public function build(User $patient): Collection
    {
        $items = $this->rawNotifications($patient);

        $readKeys = PatientNotificationRead::where('user_id', $patient->id)
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
     * window as the panel — consistent with what the patient can see).
     */
    public function unreadCount(User $patient): int
    {
        return $this->build($patient)->where('unread', true)->count();
    }

    /**
     * Mark a single notification as read for this patient. Only succeeds
     * if the key corresponds to a real, currently-generated notification
     * for THIS patient — never trusts the key blindly, and never allows
     * one patient to touch another patient's read state.
     */
    public function markRead(User $patient, string $key): bool
    {
        $validKeys = $this->rawNotifications($patient)->pluck('key');

        if (!$validKeys->contains($key)) {
            return false;
        }

        PatientNotificationRead::firstOrCreate(
            ['user_id' => $patient->id, 'notification_key' => $key],
            ['read_at' => now()]
        );

        return true;
    }

    /**
     * Mark every currently-unread notification for this patient as read.
     */
    public function markAllRead(User $patient): int
    {
        $items = $this->build($patient)->where('unread', true);

        $now = now();

        foreach ($items as $item) {
            PatientNotificationRead::firstOrCreate(
                ['user_id' => $patient->id, 'notification_key' => $item['key']],
                ['read_at' => $now]
            );
        }

        return $items->count();
    }

    // =========================================================================
    // SOURCES — derive the raw (unread-state-agnostic) notification list
    // =========================================================================

    protected function rawNotifications(User $patient): Collection
    {
        $items = collect()
            ->concat($this->appointmentStatusNotifications($patient))
            ->concat($this->upcomingReminderNotifications($patient))
            ->concat($this->medicalCertificateNotifications($patient))
            ->concat($this->medicalCertificateCorrectionNotifications($patient))
            ->concat($this->accountNotifications($patient));

        return $items
            ->sortByDesc('timestamp')
            ->take(self::TOTAL_LIMIT)
            ->values();
    }

    /**
     * Appointment status changes: Approved, Rejected, Cancelled, Rescheduled.
     *
     * Reads from `appointment_status_events` (an append-only history —
     * see AppointmentStatusEvent / Appointment::boot()) rather than the
     * live `appointments.status` column. This is what allows an OLD status
     * notification (e.g. Approved) to remain in history correctly marked
     * as read even after the appointment's status later changes again
     * (e.g. to Cancelled) — the live column alone can't represent that,
     * since it only ever holds the current status.
     *
     * Key includes the status AND the event's own timestamp, so the same
     * appointment produces a brand-new key every time its status changes
     * (Approved -> read, later Cancelled -> new unread), while the old
     * Approved notification's key never reappears or gets reused.
     */
    protected function appointmentStatusNotifications(User $patient): Collection
    {
        return AppointmentStatusEvent::whereIn('status', ['Approved', 'Rejected', 'Cancelled', 'Rescheduled'])
            ->whereHas('appointment', fn ($q) => $q->where('patient_id', $patient->id))
            ->with('appointment')
            ->latest('occurred_at')
            ->take(self::PER_SOURCE_LIMIT)
            ->get()
            ->filter(fn ($event) => $event->appointment !== null)
            ->map(function ($event) {
                $app = $event->appointment;

                [$icon, $label] = match ($event->status) {
                    'Approved'    => ['bx-check-circle', 'notif-item-icon--approved'],
                    'Rejected'    => ['bx-x-circle', 'notif-item-icon--rejected'],
                    'Cancelled'   => ['bx-minus-circle', 'notif-item-icon--cancelled'],
                    'Rescheduled' => ['bx-calendar-edit', 'notif-item-icon--rescheduled'],
                    default       => ['bx-info-circle', ''],
                };

                $title = match ($event->status) {
                    'Approved'    => 'Appointment Approved',
                    'Rejected'    => 'Appointment Rejected',
                    'Cancelled'   => 'Appointment Cancelled',
                    'Rescheduled' => 'Appointment Rescheduled',
                    default       => 'Appointment Update',
                };

                $dateStr = $app->appointment_date
                    ? \Carbon\Carbon::parse($app->appointment_date)->format('M j, Y')
                    : null;
                $timeStr = $app->appointment_time
                    ? \Carbon\Carbon::parse($app->appointment_time)->format('g:i A')
                    : null;

                $message = match ($event->status) {
                    'Approved'    => 'Your appointment' . ($dateStr ? " on {$dateStr}" : '') . ($timeStr ? " at {$timeStr}" : '') . ' has been approved.',
                    'Rejected'    => 'Your appointment request' . ($dateStr ? " for {$dateStr}" : '') . ' was rejected.',
                    'Cancelled'   => 'Your appointment' . ($dateStr ? " on {$dateStr}" : '') . ($timeStr ? " at {$timeStr}" : '') . ' has been cancelled.',
                    'Rescheduled' => 'Your appointment has been moved' . ($dateStr ? " to {$dateStr}" : '') . ($timeStr ? " at {$timeStr}" : '') . '.',
                    default       => $app->reason ?? 'Your appointment status was updated.',
                };

                return [
                    'key'        => "appointment_{$app->id}_{$event->status}_{$event->occurred_at->timestamp}_{$event->id}",
                    'category'   => 'appointments',
                    'icon'       => $icon,
                    'icon_class' => $label,
                    'label'      => $event->status,
                    'label_class'=> match ($event->status) {
                        'Approved'    => 'notif-label--approved',
                        'Rejected'    => 'notif-label--rejected',
                        'Cancelled'   => 'notif-label--cancelled',
                        'Rescheduled' => 'notif-label--rescheduled',
                        default       => '',
                    },
                    'title'      => $title,
                    'message'    => $message,
                    'url'        => route('patient.appointments.index'),
                    'timestamp'  => $event->occurred_at,
                ];
            });
    }

    /**
     * Upcoming appointment reminders (Approved appointments in the next 7
     * days). Key is STABLE per appointment (no timestamp component), so
     * once the patient reads a reminder it stays read on every refresh —
     * it never gets recreated as unread just because the page reloaded.
     */
    protected function upcomingReminderNotifications(User $patient): Collection
    {
        return Appointment::where('patient_id', $patient->id)
            ->where('status', 'Approved')
            ->whereBetween('appointment_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->orderBy('appointment_date')
            ->take(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(function ($app) {
                $dateStr = $app->appointment_date
                    ? \Carbon\Carbon::parse($app->appointment_date)->format('M j, Y')
                    : null;
                $timeStr = $app->appointment_time
                    ? \Carbon\Carbon::parse($app->appointment_time)->format('g:i A')
                    : null;

                return [
                    'key'        => "reminder_appointment_{$app->id}",
                    'category'   => 'appointments',
                    'icon'       => 'bx-calendar-event',
                    'icon_class' => 'notif-item-icon--upcoming',
                    'label'      => 'Upcoming',
                    'label_class'=> 'notif-label--upcoming',
                    'title'      => 'Appointment Reminder',
                    'message'    => 'You have an appointment' . ($dateStr ? " on {$dateStr}" : ' coming up') . ($timeStr ? " at {$timeStr}" : '') . '.',
                    'url'        => route('patient.appointments.index'),
                    // Reminders are ordered by how soon they are, but for
                    // merging with the rest of the feed we still need a
                    // real point in time — use the appointment's own
                    // created/updated_at so it doesn't dominate the top
                    // of a mixed feed indefinitely.
                    'timestamp'  => $app->updated_at,
                ];
            });
    }

    /**
     * Medical Certificate lifecycle notifications for the patient's own
     * requests: Submitted, Ready/Issued, Rejected.
     *
     * Uses `updated_at` for the "issued" key so a correction that gets
     * re-issued produces a fresh notification (issued_at is intentionally
     * left unchanged by the app when correcting, but updated_at always
     * changes on save).
     */
    protected function medicalCertificateNotifications(User $patient): Collection
    {
        $certs = MedicalCertificate::where('patient_id', $patient->id)
            ->latest('updated_at')
            ->take(self::PER_SOURCE_LIMIT)
            ->get();

        $items = collect();

        foreach ($certs as $cert) {
            // Request submitted.
            if ($cert->requested_at) {
                $items->push([
                    'key'        => "mc_{$cert->id}_pending_{$cert->requested_at->timestamp}",
                    'category'   => 'medical-certificate',
                    'icon'       => 'bx-file-blank',
                    'icon_class' => '',
                    'label'      => 'Requested',
                    'label_class'=> '',
                    'title'      => 'Medical Certificate Request Submitted',
                    'message'    => 'Your medical certificate request has been submitted successfully.',
                    'url'        => route('patient.medical-certificates.index'),
                    'timestamp'  => $cert->requested_at,
                ]);
            }

            // Issued / ready (covers the original issuance and any later
            // correction-driven re-issuance, since each save bumps updated_at).
            if ($cert->status === 'issued') {
                $items->push([
                    'key'        => "mc_{$cert->id}_issued_{$cert->updated_at->timestamp}",
                    'category'   => 'medical-certificate',
                    'icon'       => 'bx-check-circle',
                    'icon_class' => 'notif-item-icon--approved',
                    'label'      => 'Ready',
                    'label_class'=> 'notif-label--approved',
                    'title'      => 'Medical Certificate Ready',
                    'message'    => 'Your requested medical certificate is now available.',
                    'url'        => route('patient.medical-certificates.show', $cert->id),
                    // For the "issued" event specifically we key/time off
                    // issued_at when present so a correction currently in
                    // review doesn't push a stale "ready" notice to the
                    // top; fall back to updated_at otherwise.
                    'timestamp'  => $cert->issued_at ?? $cert->updated_at,
                ]);
            }

            // Rejected.
            if ($cert->status === 'rejected' && $cert->rejected_at) {
                $items->push([
                    'key'        => "mc_{$cert->id}_rejected_{$cert->rejected_at->timestamp}",
                    'category'   => 'medical-certificate',
                    'icon'       => 'bx-x-circle',
                    'icon_class' => 'notif-item-icon--rejected',
                    'label'      => 'Rejected',
                    'label_class'=> 'notif-label--rejected',
                    'title'      => 'Medical Certificate Request Rejected',
                    'message'    => 'Your medical certificate request was not approved.',
                    'url'        => route('patient.medical-certificates.show', $cert->id),
                    'timestamp'  => $cert->rejected_at,
                ]);
            }
        }

        return $items;
    }

    /**
     * Notify the patient when a doctor responds to a correction request
     * THEY filed on an issued certificate (resolved -> re-issued, handled
     * above via the certificate's own updated_at; rejected -> handled
     * here since the certificate itself just reverts to 'issued' with no
     * new distinguishing timestamp of its own).
     */
    protected function medicalCertificateCorrectionNotifications(User $patient): Collection
    {
        return MedicalCertificateCorrection::where('patient_id', $patient->id)
            ->where('status', 'rejected')
            ->whereNotNull('resolved_at')
            ->latest('resolved_at')
            ->take(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(function ($correction) {
                return [
                    'key'        => "mc_correction_{$correction->id}_rejected_{$correction->resolved_at->timestamp}",
                    'category'   => 'medical-certificate',
                    'icon'       => 'bx-x-circle',
                    'icon_class' => 'notif-item-icon--rejected',
                    'label'      => 'Correction Declined',
                    'label_class'=> 'notif-label--rejected',
                    'title'      => 'Correction Request Declined',
                    'message'    => 'Your correction request was not approved. The original certificate remains valid.',
                    'url'        => route('patient.medical-certificates.show', $correction->medical_certificate_id),
                    'timestamp'  => $correction->resolved_at,
                ];
            });
    }

    /**
     * Account-level notifications already supported by the current
     * system (account approval). Kept minimal on purpose — no
     * notifications for trivial actions.
     */
    protected function accountNotifications(User $patient): Collection
    {
        $items = collect();

        if ($patient->approval_status === 'Approved') {
            // Stable key (no timestamp component) — this is a one-time
            // event and must NOT re-trigger every time the patient's
            // `users` row is touched by an unrelated profile update.
            $items->push([
                'key'        => "account_{$patient->id}_approved",
                'category'   => 'account',
                'icon'       => 'bx-badge-check',
                'icon_class' => 'notif-item-icon--approved',
                'label'      => 'Account',
                'label_class'=> 'notif-label--approved',
                'title'      => 'Account Approved',
                'message'    => 'Your patient account has been approved. You can now book appointments.',
                'url'        => route('patient.dashboard'),
                'timestamp'  => $patient->updated_at,
            ]);
        }

        return $items;
    }
}
