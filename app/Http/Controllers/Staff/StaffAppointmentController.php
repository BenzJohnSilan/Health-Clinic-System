<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Appointment;
use App\Models\LaboratoryRequest;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserLog;
use App\Services\DoctorAvailabilityService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppointmentStatusMail;
use App\Services\IProgSmsService;
use Throwable;
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

        // A walk-in has no reserved time — describe it by its queue number.
        if ($appointment->isWalkIn()) {
            return "{$date} (Walk-in {$appointment->queue_number})";
        }

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

        // Today's ACTIVE walk-in queue (Waiting / Checked In / In Progress),
        // shown in the compact "Walk-in Queue" modal on the Appointments
        // page. Completed / Cancelled walk-ins are no longer active.
        $walkInQueue = Appointment::with(['patient', 'walkinPatient', 'doctor', 'medicalRecord'])
            ->activeWalkInQueue()
            ->orderBy('arrived_at')
            ->orderBy('id')
            ->get();

        $canRecordVitals = $user->hasPermission('record_vital_signs');

        // Remove from Queue (Waiting walk-ins only): same permission as
        // adding to the queue (add_walk_in_patient), matching the route.
        $canRemoveFromQueue = $user->hasPermission('add_walk_in_patient');

        return view($this->indexView(), [
            'walkInQueue'    => $walkInQueue,
            'canRecordVitals' => $canRecordVitals,
            'canRemoveFromQueue' => $canRemoveFromQueue,
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

        $prescriptions = $appointment->prescriptions()->with('medicine', 'invoiceItem.invoice')->get();

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
        $canRecordVitals = $user->hasPermission('record_vital_signs');

        // Vital Signs live on the appointment's existing MedicalRecord row.
        $medicalRecord = MedicalRecord::where('appointment_id', $appointment->id)->first();

        return view('staff.appointment-show', compact(
            'appointment',
            'prescriptions',
            'canApprove',
            'canReject',
            'canCancel',
            'canCheckIn',
            'canDispense',
            'canRecordVitals',
            'medicalRecord'
        ));
    }

    // ================= RECORD VITAL SIGNS =================
    /**
     * Staff records/updates the Vital Signs for ONE appointment. Values are
     * written to that appointment's existing MedicalRecord row (created here
     * if it does not exist yet — same updateOrCreate key the Doctor's
     * consultation uses, so there is never a duplicate record). Only the
     * vital-sign columns are touched; clinical fields are never overwritten.
     *
     * A vital may be left blank or marked "Not taken" with a reason — it is
     * stored as NULL, never as 0 / "N/A".
     */
    public function saveVitalSigns(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        if ($appointment->isWalkIn() && $appointment->status === Appointment::STATUS_WAITING) {
            return redirect()->back()
                ->with('error', 'Check in the walk-in patient first, then record the Vital Signs.');
        }

        if (!in_array($appointment->status, ['Approved', 'Rescheduled', 'Checked In'], true)) {
            return redirect()->back()
                ->with('error', 'Vital Signs can only be recorded before the consultation starts.');
        }

        $keys = array_keys(MedicalRecord::VITALS);

        $rules = [
            'vital.blood_pressure'    => ['nullable', 'regex:/^\d{2,3}\/\d{2,3}$/'],
            'vital.temperature'       => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'vital.pulse_rate'        => ['nullable', 'numeric', 'min:0', 'max:999'],
            'vital.respiratory_rate'  => ['nullable', 'numeric', 'min:0', 'max:999'],
            'vital.oxygen_saturation' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vital.weight'            => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'vital.height'            => ['nullable', 'numeric', 'min:0', 'max:999.9'],
        ];
        $messages = [
            'vital.blood_pressure.regex' => 'Blood Pressure must be in the format 120/80.',
        ];
        $attributes = [];

        foreach (MedicalRecord::VITALS as $key => $meta) {
            $attributes["vital.$key"] = strtolower($meta['label']);

            // Reason is mandatory only when this vital is marked Not Taken.
            $rules["reason.$key"] = [
                'nullable', 'required_with:not_taken.' . $key,
                'in:' . implode(',', MedicalRecord::NOT_TAKEN_REASONS),
            ];
            $rules["reason_other.$key"] = [
                'nullable', 'string', 'max:120',
                'required_if:reason.' . $key . ',Other',
            ];
            $attributes["reason.$key"]       = strtolower($meta['label']) . ' reason';
            $attributes["reason_other.$key"] = strtolower($meta['label']) . ' reason details';
        }

        // Only enforce the reason rules for vitals actually marked Not Taken.
        foreach ($keys as $key) {
            if (!$request->boolean("not_taken.$key")) {
                unset($rules["reason.$key"], $rules["reason_other.$key"]);
            }
        }

        $request->validate($rules, $messages, $attributes);

        $values    = [];
        $notTaken  = [];

        foreach ($keys as $key) {
            if ($request->boolean("not_taken.$key")) {
                $values[$key] = null;   // never a fake value
                $reason = $request->input("reason.$key");
                $notTaken[$key] = [
                    'reason' => $reason,
                    'note'   => $reason === 'Other' ? trim((string) $request->input("reason_other.$key")) : null,
                ];
                continue;
            }

            $v = $request->input("vital.$key");
            $values[$key] = filled($v) ? trim((string) $v) : null;
        }

        $record = MedicalRecord::firstOrNew(['appointment_id' => $appointment->id]);

        // Identity fields are only set when the record is being created
        // here; an existing record's ownership is left exactly as it is.
        if (!$record->exists) {
            $record->patient_id        = $appointment->patient_id;
            $record->walkin_patient_id = $appointment->walkin_patient_id;
            $record->doctor_id         = $appointment->doctor_id;
        }

        $record->fill($values);
        $record->vitals_not_taken    = $notTaken ?: null;
        $record->vitals_recorded_by  = auth()->id();
        $record->vitals_recorded_at  = now();
        $record->save();

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Recorded Vital Signs',
            'module'  => 'Appointments',
            'details' => 'Recorded vital signs for appointment ' . $appointment->reference_no
                       . ' (' . $appointment->patientName() . ').',
        ]);

        return redirect()->back()->with('success', 'Vital Signs saved.');
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
            'follow_up_laboratory_request_id' => 'nullable|integer',
        ]);

        // ── Follow-up Consultation (Laboratory result) ───────────────────
        // Only when Staff explicitly chose the Follow-up appointment type.
        // Re-checked from the database: the laboratory request must belong
        // to THIS patient (registered or walk-in), be Reviewed with a
        // follow-up recommended, and not already have an active follow-up.
        $followUpLabRequest = null;

        if ($request->filled('follow_up_laboratory_request_id')) {
            $ownerColumn = $request->patient_type === 'user' ? 'patient_id' : 'walkin_patient_id';

            $followUpLabRequest = LaboratoryRequest::where($ownerColumn, $request->patient_id)
                ->awaitingFollowUpBooking()
                ->find((int) $request->follow_up_laboratory_request_id);

            if (!$followUpLabRequest) {
                throw ValidationException::withMessages([
                    'follow_up_laboratory_request_id' => 'This laboratory result has no follow-up consultation waiting to be booked for this patient.',
                ]);
            }

            if ($followUpLabRequest->doctor_id && (int) $request->doctor_id !== (int) $followUpLabRequest->doctor_id) {
                throw ValidationException::withMessages([
                    'doctor_id' => 'A follow-up consultation must be scheduled with the doctor who reviewed the laboratory result.',
                ]);
            }
        }
        // ─────────────────────────────────────────────────────────────────

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
            // Existing Staff-created behavior: Approved directly (the
            // patient is scheduled in person) — follow-up or not.
            'status'            => 'Approved',
            'appointment_type'  => $followUpLabRequest ? Appointment::TYPE_FOLLOW_UP : Appointment::TYPE_REGULAR,
            'follow_up_laboratory_request_id' => $followUpLabRequest?->id,
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
            'details' => 'Scheduled ' . ($followUpLabRequest ? 'follow-up consultation ' : 'appointment ') . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' on ' . $this->appointmentSchedule($appointment)
                       . ($followUpLabRequest ? ' (laboratory result ' . $followUpLabRequest->reference_no . ').' : '.'),
        ]);

        return redirect()->route('staff.appointments.index')
            ->with('success', $followUpLabRequest
                ? 'Follow-up consultation scheduled successfully!'
                : 'Appointment scheduled successfully!');
    }

    // ================= APPROVE APPOINTMENT =================
    public function approve($id, IProgSmsService $smsService)
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

        // Existing email notification
        if ($appointment->patient && $appointment->patient->email) {
            Mail::to($appointment->patient->email)
                ->send(new AppointmentStatusMail($appointment, 'Approved'));
        }

        // SMS notification
        if ($appointment->patient && $appointment->patient->contact_number) {
            try {
                $phone = $appointment->patient->contact_number;

                // Convert 09XXXXXXXXX → 639XXXXXXXXX
                if (preg_match('/^09\d{9}$/', $phone)) {
                    $phone = '63' . substr($phone, 1);
                }

                $message = $appointment->isFollowUp()
                    ? 'Your follow-up appointment has been approved. '
                    . 'Ref: ' . $appointment->reference_no
                    . '. Date: ' . $this->appointmentSchedule($appointment)
                    . '. Please arrive on time. - Clinic RMS'
                    : 'Your appointment has been approved. '
                    . 'Ref: ' . $appointment->reference_no
                    . '. Date: ' . $this->appointmentSchedule($appointment)
                    . '. Please arrive on time. - Clinic RMS';

                $smsService->send($phone, $message);
            } catch (Throwable $e) {
                // SMS failure must not cancel the appointment approval.
                report($e);
            }
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
                ->with('error', $appointment->isWalkIn()
                    ? 'Only a walk-in that is Waiting in today\'s queue can be checked in.'
                    : 'Only approved or rescheduled appointments scheduled for today can be checked in.');
        }

        // Same record, same status column: Waiting/Approved -> Checked In.
        // No second visit record is ever created here.
        $appointment->update(['status' => 'Checked In']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Checked In Appointment',
            'module'  => 'Appointments',
            'details' => 'Checked in ' . ($appointment->isWalkIn() ? 'walk-in ' : 'appointment ') . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ($appointment->isWalkIn() ? ' ' : ' scheduled on ') . $this->appointmentSchedule($appointment) . '.',
        ]);

        // Check In pressed from the Walk-in Queue modal: reopen the modal.
        if (request()->boolean('from_queue')) {
            return redirect()->route($this->indexRoute(), ['queue' => 1])
                ->with('success', 'Patient checked in successfully!');
        }

        return redirect()->back()->with('success', 'Patient checked in successfully!');
    }

    // ================= REMOVE FROM WALK-IN QUEUE =================
    /**
     * Removes a walk-in that is still WAITING (never checked in), e.g. the
     * patient left or was queued by mistake. Uses the existing 'Cancelled'
     * status — no new status — so the visit stays on record, the queue
     * number is not reused that day, and the patient can be queued again.
     *
     * Only a Waiting walk-in can be removed: once checked in (vitals,
     * consultation) the normal workflow applies. This is deliberately NOT
     * the regular Cancel action, which emails/SMSes the patient — removing
     * a walk-in from the queue notifies nobody.
     */
    public function removeFromQueue($id)
    {
        $result = DB::transaction(function () use ($id) {
            $appointment = Appointment::with(['patient', 'walkinPatient'])->lockForUpdate()->findOrFail($id);

            if (!$appointment->isWalkIn() || $appointment->status !== Appointment::STATUS_WAITING) {
                return [false, null];
            }

            $appointment->update(['status' => 'Cancelled']);

            return [true, $appointment];
        });

        [$removed, $appointment] = $result;

        if (!$removed) {
            return redirect()->route($this->indexRoute(), ['queue' => 1])
                ->with('error', 'Only a walk-in that is still Waiting can be removed from the queue.');
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Removed from Walk-in Queue',
            'module'  => 'Appointments',
            'details' => 'Removed ' . $appointment->patientName() . ' (' . $appointment->queue_number . ', '
                       . $appointment->reference_no . ') from the walk-in queue before check-in.',
        ]);

        return redirect()->route($this->indexRoute(), ['queue' => 1])
            ->with('success', $appointment->queue_number . ' (' . $appointment->patientName() . ') was removed from the Walk-in Queue.');
    }

    // ================= ADD TO WALK-IN QUEUE =================
    /**
     * Staff adds an EXISTING patient (registered user or walk-in patient
     * record) to TODAY's walk-in queue, from Patients -> action menu ->
     * Add to Queue. This creates ONE new visit (an appointments row of type
     * 'Walk-in', status 'Waiting', date = today, appointment_time = NULL,
     * daily queue number) attached to the patient's permanent record — it
     * never creates a patient record.
     *
     * Guarded by `add_walk_in_patient` (route middleware); everything is
     * re-validated here regardless of what the form showed.
     */
    public function addToQueue(Request $request)
    {
        $data = $request->validate([
            'patient_id'   => 'required|integer',
            'patient_type' => 'required|in:user,patient',
            'doctor_id'    => 'required|integer',
            'reason'       => 'nullable|string|max:500',
        ]);

        $today = now()->toDateString();
        $isUser = $data['patient_type'] === 'user';

        $patientExists = $isUser
            ? User::where('role', 'Patient')->whereKey($data['patient_id'])->exists()
            : Patient::where('is_walk_in', true)->whereKey($data['patient_id'])->exists();

        if (!$patientExists) {
            throw ValidationException::withMessages([
                'patient_id' => 'The selected patient could not be found.',
            ]);
        }

        // Only an ACTIVE doctor account who is working today (existing
        // weekly schedule / exception rules) can receive a walk-in.
        $doctor = User::where('role', 'Doctor')->where('status', 'Active')->find($data['doctor_id']);

        if (!$doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => 'Please choose an active doctor.',
            ]);
        }

        [$isAvailable, $unavailableReason] = DoctorAvailabilityService::isAvailableOnDate($doctor->id, $today);

        if (!$isAvailable) {
            throw ValidationException::withMessages([
                'doctor_id' => $unavailableReason ?? 'This doctor is not available today.',
            ]);
        }

        $ownerColumn = $isUser ? 'patient_id' : 'walkin_patient_id';

        $appointment = null;

        // The unique(appointment_date, queue_number) index is the final
        // guard against two Staff members queueing at the same instant;
        // on that rare collision the number is simply recomputed.
        for ($attempt = 1; $attempt <= 3 && !$appointment; $attempt++) {
            try {
                $appointment = DB::transaction(function () use ($data, $today, $ownerColumn, $isUser) {
                    $existing = Appointment::activeWalkInQueue()
                        ->where($ownerColumn, $data['patient_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        throw ValidationException::withMessages([
                            'patient_id' => 'This patient is already in today\'s walk-in queue as ' . $existing->queue_number . '.',
                        ]);
                    }

                    return Appointment::create([
                        'patient_id'        => $isUser ? $data['patient_id'] : null,
                        'walkin_patient_id' => $isUser ? null : $data['patient_id'],
                        'doctor_id'         => $data['doctor_id'],
                        'appointment_date'  => $today,
                        'appointment_time'  => null,
                        'status'            => Appointment::STATUS_WAITING,
                        'appointment_type'  => Appointment::TYPE_WALK_IN,
                        'arrived_at'        => now(),
                        'queue_number'      => Appointment::nextQueueNumber($today),
                        'reason'            => filled($data['reason'] ?? null) ? trim($data['reason']) : null,
                    ]);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 3) {
                    throw $e;
                }
            }
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Added to Walk-in Queue',
            'module'  => 'Appointments',
            'details' => 'Added ' . $appointment->patientName() . ' to the walk-in queue as '
                       . $appointment->queue_number . ' (' . $appointment->reference_no . ') for Dr. '
                       . trim($doctor->first_name . ' ' . $doctor->last_name) . '.',
        ]);

        return redirect()->back()
            ->with('success', $appointment->patientName() . ' was added to the Walk-in Queue as ' . $appointment->queue_number . '.');
    }

    // ================= REJECT APPOINTMENT =================
    public function reject(Request $request, $id, IProgSmsService $smsService)
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

        // SMS notification
        if ($appointment->patient && $appointment->patient->contact_number) {
            try {
                $phone = $appointment->patient->contact_number;

                // Convert 09XXXXXXXXX → 639XXXXXXXXX
                if (preg_match('/^09\d{9}$/', $phone)) {
                    $phone = '63' . substr($phone, 1);
                }

                $message = 'Your appointment request has been rejected. '
                    . 'Ref: ' . $appointment->reference_no
                    . '. Please log in to your Clinic RMS account for more details. - Clinic RMS';

                $smsService->send($phone, $message);
            } catch (Throwable $e) {
                // SMS failure must not cancel the appointment rejection.
                report($e);
            }
        }

        return redirect()->back()->with('success', 'Appointment rejected successfully!');
    }

    // ================= CANCEL APPOINTMENT =================
    public function cancel($id, IProgSmsService $smsService)
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

        // SMS notification
        if ($appointment->patient && $appointment->patient->contact_number) {
            try {
                $phone = $appointment->patient->contact_number;

                // Convert 09XXXXXXXXX → 639XXXXXXXXX
                if (preg_match('/^09\d{9}$/', $phone)) {
                    $phone = '63' . substr($phone, 1);
                }

                $message = 'Your appointment has been cancelled. '
                    . 'Ref: ' . $appointment->reference_no
                    . '. Please log in to your Clinic RMS account for more details. - Clinic RMS';

                $smsService->send($phone, $message);
            } catch (Throwable $e) {
                // SMS failure must not cancel the appointment cancellation.
                report($e);
            }
        }

        return redirect()->back()->with('success', 'Appointment cancelled successfully!');
    }

    // ================= RESCHEDULE APPOINTMENT =================
    public function reschedule(Request $request, $id, IProgSmsService $smsService)
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

        // SMS notification
        if ($appointment->patient && $appointment->patient->contact_number) {
            try {
                $phone = $appointment->patient->contact_number;

                // Convert 09XXXXXXXXX → 639XXXXXXXXX
                if (preg_match('/^09\d{9}$/', $phone)) {
                    $phone = '63' . substr($phone, 1);
                }

                $message = 'Your appointment has been rescheduled. '
                    . 'Ref: ' . $appointment->reference_no
                    . '. New schedule: ' . $this->appointmentSchedule($appointment)
                    . '. Please arrive on time. - Clinic RMS';

                $smsService->send($phone, $message);
            } catch (Throwable $e) {
                // SMS failure must not cancel the appointment reschedule.
                report($e);
            }
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