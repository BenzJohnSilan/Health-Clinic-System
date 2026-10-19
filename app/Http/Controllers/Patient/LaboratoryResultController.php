<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryResult;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LaboratoryResultController extends Controller
{
    /**
     * The Patient's own Laboratory Results list (Section 6A of the
     * workflow spec) — every laboratory request tied to appointments
     * booked under this Patient account, with an Upload action while
     * still Awaiting Result.
     */
    public function index(Request $request)
    {
        $patient = $request->user();

        // Search matches either the request's own reference number or
        // any of its requested test names (e.g. typing "CBC" finds every
        // request that includes a CBC test).
        $search = trim((string) $request->query('search', ''));

        // Filter dropdown uses the same raw status values stored in the
        // database — 'Correction Required' included — so the query stays
        // a simple equality check. Only the on-screen LABEL for that
        // option/badge is softened to "Correction Needed" for the Patient,
        // exactly like the existing per-row wording already does.
        $validStatuses = ['All', 'Awaiting Result', 'Result Received', 'Correction Required', 'Reviewed'];
        $statusFilter  = $request->query('status', 'All');
        if (!in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = 'All';
        }

        $laboratoryRequests = LaboratoryRequest::with(['tests', 'results', 'doctor', 'appointment', 'corrections', 'reviewedBy'])
            ->where('patient_id', $patient->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                          ->orWhereHas('tests', function ($testQuery) use ($search) {
                              $testQuery->where('test_name', 'like', "%{$search}%");
                          });
                });
            })
            ->when($statusFilter !== 'All', function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter);
            })
            ->orderByDesc('requested_at')
            ->paginate(10)
            ->withQueryString();

        return view('patient.laboratory-results.index', compact('laboratoryRequests', 'search', 'statusFilter'));
    }

    /**
     * Patient uploads a result file against their OWN laboratory
     * request. Ownership (patient_id === auth()->id()) is enforced here
     * via the query itself — never trust the route id alone — so a
     * Patient can never upload against another patient's request.
     *
     * "Which test is this result for?" is a required dropdown limited
     * to exactly this request's own requested tests (Section 1 of the
     * upload workflow spec) — plus one "covers everything" option that
     * preserves the existing single-file-for-multiple-tests behavior
     * (stored as a null test_name, same as before).
     *
     * Also doubles as the "upload the corrected result" action when the
     * request is Correction Required (Section 7): the same form/route
     * is reused, and a successful upload here resolves the pending
     * correction and returns the request to Result Received for the
     * Doctor to review again.
     */
    public function upload(Request $request, $laboratoryRequestId)
    {
        $patient = $request->user();

        $laboratoryRequest = LaboratoryRequest::with('tests')
            ->where('patient_id', $patient->id)
            ->findOrFail($laboratoryRequestId);

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

        // The "General / All Requested Tests" option is stored as a null
        // test_name — same meaning it already had before this dropdown
        // existed (a single combined result file).
        $testName = $request->test_name !== LaboratoryRequest::GENERAL_TEST_OPTION ? $request->test_name : null;

        $outcome = DB::transaction(function () use ($request, $laboratoryRequest, $patient, $testName) {
            // Row lock so a doctor review/correction request at the same
            // moment can't race past this check.
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
                'uploaded_by'           => $patient->id,
                'uploaded_at'           => now(),
                'notes'                 => $request->notes,
            ]);

            // At most one active/current file per test slot at a time —
            // a plain re-upload for a test that already has a file
            // quietly replaces it, exactly like the explicit "Replace"
            // action does (Section 4 of the upload workflow spec).
            LaboratoryResult::supersede($locked->id, $testName, $result->id);

            // Only THIS test's own pending correction (if any) is
            // resolved — a different test's still-open correction is
            // left untouched (Section 8/14 of the multi-test correction
            // workflow spec).
            $resolvedCorrection = $locked->resolvePendingCorrectionFor($testName, $result->id);
            $locked->recomputeStatus();
            $stillPending = $locked->corrections()->where('status', 'pending')->exists();

            return [
                'result'    => $result,
                'resolved'  => $resolvedCorrection,
                'still_pending' => $stillPending,
            ];
        });

        if (!$outcome) {
            return redirect()->back()
                ->with('error', 'This laboratory request has already been reviewed and can no longer accept new uploads.');
        }

        UserLog::create([
            'user_id' => $patient->id,
            'action'  => $outcome['resolved'] ? 'Uploaded Corrected Laboratory Result' : 'Uploaded Laboratory Result',
            'module'  => 'Laboratory',
            'details' => ($outcome['resolved'] ? 'Uploaded a corrected laboratory result file' : 'Uploaded a laboratory result file')
                       . ' for request ' . $laboratoryRequest->reference_no . '.',
        ]);

        if ($outcome['still_pending']) {
            return redirect()->back()->with('success', $outcome['resolved']
                ? 'File uploaded. Note: another test on this request is still flagged for correction.'
                : 'File uploaded. Note: a different test on this request is still flagged for correction.');
        }

        return redirect()->back()->with('success', $outcome['resolved']
            ? 'Corrected laboratory result uploaded successfully. The doctor has been notified.'
            : 'Laboratory result uploaded successfully. The doctor has been notified.');
    }

    /**
     * Replace the CURRENT uploaded result for one specific requested
     * test (Section 4 of the upload workflow spec) — used by the
     * per-test "Replace" action in the "Uploaded Laboratory Results"
     * modal, and by the "Replace {Test} Result" action shown when the
     * Doctor has requested a correction for that test.
     *
     * $resultId must be the PATIENT's own, currently-active (not
     * superseded/removed) file on THEIR OWN laboratory request — this
     * is re-checked here on every axis (Section 11/13 of the spec) so a
     * manipulated request/ID cannot replace another patient's file, a
     * file the patient didn't upload themselves, or a Reviewed result.
     */
    public function replaceResult(Request $request, $laboratoryRequestId, $resultId)
    {
        $patient = $request->user();

        $laboratoryRequest = LaboratoryRequest::where('patient_id', $patient->id)
            ->findOrFail($laboratoryRequestId);

        $existing = LaboratoryResult::where('laboratory_request_id', $laboratoryRequest->id)
            ->where('uploaded_by', $patient->id)
            ->findOrFail($resultId);

        $request->validate([
            'result_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notes'       => 'nullable|string|max:1000',
        ]);

        // The test slot being replaced is taken from the EXISTING file's
        // own test_name, never from client input — the Patient cannot
        // repoint a Replace at a different test than the one they clicked.
        $testName = $existing->test_name;

        $outcome = DB::transaction(function () use ($request, $laboratoryRequest, $existing, $patient, $testName) {
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)->lockForUpdate()->first();

            // Reviewed (or any other non-modifiable state) locks this
            // completely — enforced here, not just hidden in the UI.
            if (!$locked || !$locked->canPatientModifyResults()) {
                return null;
            }

            // The file being replaced must still be the active/current
            // one (not already superseded/removed by a concurrent request).
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
                'uploaded_by'           => $patient->id,
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
            'user_id' => $patient->id,
            'action'  => 'Replaced Laboratory Result',
            'module'  => 'Laboratory',
            'details' => 'Replaced the uploaded '
                       . ($testName ?? 'General') . ' result on laboratory request '
                       . $laboratoryRequest->reference_no . '.',
        ]);

        if ($outcome['still_pending']) {
            return redirect()->back()->with('success', ($testName ? $testName . ' r' : 'R')
                . 'esult replaced successfully. Note: another test on this request is still flagged for correction.');
        }

        return redirect()->back()->with('success', ($testName ? $testName . ' r' : 'R') . 'esult replaced successfully. The doctor has been notified.');
    }

    /**
     * Patient removes their OWN wrong upload (Section 3/4 of the upload
     * workflow spec). The file is taken off storage, but the database
     * row is only soft-deleted — never hard-deleted — so the Doctor's
     * Correction History / audit trail is never silently destroyed.
     *
     * Guarded on every axis Section 13 requires:
     *   - laboratory_request_id AND result_id are both scoped to the
     *     logged-in Patient's own data — a manipulated ID 404s instead
     *     of leaking another patient's file or letting them touch it.
     *   - only the file's OWN uploader (this patient, not Staff) may
     *     remove it.
     *   - only while the request is Result Received or Correction
     *     Required — never once Reviewed.
     */
    public function removeResult(Request $request, $laboratoryRequestId, $resultId)
    {
        $patient = $request->user();

        $laboratoryRequest = LaboratoryRequest::where('patient_id', $patient->id)
            ->findOrFail($laboratoryRequestId);

        $result = LaboratoryResult::where('laboratory_request_id', $laboratoryRequest->id)
            ->where('uploaded_by', $patient->id)
            ->findOrFail($resultId);

        $removed = DB::transaction(function () use ($laboratoryRequest, $result) {
            // Row lock so a doctor review/correction request at the same
            // moment can't race past this check.
            $locked = LaboratoryRequest::whereKey($laboratoryRequest->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || !$locked->canPatientModifyResults()) {
                return false;
            }

            $result->delete(); // soft delete — row kept for audit history
            $locked->recomputeStatus();

            return true;
        });

        if (!$removed) {
            return redirect()->back()
                ->with('error', 'This result has already been reviewed and can no longer be removed.');
        }

        Storage::disk('public')->delete($result->file_path);

        UserLog::create([
            'user_id' => $patient->id,
            'action'  => 'Removed Laboratory Result',
            'module'  => 'Laboratory',
            'details' => 'Removed uploaded file "' . $result->original_filename . '" from laboratory request '
                       . $laboratoryRequest->reference_no . '.',
        ]);

        return redirect()->back()->with('success', 'The uploaded file was removed. You can upload the correct result now.');
    }
}