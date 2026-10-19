<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\MedicalCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Patient → Medical Certificates (history + personal copy).
 *
 * A Medical Certificate is never requested, approved or corrected through
 * the patient portal. The Doctor creates it at the clinic, the fee is paid
 * at the counter, and the patient receives the physically signed copy from
 * Staff. This controller lets the patient look back at certificates that
 * were already RELEASED to them and, once the invoice is fully paid, print
 * or save a COPY of it (the signed paper copy stays the official one).
 */
class MedicalCertificateController extends Controller
{
    /**
     * Patient → list of certificates already released to this patient.
     */
    public function index(Request $request)
    {
        $certificates = MedicalCertificate::with(['appointment', 'doctor'])
            ->where('patient_id', Auth::id())
            ->where('status', 'released')
            ->latest('released_at')
            ->paginate(10)
            ->withQueryString();

        return view('patient.medical-certificates.index', compact('certificates'));
    }

    /**
     * Patient → view a certificate that was already released to them.
     * Anything not released (draft / issued / unpaid) is not visible here.
     */
    public function show(MedicalCertificate $medicalCertificate)
    {
        abort_unless($medicalCertificate->patient_id === Auth::id(), 403);
        abort_unless($medicalCertificate->isReleased(), 404);

        $medicalCertificate->load(['appointment.doctor', 'doctor']);

        return view('patient.medical-certificates.show', compact('medicalCertificate'));
    }

    /**
     * Patient → print / save a COPY of a released certificate.
     * Only the owner, only when RELEASED, and only when the invoice is PAID.
     */
    public function print(MedicalCertificate $medicalCertificate)
    {
        abort_unless($medicalCertificate->patient_id === Auth::id(), 403);
        abort_unless($medicalCertificate->isReleased(), 404);

        $medicalCertificate->load(['appointment.walkinPatient', 'appointment.medicalRecord', 'patient', 'doctor', 'invoice']);

        abort_unless($medicalCertificate->isPaid(), 403, 'You can print this certificate once its invoice is fully paid.');

        return view('patient.medical-certificates.print', [
            'certificate' => $medicalCertificate,
        ]);
    }
}