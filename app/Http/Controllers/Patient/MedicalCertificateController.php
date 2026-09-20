<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\MedicalCertificate;
use App\Models\MedicalCertificateCorrection;
use App\Models\UserLog;
use Illuminate\Support\Facades\Auth;

class MedicalCertificateController extends Controller
{
    /**
     * Patient → Medical Certificates (list)
     */
    public function index(Request $request)
    {
        $filter = $request->query('status', 'all');

        $query = MedicalCertificate::with(['appointment', 'doctor'])
            ->where('patient_id', Auth::id());

        if (in_array($filter, ['pending', 'issued', 'rejected'])) {
            $query->where('status', $filter);
        }

        $certificates = $query
            ->latest('requested_at')
            ->paginate(10)
            ->withQueryString();

        return view('patient.medical-certificates.index', compact('certificates', 'filter'));
    }

    /**
     * Patient → Request Medical Certificate (form)
     */
    public function create(Request $request)
    {
        // Only completed appointments belonging to the logged-in patient,
        // and only those without an existing ACTIVE request/certificate
        // (pending, issued, or correction_requested). Rejected ones are
        // allowed again ("Request Again").
        $appointments = Appointment::where('patient_id', Auth::id())
            ->where('status', 'Completed')
            ->whereDoesntHave('medicalCertificates', function ($q) {
                $q->whereIn('status', ['pending', 'issued', 'correction_requested']);
            })
            ->with('doctor')
            ->orderBy('appointment_date', 'desc')
            ->get();

        // Optional preselect when coming from a specific appointment row
        // (e.g. "Request Medical Certificate" / "Request Again" button).
        $selectedAppointmentId = $request->query('appointment_id');

        return view('patient.medical-certificates.request', compact('appointments', 'selectedAppointmentId'));
    }

    /**
     * Patient → Request Medical Certificate modal: eligibility check (AJAX)
     *
     * Business rule: a patient may request a medical certificate for a
     * doctor only if (a) they have a Completed appointment with that
     * doctor, and (b) they do not already have a PENDING medical
     * certificate request for that same doctor. A pending request for a
     * different doctor must NOT block the request.
     */
    public function eligibility()
    {
        $patientId = Auth::id();

        $completedAppointments = Appointment::where('patient_id', $patientId)
            ->where('status', 'Completed')
            ->with('doctor')
            ->orderBy('appointment_date', 'desc')
            ->get();

        if ($completedAppointments->isEmpty()) {
            return response()->json(['state' => 'no_completed_appointment']);
        }

        // Doctors for whom this patient already has a PENDING request
        // (regardless of which appointment that request is tied to).
        $doctorIdsWithPending = MedicalCertificate::where('patient_id', $patientId)
            ->where('status', 'pending')
            ->whereHas('appointment')
            ->with('appointment:id,doctor_id')
            ->get()
            ->pluck('appointment.doctor_id')
            ->filter()
            ->unique();

        $eligibleAppointments = collect();

        // Track *why* appointments were excluded so we can return an
        // accurate message instead of always assuming "pending".
        $hasGenuinePending = false;  // blocked by an actual 'pending' request somewhere
        $hasNonPendingBlock = false; // blocked only by an already-issued / correction_requested cert

        foreach ($completedAppointments as $appointment) {
            // Same appointment already has an active request/certificate
            // (existing rule — preserved), but now we check its status.
            $ownActiveCert = $appointment->medicalCertificates()
                ->whereIn('status', ['pending', 'issued', 'correction_requested'])
                ->latest()
                ->first();

            if ($ownActiveCert) {
                if ($ownActiveCert->status === 'pending') {
                    $hasGenuinePending = true;
                } else {
                    // 'issued' or 'correction_requested' — not a pending state.
                    $hasNonPendingBlock = true;
                }
                continue;
            }

            // The doctor already has a pending request from this patient
            // via a different appointment (new per-doctor rule).
            if ($appointment->doctor_id && $doctorIdsWithPending->contains($appointment->doctor_id)) {
                $hasGenuinePending = true;
                continue;
            }

            $eligibleAppointments->push($appointment);
        }

        if ($eligibleAppointments->isEmpty()) {
            // Only call it "pending" if a real pending request is actually
            // what's blocking the patient.
            if ($hasGenuinePending) {
                return response()->json(['state' => 'pending_exists']);
            }

            // Otherwise every completed appointment already has an issued
            // (or correction-requested) certificate — there's simply no
            // new completed appointment left to request one for.
            if ($hasNonPendingBlock) {
                return response()->json(['state' => 'no_new_appointment']);
            }
        }

        return response()->json([
            'state' => 'eligible',
            'appointments' => $eligibleAppointments->map(function ($appointment) {
                return [
                    'id'          => $appointment->id,
                    'date'        => \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y'),
                    'doctor_name' => 'Dr. ' . trim(($appointment->doctor->first_name ?? '') . ' ' . ($appointment->doctor->last_name ?? '')),
                ];
            })->values(),
        ]);
    }

    /**
     * Patient → Submit a Medical Certificate request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'appointment_id'  => 'required|exists:appointments,id',
            'purpose'         => 'required|in:School,Work,Sick Leave,Other',
            'other_purpose'   => 'required_if:purpose,Other|nullable|string|max:150',
            'request_details' => 'nullable|string|max:1000',
        ]);

        // Ensure the appointment belongs to the logged-in patient and is Completed
        $appointment = Appointment::where('id', $validated['appointment_id'])
            ->where('patient_id', Auth::id())
            ->where('status', 'Completed')
            ->firstOrFail();

        // Prevent duplicate active requests for the same appointment
        // (pending, issued, or correction_requested). Rejected requests
        // are allowed to be requested again.
        $hasActiveRequest = MedicalCertificate::where('appointment_id', $appointment->id)
            ->whereIn('status', ['pending', 'issued', 'correction_requested'])
            ->exists();

        if ($hasActiveRequest) {
            return back()->with('error', 'You already have an active medical certificate request for this appointment.');
        }

        // Prevent a duplicate PENDING request for the SAME DOCTOR, even if
        // it's tied to a different completed appointment. A pending
        // request for a different doctor must NOT block this one.
        $hasPendingForDoctor = MedicalCertificate::where('patient_id', Auth::id())
            ->where('status', 'pending')
            ->whereHas('appointment', function ($q) use ($appointment) {
                $q->where('doctor_id', $appointment->doctor_id);
            })
            ->exists();

        if ($hasPendingForDoctor) {
            return back()->with('error', 'You already have a pending medical certificate request for this doctor.');
        }

        MedicalCertificate::create([
            'appointment_id'  => $appointment->id,
            'patient_id'      => Auth::id(),
            'purpose'         => $validated['purpose'],
            'other_purpose'   => $validated['purpose'] === 'Other' ? $validated['other_purpose'] : null,
            'request_details' => $validated['request_details'] ?? null,
            'status'          => 'pending',
            'requested_at'    => now(),
        ]);

        $this->logActivity(
            'Requested Medical Certificate',
            'Requested a medical certificate for the appointment on ' .
            \Carbon\Carbon::parse($appointment->appointment_date)->format('F d, Y')
        );

        return redirect()->route('patient.medical-certificates.index')
            ->with('success', 'Medical certificate request submitted successfully!');
    }

    /**
     * Patient → View a request/certificate (status, rejection reason, or issued certificate)
     */
    public function show(MedicalCertificate $medicalCertificate)
    {
        abort_unless($medicalCertificate->patient_id === Auth::id(), 403);

        $medicalCertificate->load(['appointment.doctor', 'latestCorrection']);

        return view('patient.medical-certificates.show', compact('medicalCertificate'));
    }

    /**
     * Patient → Request a correction on an already-issued certificate
     */
    public function requestCorrection(Request $request, MedicalCertificate $medicalCertificate)
    {
        abort_unless($medicalCertificate->patient_id === Auth::id(), 403);
        abort_unless($medicalCertificate->isIssued(), 403, 'Only issued certificates can have a correction requested.');

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        MedicalCertificateCorrection::create([
            'medical_certificate_id' => $medicalCertificate->id,
            'patient_id'             => Auth::id(),
            'reason'                 => $validated['reason'],
            'status'                 => 'pending',
        ]);

        $medicalCertificate->update([
            'status' => 'correction_requested',
        ]);

        $this->logActivity(
            'Requested Medical Certificate Correction',
            'Requested a correction for medical certificate ' . ($medicalCertificate->certificate_number ?? '#' . $medicalCertificate->id)
        );

        return redirect()->route('patient.medical-certificates.show', $medicalCertificate->id)
            ->with('success', 'Correction request submitted. The doctor will review it shortly.');
    }

    /**
     * Patient → Print an ISSUED certificate only
     */
    public function print(MedicalCertificate $medicalCertificate)
    {
        abort_unless($medicalCertificate->patient_id === Auth::id(), 403);
        abort_unless($medicalCertificate->isIssued(), 403, 'Only issued certificates can be printed.');

        $medicalCertificate->load(['appointment.walkinPatient', 'appointment.medicalRecord', 'patient', 'doctor']);

        $this->logActivity(
            'Printed Medical Certificate',
            'Printed medical certificate ' . $medicalCertificate->certificate_number
        );

        return view('patient.medical-certificates.print', [
            'certificate' => $medicalCertificate,
        ]);
    }

    // =========================
    // ACTIVITY LOGGER
    // =========================
    private function logActivity($action, $details = null)
    {
        UserLog::create([
            'user_id' => Auth::id(),
            'action'  => $action,
            'module'  => 'Medical Records',
            'details' => $details,
        ]);
    }
}