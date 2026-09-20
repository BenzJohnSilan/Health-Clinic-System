<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Staff\StaffAppointmentController;
use App\Models\Appointment;
use App\Models\UserLog;

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

    // ================= DELETE APPOINTMENT (Admin-only) =================
    /**
     * Staff has no equivalent action — Delete is only ever offered to
     * Admin, straight from the three-dot menu on the Appointments page.
     */
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Deleted Appointment',
            'module'  => 'Appointments',
            'details' => 'Deleted appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName() . '.',
        ]);

        $appointment->delete();

        return redirect()->route('admin.appointments.index')
            ->with('success', 'Appointment deleted successfully!');
    }
}
