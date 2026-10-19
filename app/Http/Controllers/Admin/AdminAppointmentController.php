<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Staff\StaffAppointmentController;
use App\Models\Appointment;
use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveService;

/**
 * Admin's single, centralized Appointments page.
 *
 * This intentionally extends the Staff controller instead of
 * re-implementing appointment management: the list/search/filter
 * query, the status tabs, and every workflow action (Approve, Reject,
 * Cancel, Reschedule, Check In, No Show) are all inherited as-is from
 * StaffAppointmentController. Admin automatically has full access to
 * every one of those actions because User::hasPermission() already
 * bypasses the permission system entirely for the Admin role (see
 * App\Models\User::hasPermission()), so nothing here needs its own
 * authorization logic or a parallel permission set.
 *
 * Only what's genuinely different for Admin lives in this class:
 *  - index()/pending() point at the Admin-branded view/route instead
 *    of the Staff one (via the small indexView()/indexRoute() hooks
 *    on the parent).
 *  - show() renders a simpler, read-only Admin appointment details
 *    page (no prescriptions/dispensing — that's a Staff/pharmacy
 *    workflow, not part of Admin's Appointments page).
 *  - destroy() is Admin-only; Staff never gets a Delete action.
 *
 * Appointment CREATION is deliberately NOT here — that stays on
 * Admin -> Patient List -> Schedule Appointment, handled entirely by
 * AdminPatientController::scheduleAppointment(). This controller is
 * view + manage only.
 */
class AdminAppointmentController extends StaffAppointmentController
{
    protected function indexView(): string
    {
        return 'admin.appointments';
    }

    protected function indexRoute(): string
    {
        return 'admin.appointments.index';
    }

    /** Archived appointments are hidden from the Admin list and status counts. */
    protected function applyArchiveVisibility($query)
    {
        return $query->notArchived();
    }

    // ================= SHOW APPOINTMENT DETAILS (read-only) =================
    /**
     * A simpler details view than Staff's — just the patient info,
     * schedule, and reason. No prescriptions/dispensing section: that
     * belongs to the Staff Check-In -> Dispensing workflow, not to
     * Admin's Appointments page.
     */
    public function show($id)
    {
        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        return view('admin.appointment-show', compact('appointment'));
    }

    // ================= ARCHIVE APPOINTMENT (Admin-only) =================
    /**
     * This used to hard-delete the appointment, which also wiped its medical
     * record and prescriptions through database cascades. It now ARCHIVES it:
     * the row and everything linked to it stay; only finished appointments
     * (Completed / Cancelled / Rejected / No Show) are accepted. Restore or
     * permanently delete it from Admin > Archive.
     */
    public function destroy($id, ArchiveService $archive)
    {
        $appointment = Appointment::findOrFail($id);

        try {
            $archive->archive('appointment', $appointment->id, auth()->user());
        } catch (ArchiveException $e) {
            return redirect()->route('admin.appointments.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.appointments.index')
            ->with('success', 'Appointment ' . $appointment->reference_no . ' was moved to the Archive.');
    }
}