<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\Service;
use App\Models\UserLog;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DoctorAppointmentController extends Controller
{
    protected BillingService $billing;

    public function __construct(BillingService $billing)
    {
        $this->billing = $billing;
    }

    /**
     * "September 10, 2026 at 10:00 AM" — used in Activity Log descriptions,
     * same helper/format as StaffAppointmentController::appointmentSchedule().
     */
    private function appointmentSchedule(Appointment $appointment): string
    {
        $date = Carbon::parse($appointment->appointment_date)->format('F d, Y');
        $time = Carbon::parse($appointment->appointment_time)->format('h:i A');

        return "{$date} at {$time}";
    }

    /**
     * Session key that remembers which consultation-page step this
     * Doctor currently has open for this appointment. Session-only —
     * this is UI state, never persisted as medical data — so it
     * naturally survives a reload/F5 without polluting the record.
     */
    private function stepSessionKey(int $appointmentId): string
    {
        return "consultation_step_{$appointmentId}";
    }

    /**
     * Remembers the active step for this appointment's consultation
     * page. Called after every save/redirect-producing action below
     * (and via AJAX on plain step navigation) so the Doctor is always
     * returned to where they left off instead of Step 1.
     */
    private function rememberStep(Appointment $appointment, int $step): void
    {
        session([$this->stepSessionKey($appointment->id) => max(1, min(4, $step))]);
    }

    /**
     * Persists the clinical fields shared by Save Draft and Complete
     * Consultation onto this appointment's MedicalRecord row. Both
     * patient_id and walkin_patient_id are written (only one will ever
     * be non-null for a given appointment) so the record correctly
     * resolves for BOTH registered and walk-in patients.
     */
    private function upsertMedicalRecord(Appointment $appointment, Request $request): void
    {
        MedicalRecord::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id'                  => $appointment->patient_id,
                'walkin_patient_id'           => $appointment->walkin_patient_id,
                'doctor_id'                   => $appointment->doctor_id,
                'chief_complaint'             => $request->chief_complaint,
                'history_of_present_illness'  => $request->history_of_present_illness,
                'physical_examination'        => $request->physical_examination,
                'diagnosis'                   => $request->diagnosis,
                'treatment'                   => $request->treatment,
                'notes'                       => $request->notes,
                'blood_pressure'              => $request->blood_pressure,
                'temperature'                 => $request->temperature,
                'weight'                      => $request->weight,
                'height'                      => $request->height,
            ]
        );
    }

    /**
     * Statuses shown on the active Doctor Appointment list. Also used to
     * validate the incoming ?status= filter so an unexpected value can
     * never widen the query beyond these four (e.g. to Completed/Pending).
     */
    private const ACTIVE_STATUSES = ['Approved', 'Rescheduled', 'Checked In', 'In Progress'];

    /**
     * Restricts a patient-name relation query (patient or walkinPatient)
     * to rows whose first/last name match the search term. Handles a
     * single-word search ("juan") and a two-word "first last" search
     * ("juan dela cruz" / "dela cruz juan") without relying on a
     * database-specific concat function, so this works the same on the
     * app's MySQL and SQLite connections.
     */
    private function applyNameSearch($relationQuery, string $search): void
    {
        $relationQuery->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%");

            $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) >= 2) {
                $first = $words[0];
                $last  = implode(' ', array_slice($words, 1));

                $q->orWhere(function ($iq) use ($first, $last) {
                    $iq->where('first_name', 'like', "%{$first}%")
                       ->where('last_name', 'like', "%{$last}%");
                });
            }
        });
    }

    /**
     * Doctor Appointment list. Supports an optional search box (patient
     * name — registered or walk-in — or reference number), a status
     * filter (restricted to the same four active statuses already shown
     * here), and a date filter (Today / Upcoming), all applied on top of
     * the existing active-status query and pagination.
     */
    public function index(Request $request)
    {
        $doctor = auth()->user();

        $search = trim((string) $request->input('search', ''));

        $statusFilter = $request->input('status', 'All Active');
        if (!in_array($statusFilter, self::ACTIVE_STATUSES, true)) {
            $statusFilter = 'All Active';
        }

        $dateFilter = $request->input('date_filter', 'All Dates');
        if (!in_array($dateFilter, ['Today', 'Upcoming'], true)) {
            $dateFilter = 'All Dates';
        }

        $query = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', self::ACTIVE_STATUSES);

        if ($statusFilter !== 'All Active') {
            $query->where('status', $statusFilter);
        }

        if ($dateFilter === 'Today') {
            $query->whereDate('appointment_date', Carbon::today());
        } elseif ($dateFilter === 'Upcoming') {
            $query->whereDate('appointment_date', '>', Carbon::today());
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $this->applyNameSearch($pq, $search);
                  })
                  ->orWhereHas('walkinPatient', function ($wq) use ($search) {
                      $this->applyNameSearch($wq, $search);
                  });
            });
        }

        $appointments = $query
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->paginate(10)
            ->appends($request->query());

        return view('doctor.appointments', compact('appointments', 'search', 'statusFilter', 'dateFilter'));
    }

    /**
     * Update an appointment status.
     *
     * At the moment this endpoint is used by the Doctor appointment page
     * for confirming a No Show. The No Show action is only allowed after
     * the appointment's 30-minute time slot has ended, and only while the
     * appointment is still Approved or Rescheduled (i.e. before Staff has
     * checked the patient in).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:No Show',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        if (!in_array($appointment->status, ['Approved', 'Rescheduled'], true)) {
            return redirect()->back()
                ->with('error', 'Only approved or rescheduled appointments can be marked as No Show.');
        }

        // Appointment slots in ClinicRMS are 30 minutes long.
        // The stored appointment_time is the slot start time.
        $appointmentStart = Carbon::parse(
            $appointment->appointment_date->format('Y-m-d') . ' ' . $appointment->appointment_time
        );
        $appointmentEnd = $appointmentStart->copy()->addMinutes(30);

        if (Carbon::now()->lt($appointmentEnd)) {
            return redirect()->back()
                ->with('error', 'No Show is only available after the scheduled appointment time has ended.');
        }

        $appointment->update([
            'status' => 'No Show',
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Marked Appointment as No Show (Doctor)',
            'module'  => 'Appointments',
            'details' => 'Marked appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' scheduled on ' . $this->appointmentSchedule($appointment)
                       . ' as No Show.',
        ]);

        return redirect()->back()->with('success', 'Appointment marked as No Show.');
    }

    /**
     * Consultation page. Backend-enforced workflow gate — a Doctor must
     * not be able to open this page for an appointment Staff has not yet
     * checked in, even by typing/guessing the URL directly, so this check
     * happens here rather than only being hidden in the Blade view.
     */
    public function show($id)
    {
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->with(['patient', 'walkinPatient'])
            ->findOrFail($id);

        // Not yet checked in — no consultation access at all.
        if (in_array($appointment->status, ['Pending', 'Approved', 'Rescheduled'], true)) {
            return redirect()
                ->route('doctor.appointments.index')
                ->with('error', 'This appointment is waiting for Staff Check-In before consultation can begin.');
        }

        // Never consultable.
        if (in_array($appointment->status, ['Cancelled', 'Rejected', 'No Show'], true)) {
            return redirect()
                ->route('doctor.appointments.index')
                ->with('error', 'This appointment cannot be consulted on.');
        }

        // The same-day restriction only applies while the consultation is
        // still ongoing (Checked In / In Progress). A Completed
        // consultation stays viewable (read-only) after its date has
        // passed, same as Medical Records / Print Report already allow.
        if (in_array($appointment->status, ['Checked In', 'In Progress'], true)) {
            $appointmentDate = Carbon::parse($appointment->appointment_date)->startOfDay();
            if (!$appointmentDate->equalTo(Carbon::today())) {
                return redirect()
                    ->route('doctor.appointments.index')
                    ->with('error', 'You can only consult on the scheduled date.');
            }
        }

        $medicines = Medicine::where('status', '!=', 'Out of Stock')->get();

        $prescriptions = Prescription::where('appointment_id', $id)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $id)->first();

        $medicalRecord = MedicalRecord::where('appointment_id', $id)->first();

        // ── Previous Medical History (read-only) ────────────────────────
        // All earlier medical records for the SAME patient (registered or
        // walk-in), excluding this appointment's own record, newest first.
        $previousRecordsQuery = MedicalRecord::with(['appointment'])
            ->where('appointment_id', '!=', $id);

        if ($appointment->patient_id) {
            $previousRecordsQuery->where('patient_id', $appointment->patient_id);
        } elseif ($appointment->walkin_patient_id) {
            $previousRecordsQuery->where('walkin_patient_id', $appointment->walkin_patient_id);
        } else {
            $previousRecordsQuery->whereRaw('1 = 0');
        }

        $previousRecords = $previousRecordsQuery->latest()->get();

        $services = Service::where('is_active', true)->orderBy('name')->get();
        $invoice  = $appointment->invoice()->with('items')->first();

        return view('doctor.show-appointment', compact(
            'appointment',
            'medicines',
            'prescriptions',
            'review',
            'medicalRecord',
            'previousRecords',
            'services',
            'invoice'
        ));
    }

    /**
     * Records which consultation step the Doctor is currently viewing.
     * Called by the page's stepper JS on every navigation (Next, Back,
     * progress-bar tab) so a reload/F5 reopens the same step. Pure UI
     * state — nothing here touches the medical record.
     */
    public function setStep(Request $request, $id)
    {
        $request->validate([
            'step' => 'required|integer|min:1|max:4',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        $this->rememberStep($appointment, (int) $request->step);

        return response()->noContent();
    }

    /**
     * Doctor confirms the patient (already Checked In by Staff) is now
     * being seen. Checked In -> In Progress. This is the only action that
     * may perform that transition — the Doctor cannot bypass Check-In by
     * any other route, since every other consultation-editing endpoint
     * below requires the appointment to already be In Progress.
     */
    public function startConsultation($id)
    {
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        if (!$appointment->canStartConsultation()) {
            return redirect()->back()
                ->with('error', 'Only a Checked In appointment can start consultation.');
        }

        $appointment->update(['status' => 'In Progress']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Started Consultation',
            'module'  => 'Appointments',
            'details' => 'Started consultation for appointment ' . $appointment->reference_no
                       . ' (' . $appointment->patientName() . ').',
        ]);

        return redirect()
            ->route('doctor.appointments.show', $appointment->id)
            ->with('success', 'Consultation started.');
    }

    /**
     * Save Draft — persists whatever clinical fields are filled in so
     * far. Incomplete fields are allowed on purpose; the appointment
     * status is left unchanged (In Progress) so the Doctor can keep
     * coming back to it. Never usable outside In Progress.
     */
    public function saveDraft(Request $request, $id)
    {
        $request->validate([
            'chief_complaint'            => 'nullable|string|max:5000',
            'history_of_present_illness' => 'nullable|string|max:5000',
            'physical_examination'       => 'nullable|string|max:5000',
            'diagnosis'                  => 'nullable|string|max:5000',
            'treatment'                  => 'nullable|string|max:5000',
            'notes'                      => 'nullable|string|max:5000',
            'blood_pressure'             => 'nullable|string|max:50',
            'temperature'                => 'nullable|string|max:50',
            'weight'                     => 'nullable|string|max:50',
            'height'                     => 'nullable|string|max:50',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        if ($appointment->status !== 'In Progress') {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'The consultation must be In Progress to save a draft.',
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'The consultation must be In Progress to save a draft.');
        }

        $this->upsertMedicalRecord($appointment, $request);

        // This endpoint is now only reached via the Next button on the
        // Assessment step, so a successful save always means the Doctor
        // is moving on to Step 3 (Prescription & Review).
        $this->rememberStep($appointment, 3);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Progress saved.']);
        }

        return redirect()->back()
            ->with('success', 'Progress saved.');
    }

    /**
     * Complete Consultation — the ONLY action that may move an
     * appointment from In Progress to Completed. Requires Chief
     * Complaint, Diagnosis, and Treatment/Plan; everything else stays
     * optional. Medical record + status change are saved atomically so
     * the appointment is never left Completed with no record (or vice
     * versa) if something fails mid-way.
     */
    public function completeConsultation(Request $request, $id)
    {
        $request->validate([
            'chief_complaint'            => 'required|string|max:5000',
            'history_of_present_illness' => 'nullable|string|max:5000',
            'physical_examination'       => 'nullable|string|max:5000',
            'diagnosis'                  => 'required|string|max:5000',
            'treatment'                  => 'required|string|max:5000',
            'notes'                      => 'nullable|string|max:5000',
            'blood_pressure'             => 'nullable|string|max:50',
            'temperature'                => 'nullable|string|max:50',
            'weight'                     => 'nullable|string|max:50',
            'height'                     => 'nullable|string|max:50',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        if ($appointment->status !== 'In Progress') {
            return redirect()->back()
                ->with('error', 'Only an In Progress consultation can be completed.');
        }

        DB::transaction(function () use ($request, $appointment) {
            $this->upsertMedicalRecord($appointment, $request);
            $appointment->update(['status' => 'Completed']);
        });

        $this->rememberStep($appointment, 4);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Completed Consultation',
            'module'  => 'Appointments',
            'details' => 'Completed consultation for appointment ' . $appointment->reference_no
                       . ' (' . $appointment->patientName() . ').',
        ]);

        return redirect()
            ->route('doctor.appointments.show', $appointment->id)
            ->with('success', 'Consultation completed successfully!');
    }

    /**
     * Doctor tags the applicable service(s)/charge for this appointment.
     * This does NOT record any payment and does NOT let the Doctor mark
     * anything as paid — it only creates/refreshes the invoice (always
     * starting/remaining UNPAID here) so Staff can collect payment at
     * the counter. Locked once a payment has been recorded.
     *
     * The route middleware only guarantees the user holds AT LEAST ONE
     * of create_invoice / edit_invoice (see routes/web.php). Which one
     * is actually REQUIRED depends on whether this appointment already
     * has an invoice, so that distinction is enforced here:
     *   - no existing invoice            -> create_invoice required
     *   - existing invoice, no payment   -> edit_invoice required
     *   - existing invoice, has payment  -> locked regardless of permission
     * A Doctor with only create_invoice can never modify an existing
     * invoice, and a Doctor with only edit_invoice can never create a
     * new one.
     */
    public function saveCharges(Request $request, $id)
    {
        $request->validate([
            'service_ids'       => 'nullable|array',
            'service_ids.*'     => 'exists:services,id',
            'service_qty'       => 'nullable|array',
            'service_qty.*'     => 'nullable|integer|min:1',
            // Only prescriptions the Doctor explicitly checked here are
            // billed — a prescription on its own never creates a charge
            // (Prescription != Purchase).
            'medicine_rx_ids'   => 'nullable|array',
            'medicine_rx_ids.*' => 'integer',
            'medicine_rx_qty'   => 'nullable|array',
            'medicine_rx_qty.*' => 'nullable|integer|min:1',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($id);

        $existingInvoice = $appointment->invoice()->first();
        if ($existingInvoice && $existingInvoice->amount_paid > 0) {
            return redirect()->back()
                ->with('error', 'Charges can no longer be edited — a payment has already been recorded for this invoice.');
        }

        $requiredPermission = $existingInvoice ? 'edit_invoice' : 'create_invoice';
        abort_unless(auth()->user()->hasPermission($requiredPermission), 403,
            'You do not have permission to perform this action.');

        $serviceIds = $request->input('service_ids', []);
        $serviceQty = $request->input('service_qty', []);

        $lines = [];
        foreach ($serviceIds as $sid) {
            $lines[] = [
                'service_id' => (int) $sid,
                'quantity'   => (int) ($serviceQty[$sid] ?? 1),
            ];
        }

        $medicineRxIds = $request->input('medicine_rx_ids', []);
        $medicineRxQty = $request->input('medicine_rx_qty', []);

        // Re-fetch the checked prescriptions ourselves (never trust the
        // client for the price/quantity ceiling) and cap each one at
        // its own quantity_prescribed — the Doctor can bill less than
        // was prescribed (e.g. patient buys a partial supply) but never
        // more.
        $checkedPrescriptions = Prescription::where('appointment_id', $appointment->id)
            ->whereIn('id', $medicineRxIds)
            ->whereNotNull('medicine_id')
            ->get();

        $medicineLines = [];
        foreach ($checkedPrescriptions as $rx) {
            $qty = (int) ($medicineRxQty[$rx->id] ?? $rx->quantity_prescribed);
            $qty = max(1, min($qty, (int) $rx->quantity_prescribed));

            $medicineLines[] = [
                'prescription_id' => $rx->id,
                'quantity'        => $qty,
            ];
        }

        $this->billing->syncInvoiceForAppointment($appointment, $lines, auth()->user(), $medicineLines);

        $this->rememberStep($appointment, 4);

        return redirect()->back()->with('success', 'Charges saved. Billing has been updated.');
    }

    public function report($id)
    {
        $appointment = Appointment::where('doctor_id', auth()->id())
            ->with('patient')
            ->findOrFail($id);

        $prescriptions = Prescription::where('appointment_id', $id)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $id)->first();

        $medicalRecord = MedicalRecord::where('appointment_id', $id)->first();

        return view('doctor.report', compact(
            'appointment',
            'prescriptions',
            'review',
            'medicalRecord'
        ));
    }

    public function reschedule(Request $request, $id)
    {
        $request->validate([
            'appointment_date'  => 'required|date|after_or_equal:today',
            'appointment_time'  => 'required|date_format:H:i',
            'reschedule_reason' => 'required|string|max:500',
        ]);

        $appointment = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', auth()->id())
            ->findOrFail($id);

        if (!in_array($appointment->status, ['Approved', 'Rescheduled'])) {
            return redirect()->back()->with('error', 'This appointment cannot be rescheduled.');
        }

        $isTaken = Appointment::where('doctor_id', auth()->id())
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereNotIn('status', ['Rejected', 'Cancelled', 'Completed'])
            ->where('id', '!=', $id)
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
            'action'  => 'Rescheduled Appointment (Doctor)',
            'module'  => 'Appointments',
            'details' => 'Rescheduled appointment ' . $appointment->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' to ' . $this->appointmentSchedule($appointment)
                       . '. Reason: ' . $request->reschedule_reason,
        ]);

        return redirect()->back()->with('success', 'Appointment rescheduled successfully!');
    }

    /**
     * Save the review/follow-up. This NEVER completes the appointment —
     * only completeConsultation() may set status to Completed (Section
     * 11 of the workflow spec). Only usable while In Progress, same as
     * every other consultation-editing endpoint.
     */
    public function storeReview(Request $request)
    {
        $request->validate([
            'appointment_id'   => 'required|exists:appointments,id',
            'next_review_date' => 'nullable|date',
            'message'          => 'nullable|string',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($request->appointment_id);

        if ($appointment->status !== 'In Progress') {
            return redirect()->back()
                ->with('error', 'The review can only be saved while the consultation is In Progress.');
        }

        Review::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'next_review_date' => $request->next_review_date,
                'message'          => $request->message,
            ]
        );

        // Review lives on Step 3 (Prescription & Review) — stay there.
        $this->rememberStep($appointment, 3);

        return redirect()->back()->with('success', 'Review notes saved.');
    }

    /**
     * ─── THIS IS THE MISSING METHOD ───────────────────────────────────────────
     * Lists all medical records for the logged-in doctor.
     * Passes $records to the index blade view.
     */
    public function medicalRecordsIndex()
    {
        $records = MedicalRecord::with(['appointment.patient', 'appointment.walkinPatient'])
            ->where('doctor_id', auth()->id())
            ->latest()
            ->paginate(10); 

        return view('doctor.medical-records', compact('records'));
    }

    /**
     * Show medical record (view-only)
     */
    public function showMedicalRecord($appointmentId)
    {
        $appointment = Appointment::with([
            'patient',
            'walkinPatient',
            'medicalRecord',
            'prescriptions.medicine',
            'review',
            'latestMedicalCertificate',
        ])->findOrFail($appointmentId);

        $medicalRecord = MedicalRecord::where('appointment_id', $appointmentId)->first();

        $prescriptions = Prescription::where('appointment_id', $appointmentId)
            ->with('medicine')
            ->get();

        $review = Review::where('appointment_id', $appointmentId)->first();

        // ← changed from 'doctor.medical-records' to 'doctor.medical-record-show'
        return view('doctor.medical-record-show', compact(
            'appointment',
            'medicalRecord',
            'prescriptions',
            'review',
        ));
    }
}