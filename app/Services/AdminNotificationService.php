<?php

namespace App\Services;

use App\Models\AdminNotificationRead;
use App\Models\Appointment;
use App\Models\AppointmentStatusEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the Admin notification feed (pending accounts + pending
 * appointments) and cross-references it against the
 * `admin_notification_reads` table to decide read/unread state.
 *
 * Architecture mirrors StaffNotificationService on purpose (same
 * derive-on-the-fly + persisted-read-state approach — Admin, like Staff,
 * sees ALL appointments, not just one patient's), but this service is
 * entirely independent:
 *
 *  - Reads/writes `admin_notification_reads`, NEVER
 *    `staff_notification_reads` or `patient_notification_reads`.
 *  - Notification keys are prefixed `admin_` and are never shared with
 *    Staff/Patient notification keys, even though the appointment source
 *    ultimately reads from the same underlying `appointment_status_events`
 *    table Staff already uses.
 *
 * IMPORTANT: notifications themselves are NOT stored in the database —
 * they are derived on the fly from the live `users` / `appointments`
 * tables (the SAME source the Admin Dashboard and the Pending Accounts /
 * Pending Appointments pages already query), so the badge count here is
 * always exactly consistent with those pages. Only the READ STATE is
 * persisted, keyed by a stable "notification_key" per pending item so a
 * newly pending item always shows up unread while previously-read ones
 * stay read as long as they remain pending.
 */
class AdminNotificationService
{
    /** How many rows to pull per source query (keeps things bounded/fast). */
    protected const PER_SOURCE_LIMIT = 15;

    /** How many notifications to keep after merging + sorting all sources. */
    protected const TOTAL_LIMIT = 30;

    /**
     * Build the full notification list for this admin, each item
     * annotated with an accurate 'unread' flag from the database.
     */
    public function build(User $admin): Collection
    {
        $items = $this->rawNotifications();

        $readKeys = AdminNotificationRead::where('user_id', $admin->id)
            ->whereIn('notification_key', $items->pluck('key'))
            ->pluck('notification_key')
            ->flip(); // key => index, for O(1) isset() lookups

        return $items->map(function ($item) use ($readKeys) {
            $item['unread'] = !isset($readKeys[$item['key']]);
            return $item;
        })->values();
    }

    /**
     * Unread count for the header badge — bounded by the same window as
     * the panel, and (since both sources are the live pending queries)
     * consistent with the Admin Dashboard's own pending counts.
     */
    public function unreadCount(User $admin): int
    {
        return $this->build($admin)->where('unread', true)->count();
    }

    /**
     * Mark a single notification as read for this admin. Only succeeds
     * if the key corresponds to a real, currently-generated notification
     * — never trusts the key blindly, and never allows one admin to
     * touch another admin's read state.
     */
    public function markRead(User $admin, string $key): bool
    {
        $validKeys = $this->rawNotifications()->pluck('key');

        if (!$validKeys->contains($key)) {
            return false;
        }

        AdminNotificationRead::firstOrCreate(
            ['user_id' => $admin->id, 'notification_key' => $key],
            ['read_at' => now()]
        );

        return true;
    }

    /**
     * Mark every currently-unread notification for this admin as read.
     */
    public function markAllRead(User $admin): int
    {
        $items = $this->build($admin)->where('unread', true);

        $now = now();

        foreach ($items as $item) {
            AdminNotificationRead::firstOrCreate(
                ['user_id' => $admin->id, 'notification_key' => $item['key']],
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
        $items = collect()
            ->concat($this->pendingAccountNotifications())
            ->concat($this->pendingAppointmentNotifications());

        return $items
            ->sortByDesc('timestamp')
            ->take(self::TOTAL_LIMIT)
            ->values();
    }

    /**
     * Pending account registrations awaiting Admin approval.
     *
     * Uses the exact same query shape as AdminController::pendingAccounts()
     * / the Dashboard's pending count (`approval_status = Pending`,
     * excluding Admin accounts) so this feed's badge count always matches
     * what those pages show.
     *
     * Key is stable per user id (no timestamp component) — once approved
     * or rejected the user drops out of this query entirely, so the key
     * never reappears/needs to be "fresh" again unless a brand-new
     * registration (a new user id) comes in.
     */
    protected function pendingAccountNotifications(): Collection
    {
        return User::where('approval_status', 'Pending')
            ->where('role', '!=', 'Admin')
            ->orderByDesc('created_at')
            ->take(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(function ($user) {
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'New user';

                return [
                    'key'        => "admin_account_{$user->id}",
                    'category'   => 'accounts',
                    'icon'       => 'bx-user-plus',
                    'icon_class' => 'icon-account',
                    'label'      => 'Pending Account',
                    'label_class'=> 'notif-label--account',
                    'title'      => 'New Account Pending Approval',
                    'message'    => "{$name} ({$user->role}) is awaiting account approval.",
                    'url'        => route('admin.pending'),
                    'timestamp'  => $user->created_at,
                ];
            });
    }

    /**
     * Pending appointment requests awaiting Admin action, for ALL
     * patients (Admin manages every appointment, like Staff).
     *
     * The *set* of appointments is the same live `status = Pending` query
     * the Dashboard and Appointments page use, so the count here always
     * matches. For the notification KEY, we additionally look up each
     * appointment's most recent 'Pending' entry in the append-only
     * `appointment_status_events` history (the same table Staff already
     * uses) so that if an appointment leaves Pending and later becomes
     * Pending again (e.g. after a reschedule), it gets a fresh, distinct,
     * unread key instead of silently staying "already read". Falls back
     * to the appointment's own id/created_at for legacy rows that predate
     * event tracking.
     */
    protected function pendingAppointmentNotifications(): Collection
    {
        $appointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('status', 'Pending')
            ->orderByDesc('created_at')
            ->take(self::PER_SOURCE_LIMIT)
            ->get();

        if ($appointments->isEmpty()) {
            return collect();
        }

        $latestPendingEvents = AppointmentStatusEvent::whereIn('appointment_id', $appointments->pluck('id'))
            ->where('status', 'Pending')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->unique('appointment_id')
            ->keyBy('appointment_id');

        return $appointments->map(function ($app) use ($latestPendingEvents) {
            $event = $latestPendingEvents->get($app->id);

            return [
                'key'        => $event
                                    ? "admin_appointment_{$app->id}_Pending_{$event->id}"
                                    : "admin_appointment_{$app->id}_Pending_legacy",
                'category'   => 'appointments',
                'icon'       => 'bx-calendar-check',
                'icon_class' => 'icon-appointment',
                'label'      => 'Pending Appointment',
                'label_class'=> 'notif-label--appointment',
                'title'      => $app->patientName(),
                'message'    => $app->reason ?? 'No reason provided',
                'url'        => route('admin.appointments.show', $app->id),
                'timestamp'  => $event->occurred_at ?? $app->created_at,
            ];
        });
    }
}
