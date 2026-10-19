<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryRequestTest;
use App\Models\LaboratoryResultCorrection;
use App\Models\UserLog;
use App\Services\IProgSmsService;
use App\Services\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Throwable;

class LaboratoryRequestController extends Controller
{
    /**
     * Save Laboratory Request — created from the Consultation page
     * (Step 3, before Prescription). Only usable while the consultation
     * is In Progress, the same window Prescriptions and the Assessment
     * form are editable in. Creating a request never touches the
     * appointment's own status (Section 4/12 of the workflow spec) —
     * the Doctor can still complete the consultation normally right
     * after saving/printing this.
     */
    public function store(Request $request, $appointmentId)
    {
        $request->validate([
            'tests'                  => 'required|array|min:1',
            'tests.*'                => 'required|string|in:' . implode(',', LaboratoryRequest::TEST_CATALOG),
            'lab_diagnosis'           => 'nullable|string|max:5000',
            'other_test_description'  => 'nullable|string|max:255',
        ]);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($appointmentId);

        if ($appointment->status !== 'In Progress') {
            return redirect()->back()
                ->with('error', 'A Laboratory Request can only be created while the consultation is In Progress.');
        }

        if (in_array('Others', $request->tests, true) && empty(trim((string) $request->other_test_description))) {
            return redirect()->back()
                ->withErrors(['other_test_description' => 'Please describe the "Others" test.'])
                ->withInput();
        }

        $laboratoryRequest = DB::transaction(function () use ($appointment, $request) {
            $laboratoryRequest = LaboratoryRequest::create([
                'appointment_id'          => $appointment->id,
                'patient_id'              => $appointment->patient_id,
                'walkin_patient_id'       => $appointment->walkin_patient_id,
                'doctor_id'               => $appointment->doctor_id,
                'diagnosis'               => $request->lab_diagnosis,
                'other_test_description'  => in_array('Others', $request->tests, true)
                    ? $request->other_test_description
                    : null,
            ]);

            foreach (array_unique($request->tests) as $testName) {
                LaboratoryRequestTest::create([
                    'laboratory_request_id' => $laboratoryRequest->id,
                    'test_name'             => $testName,
                ]);
            }

            return $laboratoryRequest;
        });

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Created Laboratory Request',
            'module'  => 'Laboratory',
            'details' => 'Created laboratory request ' . $laboratoryRequest->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' (appointment ' . $appointment->reference_no . ').',
        ]);

        // Laboratory Request lives on Step 3 alongside Prescription — stay there.
        session(["consultation_step_{$appointment->id}" => 3]);

        return redirect()->back()->with('success', 'Laboratory request saved. Status: Awaiting Result.');
    }

    /**
     * Edit Laboratory Request — lets the Doctor correct a request saved
     * by mistake (e.g. an extra test checked). Updates the SAME record
     * (same ID / reference_no) and synchronizes its test rows; it never
     * creates a new request. Only allowed while the consultation is In
     * Progress (same window as store() / Prescriptions) and while the
     * request is still editable (Awaiting Result, no result uploaded).
     * Printing does not lock it.
     */
    public function update(Request $request, $laboratoryRequestId)
    {
        $request->validate([
            'tests'                  => 'required|array|min:1',
            'tests.*'                => 'required|string|in:' . implode(',', LaboratoryRequest::TEST_CATALOG),
            'lab_diagnosis'           => 'nullable|string|max:5000',
            'other_test_description'  => 'nullable|string|max:255',
        ]);

        $laboratoryRequest = LaboratoryRequest::with('tests')
            ->where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequestId);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequest->appointment_id);

        session(["consultation_step_{$appointment->id}" => 3]);

        if ($appointment->status !== 'In Progress') {
            return redirect()->back()
                ->with('error', 'A Laboratory Request can only be edited while the consultation is In Progress.');
        }

        if (!$laboratoryRequest->isEditable()) {
            return redirect()->back()
                ->with('error', 'This laboratory request has already entered the result workflow and can no longer be edited.');
        }

        if (in_array('Others', $request->tests, true) && empty(trim((string) $request->other_test_description))) {
            return redirect()->back()
                ->withErrors(['other_test_description' => 'Please describe the "Others" test.'])
                ->withInput();
        }

        $oldTests = $laboratoryRequest->tests->pluck('test_name')->all();
        $newTests = array_values(array_unique($request->tests));

        $updated = DB::transaction(function () use ($laboratoryRequest, $request, $newTests) {
            // Re-check under a row lock so a result uploaded at the same
            // moment cannot slip through between the check and the write.
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || !$locked->isEditable()) {
                return false;
            }

            $locked->update([
                'diagnosis'               => $request->lab_diagnosis,
                'other_test_description'  => in_array('Others', $newTests, true)
                    ? $request->other_test_description
                    : null,
            ]);

            // Synchronize test rows: remove unchecked, add newly checked,
            // leave unchanged ones untouched.
            $locked->tests()->whereNotIn('test_name', $newTests)->delete();

            $existing = $locked->tests()->pluck('test_name')->all();
            foreach (array_diff($newTests, $existing) as $testName) {
                LaboratoryRequestTest::create([
                    'laboratory_request_id' => $locked->id,
                    'test_name'             => $testName,
                ]);
            }

            return true;
        });

        if (!$updated) {
            return redirect()->back()
                ->with('error', 'This laboratory request has already entered the result workflow and can no longer be edited.');
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Laboratory Request',
            'module'  => 'Laboratory',
            'details' => 'Updated laboratory request ' . $laboratoryRequest->reference_no
                       . ' for ' . $appointment->patientName()
                       . ' (tests: ' . implode(', ', $oldTests) . ' → ' . implode(', ', $newTests) . ').',
        ]);

        return redirect()->back()->with('success', 'Laboratory request ' . $laboratoryRequest->reference_no . ' updated.');
    }

    /**
     * Delete Laboratory Request — for when the Doctor decides no
     * laboratory test is needed after all. Hard delete, same convention
     * as PrescriptionController::destroy(); its test rows are removed
     * with it. Same In Progress + editable guards as update().
     */
    public function destroy($laboratoryRequestId)
    {
        $laboratoryRequest = LaboratoryRequest::where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequestId);

        $appointment = Appointment::where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequest->appointment_id);

        session(["consultation_step_{$appointment->id}" => 3]);

        if ($appointment->status !== 'In Progress') {
            return redirect()->back()
                ->with('error', 'A Laboratory Request can only be deleted while the consultation is In Progress.');
        }

        if (!$laboratoryRequest->isEditable()) {
            return redirect()->back()
                ->with('error', 'This laboratory request has already entered the result workflow and can no longer be deleted.');
        }

        $referenceNo = $laboratoryRequest->reference_no;

        $deleted = DB::transaction(function () use ($laboratoryRequest) {
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || !$locked->isEditable()) {
                return false;
            }

            $locked->tests()->delete();
            $locked->delete();

            return true;
        });

        if (!$deleted) {
            return redirect()->back()
                ->with('error', 'This laboratory request has already entered the result workflow and can no longer be deleted.');
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Deleted Laboratory Request',
            'module'  => 'Laboratory',
            'details' => 'Deleted laboratory request ' . $referenceNo
                       . ' for ' . $appointment->patientName()
                       . ' (appointment ' . $appointment->reference_no . ').',
        ]);

        return redirect()->back()->with('success', 'Laboratory request ' . $referenceNo . ' deleted.');
    }

    /**
     * Printable Laboratory Request handed to the patient (Section 3 of
     * the workflow spec). Never changes the request's status — it
     * remains Awaiting Result until an external result is uploaded.
     */
    public function print($laboratoryRequestId)
    {
        $laboratoryRequest = LaboratoryRequest::with(['appointment', 'patient', 'walkinPatient', 'doctor', 'tests'])
            ->where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequestId);

        return view('doctor.laboratory-request-print', compact('laboratoryRequest'));
    }

    /**
     * Doctor's centralized Laboratory work queue (Section 7). Shows
     * every laboratory request this Doctor has created, across all
     * appointments, with search + status + date filters.
     */
    public function index(Request $request)
    {
        $doctor = auth()->user();

        $search = trim((string) $request->input('search', ''));

        $statusFilter = $request->input('status', 'All');
        if (!in_array($statusFilter, ['Awaiting Result', 'Result Received', 'Correction Required', 'Reviewed'], true)) {
            $statusFilter = 'All';
        }

        $dateFilter = $request->input('date_filter', 'All Dates');
        if (!in_array($dateFilter, ['Today', 'This Week', 'This Month'], true)) {
            $dateFilter = 'All Dates';
        }

        $query = LaboratoryRequest::with(['patient', 'walkinPatient', 'tests', 'appointment'])
            ->where('doctor_id', $doctor->id);

        if ($statusFilter !== 'All') {
            $query->where('status', $statusFilter);
        }

        if ($dateFilter === 'Today') {
            $query->whereDate('requested_at', Carbon::today());
        } elseif ($dateFilter === 'This Week') {
            $query->whereBetween('requested_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'This Month') {
            $query->whereBetween('requested_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($pq) use ($search) {
                      $pq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('walkinPatient', function ($wq) use ($search) {
                      $wq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $laboratoryRequests = $query
            ->orderByDesc('requested_at')
            ->paginate(10)
            ->appends($request->query());

        return view('doctor.laboratory-requests.index', compact('laboratoryRequests', 'search', 'statusFilter', 'dateFilter'));
    }

    /**
     * Laboratory Result review page (Section 8). Opened either from the
     * work queue, the notification bell ("Review Now"), or the Medical
     * Record's "View Laboratory Result" shortcut — never requires the
     * Doctor to search for the patient manually.
     */
    public function show($laboratoryRequestId)
    {
        $laboratoryRequest = LaboratoryRequest::with([
            'appointment',
            'patient',
            'walkinPatient',
            'doctor',
            'tests',
            'results.uploader',
            'reviewedBy',
            'corrections.requestedBy',
            'corrections.resolvingResult',
        ])
            ->where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequestId);

        return view('doctor.laboratory-requests.show', compact('laboratoryRequest'));
    }

    /**
     * Mark as Reviewed — the ONLY action that may move a Laboratory
     * Request from Result Received to Reviewed. Never changes the
     * appointment's status (Section 8/12 of the workflow spec).
     */
    public function review(Request $request, $laboratoryRequestId, IProgSmsService $smsService)
    {
        // review_notes (Interpretation / Remarks) is PATIENT-FACING.
        $request->validate([
            'review_notes' => 'nullable|string|max:5000',
        ]);

        $laboratoryRequest = LaboratoryRequest::where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequestId);

        // Row lock so an upload/replace or correction request at the same
        // moment can't slip in between the status check and the write.
        $reviewed = DB::transaction(function () use ($laboratoryRequest, $request) {
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== 'Result Received') {
                return false;
            }

            $locked->update([
                'status'                 => 'Reviewed',
                'reviewed_by'            => auth()->id(),
                'reviewed_at'            => now(),
                'review_notes'           => $request->review_notes,
            ]);

            return true;
        });

        if (!$reviewed) {
            return redirect()->back()
                ->with('error', 'Only a request with a received result can be marked as reviewed.');
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Reviewed Laboratory Result',
            'module'  => 'Laboratory',
            'details' => 'Reviewed laboratory result for request ' . $laboratoryRequest->reference_no
                       . ' (' . $laboratoryRequest->patientName() . ').',
        ]);

        // SMS notification — registered patients only (walk-in patients
        // have no account/contact_number on this model).
        if (SystemSettings::notificationEnabled('sms', 'lab_reviewed')
            && $laboratoryRequest->patient && $laboratoryRequest->patient->contact_number) {
            try {
                $phone = $laboratoryRequest->patient->contact_number;

                // Convert 09XXXXXXXXX → 639XXXXXXXXX
                if (preg_match('/^09\d{9}$/', $phone)) {
                    $phone = '63' . substr($phone, 1);
                }

                $message = 'Your laboratory result has been reviewed by your doctor. '
                    . 'Please log in to your Clinic RMS account to view your result. ' . SystemSettings::smsSignature();

                $smsService->send($phone, $message);
            } catch (Throwable $e) {
                // SMS failure must not cancel the laboratory result review.
                report($e);
            }
        }

        return redirect()
            ->route('doctor.laboratory-requests.show', $laboratoryRequest->id)
            ->with('success', 'Laboratory result marked as reviewed.');
    }

    /**
     * Request Correction — the Doctor found the uploaded result wrong,
     * incomplete, or otherwise unusable and needs a new file, WITHOUT
     * deleting what was already uploaded (Section 4 of the correction
     * workflow spec: no silent deletion, full audit trail). Only valid
     * from Result Received (there must be something to correct); moves
     * the request to Correction Required and opens a new pending
     * LaboratoryResultCorrection row carrying the reason. The patient
     * (or Staff, if they re-upload for the patient) can then upload a
     * new file, which resolves this correction and returns the request
     * to Result Received for another review pass.
     */
    public function requestCorrection(Request $request, $laboratoryRequestId)
    {
        $laboratoryRequest = LaboratoryRequest::with('tests')
            ->where('doctor_id', auth()->id())
            ->findOrFail($laboratoryRequestId);

        // "Which test needs correction?" is required and restricted to
        // exactly this request's own requested tests (Section 5 of the
        // correction workflow spec) — a correction must always be able
        // to identify the specific test it concerns.
        $requestedTestNames = $laboratoryRequest->tests->pluck('test_name')->all();

        $request->validate([
            'reason'    => 'required|string|max:2000',
            'test_name' => ['required', Rule::in($requestedTestNames)],
        ]);

        $requested = DB::transaction(function () use ($laboratoryRequest, $request) {
            // Row lock so a result uploaded (or a review) at the same
            // moment can't race past this check.
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)
                ->lockForUpdate()
                ->first();

            // A correction may be requested from Result Received (the
            // first pass) OR Correction Required (flagging ANOTHER test
            // while one is already pending — Section 8 of the spec: CBC
            // and FBS can each be independently under correction).
            if (!$locked || !in_array($locked->status, ['Result Received', 'Correction Required'], true)) {
                return null;
            }

            // One pending correction per test at a time — requesting it
            // again for a test that's already flagged would just create
            // a confusing duplicate the Patient/Staff can't tell apart.
            $alreadyPending = $locked->corrections()
                ->where('status', 'pending')
                ->where('test_name', $request->test_name)
                ->exists();

            if ($alreadyPending) {
                return 'duplicate';
            }

            $correction = LaboratoryResultCorrection::create([
                'laboratory_request_id' => $locked->id,
                'requested_by'          => auth()->id(),
                'test_name'             => $request->test_name,
                'reason'                => $request->reason,
                'status'                => 'pending',
            ]);

            $locked->recomputeStatus();

            return $correction;
        });

        if ($requested === null) {
            return redirect()->back()
                ->with('error', 'A correction can only be requested for a result that has been received but not yet reviewed.');
        }

        if ($requested === 'duplicate') {
            return redirect()->back()
                ->with('error', 'A correction is already pending for ' . $request->test_name . '.');
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Requested Laboratory Result Correction',
            'module'  => 'Laboratory',
            'details' => 'Requested a correction on laboratory request ' . $laboratoryRequest->reference_no
                       . ' (' . $laboratoryRequest->patientName() . '), test: ' . $request->test_name . ' — ' . $request->reason,
        ]);

        return redirect()
            ->route('doctor.laboratory-requests.show', $laboratoryRequest->id)
            ->with('success', $request->test_name . ' correction requested. The patient has been notified and can upload a new result.');
    }
}