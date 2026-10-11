<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\MedicalRecord;
use App\Models\Review;
use App\Models\UserLog;
use App\Models\LaboratoryRequest;
use App\Services\PatientNotificationService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PatientController extends Controller
{
    /**
     * Patient Dashboard — a compact OVERVIEW only. Every widget here
     * reuses the same models/relationships/logic as its full detail
     * page (Appointments, Laboratory Results, Notifications): nothing
     * new is computed, nothing is duplicated.
     */
    public function dashboard(PatientNotificationService $notificationService)
    {
        $patient = auth()->user();

        // =========================================================
        // PROFILE COMPLETION — reused exactly as before.
        // =========================================================
        $profileComplete      = $patient->computeProfileComplete();
        $missingProfileFields = $patient->getMissingProfileFields();

        $totalRequiredFields     = count(User::requiredProfileFields());
        $completedRequiredFields = $totalRequiredFields - count($missingProfileFields);
        $profileCompletionPercent = $totalRequiredFields > 0
            ? (int) round(($completedRequiredFields / $totalRequiredFields) * 100)
            : 100;

        // =========================================================
        // GREETING
        // =========================================================
        $hour = Carbon::now()->hour;
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good Morning';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Good Afternoon';
        } else {
            $greeting = 'Good Evening';
        }

        // =========================================================
        // UPCOMING APPOINTMENT — the single next Approved appointment
        // only (same relationship/status used by the Appointments page).
        // =========================================================
        $nextAppointment = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->where('status', 'Approved')
            ->whereDate('appointment_date', '>=', Carbon::today())
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->first();

        // =========================================================
        // LATEST LABORATORY RESULT — most recently updated laboratory
        // request that already has at least one uploaded result (same
        // LaboratoryRequest/LaboratoryResult data as the full Laboratory
        // Results page). The patient-facing status label mirrors the
        // exact wording already used on that page (Correction Required
        // -> "Correction Needed"), computed once here instead of in
        // the view.
        // =========================================================
        $latestLabRequest = LaboratoryRequest::with('tests')
            ->where('patient_id', $patient->id)
            ->whereHas('results')
            ->latest('updated_at')
            ->first();

        $latestLabStatusLabel = null;
        $latestLabDate        = null;

        if ($latestLabRequest) {
            $latestLabStatusLabel = $latestLabRequest->status === 'Correction Required'
                ? 'Correction Needed'
                : $latestLabRequest->status;

            $latestLabDate = $latestLabRequest->result_received_at
                ?? $latestLabRequest->requested_at;
        }

        // =========================================================
        // FOLLOW-UP RECOMMENDATION — reuses LaboratoryRequest's own
        // scopeAwaitingFollowUpBooking() (the exact same check the
        // Laboratory Results page and the appointment booking form use).
        // =========================================================
        $followUpLabRequest = LaboratoryRequest::awaitingFollowUpBooking()
            ->where('patient_id', $patient->id)
            ->orderByDesc('reviewed_at')
            ->first();

        // =========================================================
        // RECENT CLINIC ACTIVITY — reuses PatientNotificationService
        // (the exact same feed the header bell uses); only the 3
        // newest are ever pulled here, nothing extra is loaded.
        // =========================================================
        $recentNotifications = $notificationService->build($patient)->take(3);

        return view('patient.dashboard', compact(
            'patient',
            'greeting',
            'profileComplete',
            'missingProfileFields',
            'totalRequiredFields',
            'completedRequiredFields',
            'profileCompletionPercent',
            'nextAppointment',
            'latestLabRequest',
            'latestLabStatusLabel',
            'latestLabDate',
            'followUpLabRequest',
            'recentNotifications'
        ));
    }

    public function profile()
    {
        $patient = auth()->user();
        return view('patient.profile', compact('patient'));
    }

    // ✅ ACCOUNT SETTINGS PAGE
    public function settings()
    {
        $patient = auth()->user();

        $profileComplete      = $patient->computeProfileComplete();
        $missingProfileFields = $patient->getMissingProfileFields();

        return view('patient.account-settings', compact(
            'patient',
            'profileComplete',
            'missingProfileFields'
        ));
    }

    // =========================
    // PROFILE UPDATE (ACCOUNT SETTINGS) — avatar logic REMOVED
    // =========================
    public function updateProfile(Request $request)
    {
        $patient = auth()->user();

        $validated = $request->validate([
            'first_name'               => 'required|string|max:255',
            'middle_name'              => 'nullable|string|max:255',
            'last_name'                => 'required|string|max:255',
            'suffix'                   => 'nullable|string|max:50',
            'gender'                   => 'required|string|in:Male,Female,Other',
            'civil_status'             => 'required|string|in:Single,Married,Widowed,Separated',
            'address'                  => 'required|string|max:500',
            'contact_number'           => 'required|string|max:20',
            'username'                 => ['required', 'string', 'max:255', Rule::unique('users')->ignore($patient->id)],
            'email'                    => ['required', 'email', Rule::unique('users')->ignore($patient->id)],

            // Medical Information — required for profile completion, but
            // "Unknown / Not Sure" is an accepted answer for blood type so
            // patients are never blocked just because they don't know it.
            'blood_type'               => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-,Unknown',

            // Patient must answer whether they have allergies. If they do,
            // they must describe them; "No Known Allergies" is otherwise
            // stored automatically as a valid, complete answer.
            'allergy_status'           => 'required|in:none,has',
            'allergies_detail'         => 'required_if:allergy_status,has|nullable|string|max:1000',

            // Emergency Contact — required for profile completion
            'emergency_name'           => 'required|string|max:100',
            'relationship'             => 'required|string|max:50',
            'emergency_contact_number' => 'required|string|max:20',
            'emergency_address'        => 'required|string|max:100',
        ]);

        $allergies = $validated['allergy_status'] === 'has'
            ? $validated['allergies_detail']
            : 'No Known Allergies';

        $patient->update([
            'first_name'               => $validated['first_name'],
            'middle_name'              => $validated['middle_name'] ?? null,
            'last_name'                => $validated['last_name'],
            'suffix'                   => $validated['suffix'] ?? null,
            'gender'                   => $validated['gender'],
            'civil_status'             => $validated['civil_status'],
            'address'                  => $validated['address'],
            'contact_number'           => $validated['contact_number'],
            'username'                 => $validated['username'],
            'email'                    => $validated['email'],

            // Medical Information
            'blood_type'               => $validated['blood_type'],
            'allergies'                => $allergies,

            // Emergency Contact
            'emergency_name'           => $validated['emergency_name'],
            'relationship'             => $validated['relationship'],
            'emergency_contact_number' => $validated['emergency_contact_number'],
            'emergency_address'        => $validated['emergency_address'],
        ]);

        $this->logActivity('Updated Profile', 'Updated profile information.');

        return redirect()->route('patient.settings')
            ->with('success', 'Profile updated successfully!');
    }

    // =========================
    // PROFILE PICTURE — SAVE (independent from profile form)
    // =========================
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $patient = auth()->user();

        // Delete old avatar if it exists on disk
        if ($patient->avatar && Storage::disk('public')->exists($patient->avatar)) {
            Storage::disk('public')->delete($patient->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $patient->avatar = $path;
        $patient->save();

        $this->logActivity('Updated Profile Picture', 'Updated profile picture.');

        return redirect()->route('patient.settings')
            ->with('success', 'Profile picture updated successfully!');
    }

    // =========================
    // PROFILE PICTURE — REMOVE (independent from profile form)
    // =========================
    public function removeAvatar()
    {
        $patient = auth()->user();

        if ($patient->avatar) {
            // Safely handle case where DB has a path but file is missing
            if (Storage::disk('public')->exists($patient->avatar)) {
                Storage::disk('public')->delete($patient->avatar);
            }

            $patient->avatar = null;
            $patient->save();

            $this->logActivity('Removed Profile Picture', 'Removed profile picture.');
        }

        return redirect()->route('patient.settings')
            ->with('success', 'Profile picture removed successfully!');
    }

    // =========================
    // PASSWORD UPDATE (INSIDE SETTINGS)
    // =========================
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:6',
        ]);

        $patient = auth()->user();

        if (!Hash::check($request->current_password, $patient->password)) {
            return back()->withErrors([
                'current_password' => 'Current password does not match.'
            ]);
        }

        $patient->password = Hash::make($request->password);
        $patient->save();

        $this->logActivity('Changed Password', 'Account password was changed.');

        return redirect()->route('patient.settings')
            ->with('success', 'Password updated successfully.');
    }

    // =========================
    // MEDICAL REPORTS
    // =========================
    public function medicalReport()
    {
        $patient = auth()->user();

        // Search matches the appointment's own reference number or the
        // assigned doctor's name (first or last).
        $search = trim((string) request()->query('search', ''));

        // Filter dropdown is restricted to the statuses this history
        // page actually shows — kept in sync with the whereIn() below.
        $validStatuses = ['All', 'Completed', 'Cancelled', 'Rejected', 'No Show'];
        $statusFilter  = request()->query('status', 'All');
        if (!in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = 'All';
        }

        $appointments = Appointment::with([
                'doctor',
                'prescriptions.medicine',
                'review',
            ])
            ->where('patient_id', $patient->id)
            ->whereIn('status', ['Completed', 'Cancelled', 'Rejected', 'No Show'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference_no', 'like', "%{$search}%")
                          ->orWhereHas('doctor', function ($doctorQuery) use ($search) {
                              $doctorQuery->where('first_name', 'like', "%{$search}%")
                                          ->orWhere('last_name', 'like', "%{$search}%");
                          });
                });
            })
            ->when($statusFilter !== 'All', function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter);
            })
            ->orderBy('appointment_date', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('patient.appointment-history', compact('appointments', 'search', 'statusFilter'));
    }

    /**
     * Consultation Summary (SINGLE VIEW) — Appointment History → View.
     *
     * A patient-facing, read-only view of data that already exists on the
     * appointment (MedicalRecord, Prescription, Review, LaboratoryRequest
     * + tests/results). Nothing is copied or stored separately.
     *
     * Access rules (enforced in the query itself, never in the Blade):
     *   - the appointment must belong to the logged-in patient, and
     *   - it must be Completed (an In Progress/draft consultation is the
     *     Doctor's unfinished work and is never shown to the patient).
     * Anything else — another patient's ID, a non-Completed appointment,
     * or an ID that doesn't exist — gets the same standard 404, so the
     * response never reveals whether someone else's appointment exists.
     */
    public function showConsultationSummary($id)
    {
        $patient = auth()->user();

        $appointment = Appointment::with([
                'doctor',
                'medicalRecord',
                'prescriptions.medicine',   // ← BAGO: kasama na ang medicine para sa pangalan ng gamot sa table
                'review',
                'laboratoryRequests' => function ($query) use ($patient) {
                    $query->where('patient_id', $patient->id)
                          ->with(['tests', 'results', 'reviewedBy']);
                },
            ])
            ->where('patient_id', $patient->id)
            ->where('status', 'Completed')
            ->findOrFail($id);

        $medicalRecord = $appointment->medicalRecord;

        // Vital signs: only the ones the clinic actually recorded. The
        // MedicalRecord model already knows each vital's label and fixed
        // unit; "not taken"/empty vitals are simply omitted here.
        $vitals = collect($medicalRecord?->vitalsForDisplay() ?? [])
            ->filter(fn ($vital) => $vital['status'] === 'recorded')
            ->values();

        // Laboratory: requested tests + current status + result-file links
        // always; the physician's review notes and follow-up instructions
        // only once the Doctor has Reviewed the request.
        $laboratoryRequests = $appointment->laboratoryRequests->map(function ($labRequest) {
            $reviewed = $labRequest->isReviewed();

            $tests = $labRequest->currentResultsByTest()->map(function ($file, $testName) use ($labRequest) {
                return [
                    'name' => ($testName === 'Others' && filled($labRequest->other_test_description))
                        ? 'Others (' . $labRequest->other_test_description . ')'
                        : $testName,
                    'file' => $file,
                ];
            })->values();

            return [
                'request'      => $labRequest,
                // Same patient-facing wording the Laboratory Results page uses.
                'status_label' => $labRequest->status === 'Correction Required'
                    ? 'Correction Needed'
                    : $labRequest->status,
                'tests'        => $tests,
                'review_notes' => ($reviewed && filled($labRequest->review_notes))
                    ? $labRequest->review_notes
                    : null,
                'follow_up'    => ($reviewed && $labRequest->follow_up_recommended && filled($labRequest->follow_up_instructions))
                    ? $labRequest->follow_up_instructions
                    : null,
            ];
        });

        // Follow-up instructions stored on the consultation's Review.
        $review       = $appointment->review;
        $followUpDate = $review?->next_review_date
            ? Carbon::parse($review->next_review_date)->format('F j, Y')
            : null;
        $followUpMessage = filled($review?->message) ? $review->message : null;

        $this->logActivity(
            'Viewed Consultation Summary',
            'Viewed consultation summary from Dr. ' . ($appointment->doctor?->first_name ?? '') . ' ' . ($appointment->doctor?->last_name ?? '') .
            ' on ' . Carbon::parse($appointment->appointment_date)->format('F d, Y'),
            'Medical Records'
        );

        return view('patient.consultation-summary', compact(
            'appointment',
            'medicalRecord',
            'vitals',
            'laboratoryRequests',
            'followUpDate',
            'followUpMessage'
        ));
    }

    /**
     * Legacy Medical Report URL (/patient/medical-report/{id}).
     *
     * The separate Patient Medical Report page was replaced by the
     * Consultation Summary. The route is kept only so old bookmarks keep
     * working; it does no lookup of its own — the destination does the
     * ownership/Completed check.
     */
    public function showMedicalReport($id)
    {
        return redirect()->route('patient.appointment.show', $id);
    }

    /**
     * Printable Medical Report (A4 document).
     *
     * A presentation-only view generated from existing records — no new
     * tables or duplicated data. Uses the SAME ownership-scoped query as
     * showMedicalReport(), so a patient can never print another
     * patient's report by changing the appointment ID in the URL (404).
     */
    public function printMedicalReport($id, Request $request)
    {
        $appointment = $this->findOwnMedicalReportAppointment($id);

        // Laboratory data for this consultation (existing relationships).
        // Only completed (doctor-reviewed) requests are printed.
        $appointment->load([
            'laboratoryRequests.tests',
            'laboratoryRequests.results',
            'laboratoryRequests.reviewedBy',
        ]);

        $prescriptions = $appointment->prescriptions;
        $review        = $appointment->review;
        $medicalRecord = MedicalRecord::where('appointment_id', $appointment->id)->first();

        $laboratoryRequests = $appointment->laboratoryRequests
            ->filter(fn ($labRequest) => $labRequest->isReviewed())
            ->values();

        // Set when navigating here from the Medical Report's "Print Medical
        // Report" button so the print dialog opens automatically.
        $autoprint = $request->query('autoprint') === '1';

        $this->logActivity(
            'Printed Medical Report',
            'Printed medical report from Dr. ' . $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name .
            ' on ' . Carbon::parse($appointment->appointment_date)->format('F d, Y'),
            'Medical Records'
        );

        return view('patient.print-medical-report', compact(
            'appointment',
            'prescriptions',
            'review',
            'medicalRecord',
            'laboratoryRequests',
            'autoprint'
        ));
    }

    /**
     * Shared, ownership-scoped lookup for the printable Medical Report.
     * The patient_id constraint stops a patient from opening someone
     * else's report via the URL, and the Completed constraint keeps an
     * unfinished (In Progress/draft) consultation from being printed.
     */
    private function findOwnMedicalReportAppointment($id): Appointment
    {
        return Appointment::with([
                'doctor',
                'patient',
                'prescriptions.medicine',
                'review'
            ])
            ->where('id', $id)
            ->where('patient_id', auth()->id())
            ->where('status', 'Completed')
            ->firstOrFail();
    }

    public function showPrescription($id, Request $request)
    {
        // Completed only: a prescription still being written during an
        // In Progress consultation (draft) must not be visible to the
        // patient, even by typing the URL of their own appointment.
        $appointment = Appointment::with(['doctor', 'patient', 'review'])
            ->where('id', $id)
            ->where('patient_id', auth()->id())
            ->where('status', 'Completed')
            ->firstOrFail();

        $prescriptions = $appointment->prescriptions;
        $review        = $appointment->review;

        // Optional ?autoprint=1 opens the print dialog automatically.
        $autoprint = $request->query('autoprint') === '1';

        $this->logActivity(
            'Viewed Prescription',
            'Viewed prescription from Dr. ' . $appointment->doctor->first_name . ' ' . $appointment->doctor->last_name .
            ' on ' . Carbon::parse($appointment->appointment_date)->format('F d, Y'),
            'Medical Records'
        );

        return view('patient.prescription', compact(
            'appointment',
            'prescriptions',
            'review',
            'autoprint'
        ));
    }

    public function activityLogs(Request $request)
    {
        $user = auth()->user();

        $query = UserLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $query->where('details', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('month')) {
            $query->whereMonth('created_at', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('created_at', $request->year);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(10)->withQueryString();

        $actions = UserLog::where('user_id', $user->id)
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $years = UserLog::where('user_id', $user->id)
            ->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('patient.activity-logs', compact('logs', 'actions', 'years'));
    }

    // =========================
    // ACTIVITY LOGGER
    // =========================
    private function logActivity($action, $details = null, $module = 'Account')
    {
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => $action,
            'module'  => $module,
            'details' => $details,
        ]);
    }
}