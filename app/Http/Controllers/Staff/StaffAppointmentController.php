<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Appointment;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentStatusMail;
use Carbon\Carbon;

class StaffAppointmentController extends Controller
{
    /**
     * "September 10, 2026 at 10:00 AM" — used in Activity Log descriptions
     * so a Staff member can tell exactly which appointment an entry refers
     * to without opening it.
     */
    private function appointmentSchedule(Appointment $appointment): string
    {
        $date = Carbon::parse($appointment->appointment_date)->format('F d, Y');
        $time = Carbon::parse($appointment->appointment_time)->format('h:i A');

        return "{$date} at {$time}";
    }

    /**
     * All appointment statuses actually supported by the current
     * `appointments.status` enum (see the appointments table migration).
     * Used to drive the status tabs/filter on the main Appointments page.
     */
    public const STATUSES = [
        'Pending',
        'Approved',
        'Checked In',
        'In Progress',
        'Rescheduled',
        'Completed',
        'Cancelled',
        'Rejected',
        'No Show',
    ];

    // ================= REUSE HOOKS (overridden by AdminAppointmentController) =================
    /**
     * Blade view for the main Appointments list. Admin gets its own
     * purple-themed page (admin.appointments) while reusing every bit
     * of the query/filter/permission logic below — see
     * AdminAppointmentController::indexView().
     */
    protected function indexView(): string
    {
        return 'staff.appointments';
    }

    /**
     * Route name for the main Appointments list, used for the legacy
     * "pending" redirect below. Overridden by AdminAppointmentController.
     */
    protected function indexRoute(): string
    {
        return 'staff.appointments.index';
    }

    // ================= LIST ALL APPOINTMENTS (with status filter) =================
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        if ($status !== 'all' && !in_array($status, self::STATUSES, true)) {
            $status = 'all';
        }

        $query = Appointment::with(['patient', 'walkinPatient', 'doctor']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('walkinPatient', function ($p) use ($search) {
                        $p->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($doctorId = $request->query('doctor_id')) {
            $query->where('doctor_id', $doctorId);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('appointment_date', $date);
        }

        $appointments = $query->latest()
            ->paginate(10)
            ->appends($request->query());

        // Counts per status, for the tab badges — derived from the same
        // existing `status` column, no new fields.
        $statusCounts = Appointment::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $doctors = User::where('role', 'Doctor')->orderBy('last_name')->get();

        // Per-action permission flags for this Staff user, same
        // convention used by StaffPatientController::index()
        // ($canAddWalkIn/$canEditPatient/etc.). Reaching this action at
        // all already required `view_appointments` (route middleware),
        // so that one isn't repeated here — only the finer-grained
        // action permissions the table/action-menu need are.
        $user = auth()->user();
        $canViewDetails = $user->hasPermission('view_appointment_details');
        $canApprove     = $user->hasPermission('approve_appointment');
        $canReject      = $user->hasPermission('reject_appointment');
        $canCancel      = $user->hasPermission('cancel_appointment');
        $canReschedule  = $user->hasPermission('reschedule_appointment');
        $canMarkNoShow  = $user->hasPermission('mark_no_show_appointment');
        $canCheckIn     = $user->hasPermission('check_in_appointment');

        // Admin-only action (not part of the Staff permission set at
        // all — Staff can never delete an appointment). Computed here,
        // alongside the other can* flags, so AdminAppointmentController
        // doesn't need to duplicate this query/filter logic just to add
        // one extra capability flag.
        $canDelete = $user->role === 'Admin';

        return view($this->indexView(), [
            'appointments'   => $appointments,
            'activeStatus'   => $status,
            'statusCounts'   => $statusCounts,
            'statusList'     => self::STATUSES,
            'doctors'        => $doctors,
            'canViewDetails' => $canViewDetails,
            'canApprove'     => $canApprove,
            'canReject'      => $canReject,
            'canCancel'      => $canCancel,
            'canReschedule'  => $canReschedule,
            'canMarkNoShow'  => $canMarkNoShow,
            'canCheckIn'     => $canCheckIn,
            'canDelete'      => $canDelete,
        ]);
    }

    // ================= OLD PENDING APPOINTMENTS URL =================
    // Pending is now a filter inside the main Appointments page, not a
    // separate page. Keep this route alive (bookmarks, old links,
    // notifications) but send it straight to the filtered list.
    public function pending()
    {
        return redirect()->route($this->indexRoute(), ['status' => 'Pending']);
    }

    // ================= SHOW APPOINTMENT DETAILS =================
    public function show(Appointment $appointment)
    {
        $appointment->load(['patient', 'walkinPatient', 'doctor']);

        $prescriptions = $appointment->prescriptions()->with('medicine')->get();

        // Reaching this action at all already required
        // `view_appointment_details` (route middleware). The workflow
        // buttons on this page (Approve/Reject/Cancel/Check In/Dispense)
        // are each gated individually on top of that, same as the
        // Appointments list.
        $user = auth()->user();
        $canApprove  = $user->hasPermission('approve_appointment');
        $canReject   = $user->hasPermission('reject_appointment');
        $canCancel   = $user->hasPermission('cancel_appointment');
        $canCheckIn  = $user->hasPermission('check_in_appointment');
        $canDispense = $user->hasPermission('dispense_prescription');

        return view('staff.appointment-show', compact(
            'appointment',
            'prescriptions',
            'canApprove',
            'canReject',
            'canCancel',
            'canCheckIn',
            'canDispense'
        ));
    }

    // ================= STORE APPOINTMENT =================
    public function store(Request $request)
    {
        $request->validate([
            'patient_id'       => 'required',
            'patient_type'     => 'required|in:user,patient',
            'doctor_id'        => 'required|exists:users,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required',
            'reason'           => 'required|string|max:500',
        ]);

        // ── Duplicate slot check ──────────────────────────────────────────
        // Prevent double-booking the same doctor at the same date and time.
        $conflict = Appointment::where('doctor_id',        $request->doctor_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'appointment_time' => 'This doctor already has an appointment at the selected date and time. Please choose a different time slot.',
            ]);
        }
        // ─────────────────────────────────────────────────────────────────

        $data = [
            'doctor_id'         => $request->doctor_id,
            'appointment_date'  => $request->appointment_date,
            'appointment_time'  => $request->appointment_time,
            'reason'            => $request->reason,
            'status'            => 'Approved',
            'patient_id'        => null,
            'walkin_patient_id' => null,
        ];

        if ($request->patient_type === 'user') {
            $data['patient_id'] = $request->patient_id;
        } else {
            $data['walkin_patient_id'] = $request->patient_id;
        }

        $appointment = Appointment::create($data);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Created Appointment',
            'module'  => 'Appointments',
            'details' => 'Scheduled appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' on ' . $this->appointmentSchedule($appointment) . '.',
        ]);

        return redirect()->route('staff.appointments.index')
            ->with('success', 'Appointment scheduled successfully!');
    }

    // ================= APPROVE APPOINTMENT =================
    public function approve($id)
    {
        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        $appointment->update(['status' => 'Approved']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Approved Appointment',
            'module'  => 'Appointments',
            'details' => 'Approved appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' scheduled on ' . $this->appointmentSchedule($appointment) . '.',
        ]);

        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Approved'));
        }

        return redirect()->back()->with('success', 'Appointment approved successfully!');
    }

    // ================= CHECK IN APPOINTMENT =================
    /**
     * Staff confirms the patient has physically arrived at the clinic.
     * Only an Approved or Rescheduled appointment can be checked in —
     * this is enforced here on the backend (not just hidden in the
     * Blade view), so a manually-crafted request against a Pending/
     * Completed/Cancelled/Rejected/No Show/already-Checked-In
     * appointment is rejected too. No patient email is sent here
     * (unlike Approve/Reject/Cancel/Reschedule) since the patient is
     * already on-site at this point.
     */
    public function checkIn($id)
    {
        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        if (!$appointment->canBeCheckedIn()) {
            return redirect()->back()
                ->with('error', 'Only approved or rescheduled appointments scheduled for today can be checked in.');
        }

        $appointment->update(['status' => 'Checked In']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Checked In Appointment',
            'module'  => 'Appointments',
            'details' => 'Checked in appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' scheduled on ' . $this->appointmentSchedule($appointment) . '.',
        ]);

        return redirect()->back()->with('success', 'Patient checked in successfully!');
    }

    // ================= REJECT APPOINTMENT =================
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        $appointment->update([
            'status'           => 'Rejected',
            'rejection_reason' => $request->reason,
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Rejected Appointment',
            'module'  => 'Appointments',
            'details' => 'Rejected appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . '. Reason: ' . $request->reason,
        ]);

        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Rejected'));
        }

        return redirect()->back()->with('success', 'Appointment rejected successfully!');
    }

    // ================= CANCEL APPOINTMENT =================
    public function cancel($id)
    {
        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        $appointment->update(['status' => 'Cancelled']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Cancelled Appointment',
            'module'  => 'Appointments',
            'details' => 'Cancelled appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' scheduled on ' . $this->appointmentSchedule($appointment) . '.',
        ]);

        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Cancelled'));
        }

        return redirect()->back()->with('success', 'Appointment cancelled successfully!');
    }

    // ================= RESCHEDULE APPOINTMENT =================
    public function reschedule(Request $request, $id)
    {
        $request->validate([
            'appointment_date'  => 'required|date|after_or_equal:today',
            'appointment_time'  => 'required|date_format:H:i',
            'reschedule_reason' => 'required|string|max:500',
        ]);

        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        // Same eligibility rule used by the Doctor Reschedule action —
        // only an Approved or already-Rescheduled appointment can be
        // moved again. Completed/Cancelled/Rejected/No Show cannot.
        if (!in_array($appointment->status, ['Approved', 'Rescheduled'], true)) {
            return redirect()->back()
                ->with('error', 'Only approved or rescheduled appointments can be rescheduled.');
        }

        // Prevent moving it onto a slot the same doctor already has taken.
        $isTaken = Appointment::where('doctor_id', $appointment->doctor_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereNotIn('status', ['Rejected', 'Cancelled', 'Completed'])
            ->where('id', '!=', $appointment->id)
            ->exists();

        if ($isTaken) {
            return redirect()->back()
                ->with('error', 'That time slot is already taken. Please choose another.');
        }

        $appointment->update([
            'appointment_date'  => $request->appointment_date,
            'appointment_time'  => $request->appointment_time,
            'status'            => 'Rescheduled',
            'rescheduled_by'    => auth()->id(),
            'reschedule_reason' => $request->reschedule_reason,
            'rescheduled_at'    => now(),
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Rescheduled Appointment (Staff)',
            'module'  => 'Appointments',
            'details' => 'Rescheduled appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' to ' . $this->appointmentSchedule($appointment)
                       . '. Reason: ' . $request->reschedule_reason,
        ]);

        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Rescheduled'));
        }

        return redirect()->back()->with('success', 'Appointment rescheduled successfully!');
    }

    // ================= MARK APPOINTMENT AS NO SHOW =================
    public function noShow($id)
    {
        $appointment = Appointment::with(['patient', 'walkinPatient', 'doctor'])->findOrFail($id);

        if (!in_array($appointment->status, ['Approved', 'Rescheduled'], true)) {
            return redirect()->back()
                ->with('error', 'Only approved or rescheduled appointments can be marked as No Show.');
        }

        // Same 30-minute-slot rule used by the Doctor No Show action —
        // the stored appointment_time is the slot start time.
        $appointmentStart = Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time
        );
        $appointmentEnd = $appointmentStart->copy()->addMinutes(30);

        if (Carbon::now()->lt($appointmentEnd)) {
            return redirect()->back()
                ->with('error', 'No Show is only available after the scheduled appointment time has ended.');
        }

        $appointment->update(['status' => 'No Show']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Marked Appointment as No Show (Staff)',
            'module'  => 'Appointments',
            'details' => 'Marked appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' scheduled on ' . $this->appointmentSchedule($appointment)
                       . ' as No Show.',
        ]);

        return redirect()->back()->with('success', 'Appointment marked as No Show.');
    }
}