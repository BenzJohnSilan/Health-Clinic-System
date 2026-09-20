<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\MedicalCertificate;
use Illuminate\Support\Facades\Auth;

class MedicalCertificateController extends Controller
{
    /**
     * Doctor → Medical Certificates (list, with status filter)
     */
    public function index(Request $request)
    {
        $filter = $request->query('status', 'all');

        $query = MedicalCertificate::with(['patient', 'appointment.walkinPatient'])
            ->whereHas('appointment', function ($q) {
                $q->where('doctor_id', Auth::id());
            });

        if (in_array($filter, ['pending', 'issued', 'rejected', 'correction_requested'])) {
            $query->where('status', $filter);
        }

        $certificates = $query->latest('requested_at')->paginate(10)->withQueryString();

        return view('doctor.medical-certificates.index', compact('certificates', 'filter'));
    }

    /**
     * Doctor → Review a request, or view an already-decided certificate
     */
    public function show(MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        $medicalCertificate->load(['patient', 'doctor', 'appointment.medicalRecord', 'appointment.walkinPatient']);

        if ($medicalCertificate->isPending()) {
            return view('doctor.medical-certificates.review', compact('medicalCertificate'));
        }

        if ($medicalCertificate->isCorrectionRequested()) {
            return redirect()->route('doctor.medical-certificates.correction.review', $medicalCertificate->id);
        }

        $medicalCertificate->load('latestCorrection');

        return view('doctor.medical-certificates.view', compact('medicalCertificate'));
    }

    /**
     * Doctor → Create / Edit & Correct Medical Certificate (build the certificate content)
     *
     * Reused for two situations:
     *   - status = pending               → normal "Create Medical Certificate"
     *   - status = correction_requested  → "Edit & Correct Certificate"
     */
    public function create(MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        if (!$medicalCertificate->isPending() && !$medicalCertificate->isCorrectionRequested()) {
            return redirect()
                ->route('doctor.medical-certificates.show', $medicalCertificate->id)
                ->with('error', 'This request has already been reviewed.');
        }

        $medicalCertificate->load(['patient', 'appointment.medicalRecord', 'appointment.walkinPatient', 'pendingCorrection']);
        $doctor = Auth::user();

        return view('doctor.medical-certificates.create', compact('medicalCertificate', 'doctor'));
    }

    /**
     * Doctor → Sign & Issue the Medical Certificate
     * (also handles saving the correction when status = correction_requested)
     */
    public function issue(Request $request, MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        if (!$medicalCertificate->isPending() && !$medicalCertificate->isCorrectionRequested()) {
            return redirect()
                ->route('doctor.medical-certificates.index')
                ->with('error', 'This request is no longer pending.');
        }

        $doctor = Auth::user();

        if (empty($doctor->signature)) {
            return back()->with('error', 'Please upload your digital signature in Account Settings before issuing a certificate.');
        }

        $validated = $request->validate([
            'medical_statement'   => 'required|string|max:2000',
            'recommendation'      => 'required|string|max:2000',
            'rest_start_date'     => 'nullable|date',
            'rest_end_date'       => 'nullable|date|after_or_equal:rest_start_date',
            'additional_remarks'  => 'nullable|string|max:2000',
        ]);

        $wasCorrection = $medicalCertificate->isCorrectionRequested();

        $medicalCertificate->update([
            'doctor_id'           => $doctor->id,
            'medical_statement'   => $validated['medical_statement'],
            'recommendation'      => $validated['recommendation'],
            'rest_start_date'     => $validated['rest_start_date'] ?? null,
            'rest_end_date'       => $validated['rest_end_date'] ?? null,
            'additional_remarks'  => $validated['additional_remarks'] ?? null,
            // Keep the same certificate number when correcting an already-
            // issued certificate; only generate one the first time.
            'certificate_number'  => $medicalCertificate->certificate_number
                ?? MedicalCertificate::generateCertificateNumber(),
            'status'              => 'issued',
            'issued_at'           => $wasCorrection ? $medicalCertificate->issued_at : now(),
        ]);

        if ($wasCorrection) {
            $medicalCertificate->pendingCorrection?->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
            ]);

            return redirect()->route('doctor.medical-certificates.index')
                ->with('success', 'Medical certificate corrected and updated successfully!');
        }

        return redirect()->route('doctor.medical-certificates.index')
            ->with('success', 'Medical certificate signed and issued successfully!');
    }

    /**
     * Doctor → Reject a pending request
     */
    public function reject(Request $request, MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        if (!$medicalCertificate->isPending()) {
            return redirect()
                ->route('doctor.medical-certificates.index')
                ->with('error', 'This request is no longer pending.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $medicalCertificate->update([
            'doctor_id'         => Auth::id(),
            'status'            => 'rejected',
            'rejected_at'       => now(),
            'rejection_reason'  => $validated['rejection_reason'],
        ]);

        return redirect()->route('doctor.medical-certificates.index')
            ->with('success', 'Medical certificate request rejected.');
    }

    /**
     * Doctor → Correction Details page (Review Correction)
     */
    public function reviewCorrection(MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        abort_unless($medicalCertificate->isCorrectionRequested(), 404);

        $medicalCertificate->load([
            'patient',
            'doctor',
            'appointment.medicalRecord',
            'appointment.walkinPatient',
            'pendingCorrection',
        ]);

        return view('doctor.medical-certificates.correction-review', compact('medicalCertificate'));
    }

    /**
     * Doctor → Reject the correction request (the original certificate stays valid)
     */
    public function rejectCorrection(Request $request, MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        abort_unless($medicalCertificate->isCorrectionRequested(), 404);

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $correction = $medicalCertificate->pendingCorrection;

        if ($correction) {
            $correction->update([
                'status'            => 'rejected',
                'rejection_reason'  => $validated['rejection_reason'] ?? null,
                'resolved_at'       => now(),
            ]);
        }

        // The original, already-issued certificate remains valid and untouched.
        $medicalCertificate->update([
            'status' => 'issued',
        ]);

        return redirect()->route('doctor.medical-certificates.index')
            ->with('success', 'Correction request rejected. The original certificate remains valid.');
    }

    /**
     * Doctor → Direct creation (walk-in / face-to-face patients)
     * Step 1: pick the consultation to attach the certificate to.
     */
    public function directIndex(Request $request)
    {
        $doctorId = Auth::id();

        $appointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctorId)
            ->where('status', 'Completed')
            ->whereDoesntHave('medicalCertificates', function ($q) {
                $q->whereIn('status', ['pending', 'issued', 'correction_requested']);
            })
            ->orderBy('appointment_date', 'desc')
            ->get();

        $selectedAppointmentId = $request->query('appointment_id');

        return view('doctor.medical-certificates.direct-create', compact('appointments', 'selectedAppointmentId'));
    }

    /**
     * Doctor → Direct creation
     * Step 2: create the certificate request for the chosen consultation,
     * then continue straight into the normal create/issue form.
     */
    public function directStore(Request $request)
    {
        $doctorId = Auth::id();

        $validated = $request->validate([
            'appointment_id'  => 'required|exists:appointments,id',
            'purpose'         => 'required|in:School,Work,Sick Leave,Other',
            'other_purpose'   => 'required_if:purpose,Other|nullable|string|max:150',
            'request_details' => 'nullable|string|max:1000',
        ]);

        $appointment = Appointment::where('id', $validated['appointment_id'])
            ->where('doctor_id', $doctorId)
            ->where('status', 'Completed')
            ->firstOrFail();

        // Guard against silently creating a duplicate — same rule as the
        // patient-facing flow: only block on an active request/certificate.
        $existingActive = MedicalCertificate::where('appointment_id', $appointment->id)
            ->whereIn('status', ['pending', 'issued', 'correction_requested'])
            ->first();

        if ($existingActive) {
            return redirect()
                ->route('doctor.medical-certificates.show', $existingActive->id)
                ->with('error', 'A medical certificate request already exists for this consultation.');
        }

        $medicalCertificate = MedicalCertificate::create([
            'appointment_id'  => $appointment->id,
            // Null for walk-in patients who have no user account; the
            // appointment relationship still resolves the correct patient.
            'patient_id'      => $appointment->patient_id,
            'purpose'         => $validated['purpose'],
            'other_purpose'   => $validated['purpose'] === 'Other' ? $validated['other_purpose'] : null,
            'request_details' => $validated['request_details'] ?? null,
            'status'          => 'pending',
            'requested_at'    => now(),
        ]);

        return redirect()->route('doctor.medical-certificates.create', $medicalCertificate->id);
    }

    /**
     * Doctor → Print an issued Medical Certificate
     * (covers online requests, walk-in/F2F certificates, and corrected ones —
     * reuses the same print design/template used on the patient side).
     */
    public function print(MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);
        abort_unless($medicalCertificate->isIssued(), 403, 'Only issued certificates can be printed.');

        $medicalCertificate->load(['appointment.walkinPatient', 'appointment.medicalRecord', 'patient', 'doctor']);

        return view('patient.medical-certificates.print', [
            'certificate' => $medicalCertificate,
        ]);
    }

    // =========================
    // AUTHORIZATION HELPER
    // =========================
    private function authorizeDoctor(MedicalCertificate $medicalCertificate): void
    {
        abort_unless(
            $medicalCertificate->appointment && $medicalCertificate->appointment->doctor_id === Auth::id(),
            403
        );
    }
}
