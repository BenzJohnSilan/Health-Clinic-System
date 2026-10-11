<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\MedicalCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Patient → Medical Certificates (READ-ONLY history).
 *
 * A Medical Certificate is never requested, approved, corrected or printed
 * through the patient portal. The Doctor creates it at the clinic, the fee
 * is paid at the counter, and the patient receives the physically signed
 * copy from Staff. This controller only lets the patient look back at
 * certificates that were already RELEASED to them.
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
}
