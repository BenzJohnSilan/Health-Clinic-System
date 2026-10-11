<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryResult;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffLaboratoryRequestController extends Controller
{
    /**
     * Staff Laboratory Results list (Section 6B of the workflow spec).
     * Gated by 'view_laboratory_requests' at the route level — never
     * granted by default, Admin must grant it via Manage Permissions.
     *
     * Staff only ever gets Upload/Replace actions here — no Review or
     * "mark correct" action exists on this page or controller, by
     * design (Section 6: "Staff should NOT review or interpret the
     * clinical laboratory result"). This is what a Walk-in Patient's
     * laboratory results look like from the Staff side, since a
     * Walk-in Patient has no account of their own to upload with.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $statusFilter = $request->input('status', 'All');
        if (!in_array($statusFilter, ['Awaiting Result', 'Result Received', 'Correction Required', 'Reviewed'], true)) {
            $statusFilter = 'All';
        }

        $query = LaboratoryRequest::with(['patient', 'walkinPatient', 'tests', 'doctor', 'results', 'corrections', 'followUpAppointments']);

        if ($statusFilter !== 'All') {
            $query->where('status', $statusFilter);
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

        // "Schedule Follow-up" hands off to the EXISTING Staff scheduling
        // flow (Patients → Schedule Appointment), so it needs both of
        // that flow's permissions.
        $canScheduleFollowUp = $request->user()->hasPermission('view_patients')
            && $request->user()->hasPermission('schedule_patient_appointment');

        return view('staff.laboratory-requests.index', compact('laboratoryRequests', 'search', 'statusFilter', 'canScheduleFollowUp'));
    }

    /**
     * Staff records a physical/scanned result the patient (registered
     * or walk-in) handed over in person. Same required-dropdown rule
     * as the Patient's own upload (Section 1/3 of the workflow spec):
     * "Which test is this result for?" only ever lists THIS request's
     * own requested tests — never free text, never every test in the
     * system. Gated by 'upload_laboratory_result' at the route level.
     *
     * Also doubles as the "record the corrected result" action while
     * the request is Correction Required, exactly like the Patient's
     * own upload route does.
     */
    public function upload(Request $request, $laboratoryRequestId)
    {
        $staff = $request->user();

        $laboratoryRequest = LaboratoryRequest::with('tests')->findOrFail($laboratoryRequestId);

        $requestedTestNames = $laboratoryRequest->tests->pluck('test_name')->all();

        $request->validate([
            'result_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'test_name'   => ['required', Rule::in(array_merge($requestedTestNames, [LaboratoryRequest::GENERAL_TEST_OPTION]))],
            'notes'       => 'nullable|string|max:1000',
        ]);

        if (!$laboratoryRequest->canAcceptUploads()) {
            return redirect()->back()
                ->with('error', 'This laboratory request has already been reviewed and can no longer accept new uploads.');
        }

        $testName = $request->test_name !== LaboratoryRequest::GENERAL_TEST_OPTION ? $request->test_name : null;

        $outcome = DB::transaction(function () use ($request, $laboratoryRequest, $staff, $testName) {
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)->lockForUpdate()->first();

            if (!$locked || !$locked->canAcceptUploads()) {
                return null;
            }

            $file = $request->file('result_file');
            $path = $file->store('laboratory_results', 'public');

            $result = LaboratoryResult::create([
                'laboratory_request_id' => $locked->id,
                'test_name'             => $testName,
                'file_path'             => $path,
                'original_filename'     => $file->getClientOriginalName(),
                'file_type'             => strtolower($file->getClientOriginalExtension()),
                'file_size'             => $file->getSize(),
                'uploaded_by'           => $staff->id,
                'uploaded_at'           => now(),
                'notes'                 => $request->notes,
            ]);

            // Same "at most one current file per test slot" rule the
            // Patient upload/replace flow follows, so the Doctor's
            // review page and the Uploaded Laboratory Results modal
            // never disagree about which file is current for a test.
            LaboratoryResult::supersede($locked->id, $testName, $result->id);

            $resolvedCorrection = $locked->resolvePendingCorrectionFor($testName, $result->id);
            $locked->recomputeStatus();
            $stillPending = $locked->corrections()->where('status', 'pending')->exists();

            return ['result' => $result, 'resolved' => $resolvedCorrection, 'still_pending' => $stillPending];
        });

        if (!$outcome) {
            return redirect()->back()
                ->with('error', 'This laboratory request has already been reviewed and can no longer accept new uploads.');
        }

        UserLog::create([
            'user_id' => $staff->id,
            'action'  => $outcome['resolved'] ? 'Uploaded Corrected Laboratory Result (Staff)' : 'Uploaded Laboratory Result (Staff)',
            'module'  => 'Laboratory',
            'details' => ($outcome['resolved'] ? 'Uploaded a corrected laboratory result file' : 'Uploaded a laboratory result file')
                       . ' for request ' . $laboratoryRequest->reference_no
                       . ' (' . $laboratoryRequest->patientName() . ').',
        ]);

        if ($outcome['still_pending']) {
            return redirect()->back()->with('success', 'File recorded. Note: a different test on this request is still flagged for correction.');
        }

        return redirect()->back()->with('success', $outcome['resolved']
            ? 'Corrected laboratory result recorded. The doctor has been notified.'
            : 'Laboratory result recorded. The doctor has been notified.');
    }

    /**
     * Replace the CURRENT result for one specific requested test
     * (Section 9/11 of the workflow spec) — the Staff equivalent of the
     * Patient's own replaceResult(). Unlike the Patient version, this is
     * NOT restricted to files Staff themselves uploaded: a Walk-in
     * Patient's file may have been recorded by a different Staff member
     * on an earlier visit, and Staff replacement must not depend on who
     * originally uploaded it (Section 11) — only on being an authorized
     * Staff member (route-level 'upload_laboratory_result' permission)
     * and the request/result still being modifiable.
     *
     * $resultId must be a currently-active (not superseded/removed)
     * file on THIS laboratory request — re-checked here so a
     * manipulated ID cannot replace a Reviewed result or one belonging
     * to a different request (Section 12/13 of the spec).
     */
    public function replaceResult(Request $request, $laboratoryRequestId, $resultId)
    {
        $staff = $request->user();

        $laboratoryRequest = LaboratoryRequest::findOrFail($laboratoryRequestId);

        $existing = LaboratoryResult::where('laboratory_request_id', $laboratoryRequest->id)
            ->findOrFail($resultId);

        $request->validate([
            'result_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notes'       => 'nullable|string|max:1000',
        ]);

        // The test slot being replaced is taken from the EXISTING file's
        // own test_name, never from client input.
        $testName = $existing->test_name;

        $outcome = DB::transaction(function () use ($request, $laboratoryRequest, $existing, $staff, $testName) {
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)->lockForUpdate()->first();

            // Reviewed (or any other non-modifiable state) locks this
            // completely — enforced here, not just hidden in the UI.
            if (!$locked || !$locked->canPatientModifyResults()) {
                return null;
            }

            if ($existing->trashed() || !LaboratoryResult::whereKey($existing->id)->exists()) {
                return null;
            }

            $file = $request->file('result_file');
            $path = $file->store('laboratory_results', 'public');

            $result = LaboratoryResult::create([
                'laboratory_request_id' => $locked->id,
                'test_name'             => $testName,
                'file_path'             => $path,
                'original_filename'     => $file->getClientOriginalName(),
                'file_type'             => strtolower($file->getClientOriginalExtension()),
                'file_size'             => $file->getSize(),
                'uploaded_by'           => $staff->id,
                'uploaded_at'           => now(),
                'notes'                 => $request->notes,
            ]);

            LaboratoryResult::supersede($locked->id, $testName, $result->id);

            $resolvedCorrection = $locked->resolvePendingCorrectionFor($testName, $result->id);
            $locked->recomputeStatus();
            $stillPending = $locked->corrections()->where('status', 'pending')->exists();

            return ['result' => $result, 'resolved' => $resolvedCorrection, 'still_pending' => $stillPending];
        });

        if (!$outcome) {
            return redirect()->back()
                ->with('error', 'This result has already been reviewed and can no longer be replaced.');
        }

        UserLog::create([
            'user_id' => $staff->id,
            'action'  => 'Replaced Laboratory Result (Staff)',
            'module'  => 'Laboratory',
            'details' => 'Replaced the recorded '
                       . ($testName ?? 'General') . ' result on laboratory request '
                       . $laboratoryRequest->reference_no . ' (' . $laboratoryRequest->patientName() . ').',
        ]);

        if ($outcome['still_pending']) {
            return redirect()->back()->with('success', ($testName ? $testName . ' r' : 'R')
                . 'esult replaced successfully. Note: another test on this request is still flagged for correction.');
        }

        return redirect()->back()->with('success', ($testName ? $testName . ' r' : 'R') . 'esult replaced successfully. The doctor has been notified.');
    }
}
