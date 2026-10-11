<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\MedicalCertificate;
use App\Services\BillingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MedicalCertificateController extends Controller
{
    protected BillingService $billing;

    public function __construct(BillingService $billing)
    {
        $this->billing = $billing;
    }

    /**
     * Statuses that mean "this consultation already has a medical
     * certificate" (so a second one must not be created for it).
     */
    private const ACTIVE_STATUSES = ['issued', 'correction_requested', 'released'];

    /**
     * Doctor → Medical Certificates (list, with status filter)
     */
    public function index(Request $request)
    {
        $filter = $request->query('status', 'all');

        $query = MedicalCertificate::with(['patient', 'appointment.walkinPatient', 'invoice'])
            ->whereHas('appointment', function ($q) {
                $q->where('doctor_id', Auth::id());
            });

        if (in_array($filter, ['issued', 'released', 'correction_requested'])) {
            $query->where('status', $filter);
        } else {
            $filter = 'all';
        }

        $certificates = $query->latest('id')->paginate(10)->withQueryString();

        return view('doctor.medical-certificates.index', compact('certificates', 'filter'));
    }

    /**
     * Doctor → View a medical certificate (details, invoice/payment state,
     * and the Print button once it is issued and paid).
     */
    public function show(MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        if ($medicalCertificate->isCorrectionRequested()) {
            return redirect()->route('doctor.medical-certificates.correction.review', $medicalCertificate->id);
        }

        $medicalCertificate->load([
            'patient', 'doctor', 'invoice',
            'appointment.medicalRecord', 'appointment.walkinPatient',
            'latestCorrection',
        ]);

        return view('doctor.medical-certificates.view', compact('medicalCertificate'));
    }

    /**
     * Doctor → Create Medical Certificate (form).
     *
     * One page: pick a COMPLETED consultation of this doctor that does not
     * have a certificate yet, review the patient/consultation info, then
     * fill in the certificate details.
     */
    public function create(Request $request)
    {
        $appointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', Auth::id())
            ->where('status', 'Completed')
            ->whereDoesntHave('medicalCertificates', function ($q) {
                $q->whereIn('status', self::ACTIVE_STATUSES);
            })
            ->orderBy('appointment_date', 'desc')
            ->get();

        $selectedAppointmentId = $request->query('appointment_id');
        $doctor = Auth::user();

        return view('doctor.medical-certificates.create', compact('appointments', 'selectedAppointmentId', 'doctor'));
    }

    /**
     * Doctor → Save the Medical Certificate.
     *
     * Creates the certificate directly as ISSUED, then attaches its
     * invoice through BillingService (added to the consultation invoice
     * when that is still unpaid, otherwise a separate invoice).
     */
    public function store(Request $request)
    {
        $doctor = Auth::user();

        $validated = $request->validate([
            'appointment_id'     => 'required|exists:appointments,id',
            'purpose'            => 'required|in:School,Work,Sick Leave,Other',
            'other_purpose'      => 'required_if:purpose,Other|nullable|string|max:150',
            'request_details'    => 'nullable|string|max:1000',
            'medical_statement'  => 'required|string|max:2000',
            'recommendation'     => 'required|string|max:2000',
            'rest_start_date'    => 'nullable|date',
            'rest_end_date'      => 'nullable|date|after_or_equal:rest_start_date',
            'additional_remarks' => 'nullable|string|max:2000',
        ]);

        // Only a COMPLETED consultation of this doctor can get a certificate.
        $appointment = Appointment::where('id', $validated['appointment_id'])
            ->where('doctor_id', $doctor->id)
            ->where('status', 'Completed')
            ->firstOrFail();

        $result = DB::transaction(function () use ($appointment, $doctor, $validated) {

            // Lock the consultation so a double click / second tab can't
            // create two certificates (and two charges) for the same visit.
            Appointment::whereKey($appointment->id)->lockForUpdate()->first();

            $existing = MedicalCertificate::where('appointment_id', $appointment->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->first();

            if ($existing) {
                return [null, $existing];
            }

            $certificate = MedicalCertificate::create([
                'appointment_id'     => $appointment->id,
                // Null for walk-in patients who have no user account; the
                // appointment relationship still resolves the right patient.
                'patient_id'         => $appointment->patient_id,
                'doctor_id'          => $doctor->id,
                'purpose'            => $validated['purpose'],
                'other_purpose'      => $validated['purpose'] === 'Other' ? $validated['other_purpose'] : null,
                'request_details'    => $validated['request_details'] ?? null,
                'medical_statement'  => $validated['medical_statement'],
                'recommendation'     => $validated['recommendation'],
                'rest_start_date'    => $validated['rest_start_date'] ?? null,
                'rest_end_date'      => $validated['rest_end_date'] ?? null,
                'additional_remarks' => $validated['additional_remarks'] ?? null,
                'certificate_number' => MedicalCertificate::generateCertificateNumber(),
                'status'             => 'issued',
                'issued_at'          => now(),
            ]);

            // Billing is handled entirely by BillingService (combined vs
            // separate invoice is decided there). Idempotent.
            $this->billing->ensureInvoiceForMedicalCertificate($certificate, $doctor);

            return [$certificate, null];
        });

        [$certificate, $existing] = $result;

        if ($existing) {
            return redirect()
                ->route('doctor.medical-certificates.show', $existing->id)
                ->with('error', 'A medical certificate already exists for this consultation.');
        }

        return redirect()
            ->route('doctor.medical-certificates.show', $certificate->id)
            ->with('success', 'Medical certificate created. Its invoice is now in Billing & Payments — print it once the fee is paid.');
    }

    /**
     * Doctor → Edit & Correct an issued certificate whose correction was
     * requested (status = correction_requested).
     */
    public function editCorrection(MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        abort_unless($medicalCertificate->isCorrectionRequested(), 404);

        $medicalCertificate->load(['patient', 'appointment.medicalRecord', 'appointment.walkinPatient', 'pendingCorrection']);
        $doctor = Auth::user();

        return view('doctor.medical-certificates.correction-edit', compact('medicalCertificate', 'doctor'));
    }

    /**
     * Doctor → Save the correction and re-issue. The certificate number and
     * invoice stay the same, so a correction never creates a second charge.
     */
    public function updateCorrection(Request $request, MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        abort_unless($medicalCertificate->isCorrectionRequested(), 404);

        $validated = $request->validate([
            'medical_statement'   => 'required|string|max:2000',
            'recommendation'      => 'required|string|max:2000',
            'rest_start_date'     => 'nullable|date',
            'rest_end_date'       => 'nullable|date|after_or_equal:rest_start_date',
            'additional_remarks'  => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($medicalCertificate, $validated) {
            $medicalCertificate->update([
                'doctor_id'          => Auth::id(),
                'medical_statement'  => $validated['medical_statement'],
                'recommendation'     => $validated['recommendation'],
                'rest_start_date'    => $validated['rest_start_date'] ?? null,
                'rest_end_date'      => $validated['rest_end_date'] ?? null,
                'additional_remarks' => $validated['additional_remarks'] ?? null,
                'status'             => 'issued',
            ]);

            $medicalCertificate->pendingCorrection?->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
            ]);
        });

        return redirect()->route('doctor.medical-certificates.index')
            ->with('success', 'Medical certificate corrected and updated successfully!');
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
     * Doctor → Print an issued Medical Certificate.
     *
     * Allowed only while the certificate is ISSUED and its fee is PAID.
     * Printing does NOT release it: the Doctor signs the printed copy by
     * hand and Staff then releases it (Staff → Medical Certificates).
     */
    public function print(MedicalCertificate $medicalCertificate)
    {
        $this->authorizeDoctor($medicalCertificate);

        $medicalCertificate->load(['appointment.walkinPatient', 'appointment.medicalRecord', 'patient', 'doctor', 'invoice']);

        abort_unless($medicalCertificate->isIssued(), 403, 'Only issued certificates can be printed.');
        abort_unless($medicalCertificate->isPaid(), 403, 'This certificate cannot be printed until its invoice is fully paid.');

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