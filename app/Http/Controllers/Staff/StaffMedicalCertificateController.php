<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MedicalCertificate;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Staff → Medical Certificates.
 *
 * Staff never creates or edits a certificate (that is the Doctor's job) and
 * never records a payment here (that stays in Billing & Payments). This
 * screen only shows where each certificate is in the clinic workflow and
 * lets Staff mark a signed, PAID certificate as RELEASED to the patient.
 */
class StaffMedicalCertificateController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status', 'all');

        $query = MedicalCertificate::with([
                'patient',
                'doctor',
                'invoice',
                'appointment.walkinPatient',
                'releasedBy',
            ])
            ->whereIn('status', ['issued', 'released']);

        if ($filter === 'awaiting_payment') {
            $query->where('status', 'issued')
                ->where(function ($q) {
                    $q->whereNull('invoice_id')
                        ->orWhereHas('invoice', fn ($i) => $i->where('status', '!=', 'Paid'));
                });
        } elseif ($filter === 'ready') {
            $query->where('status', 'issued')
                ->whereHas('invoice', fn ($i) => $i->where('status', 'Paid'));
        } elseif ($filter === 'released') {
            $query->where('status', 'released');
        } else {
            $filter = 'all';
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('appointment.walkinPatient', function ($p) use ($search) {
                        $p->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $certificates = $query->latest('issued_at')->paginate(10)->withQueryString();

        return view('staff.medical-certificates.index', compact('certificates', 'filter'));
    }

    /**
     * Mark a signed, paid certificate as RELEASED.
     *
     * Rules enforced here (never trust the button state):
     *  - the certificate must be ISSUED (not pending / rejected / already released)
     *  - its invoice must be fully PAID
     * Printing never releases a certificate — only this action does.
     */
    public function release(MedicalCertificate $medicalCertificate)
    {
        $result = DB::transaction(function () use ($medicalCertificate) {

            // Lock the row so a double click / second tab can't release twice.
            $certificate = MedicalCertificate::with('invoice')
                ->whereKey($medicalCertificate->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($certificate->isReleased()) {
                return ['error', 'This medical certificate has already been released.'];
            }

            if (!$certificate->isIssued()) {
                return ['error', 'Only an issued medical certificate can be released.'];
            }

            if (!$certificate->isPaid()) {
                return ['error', 'This medical certificate cannot be released until its invoice is fully paid.'];
            }

            $certificate->update([
                'status'      => 'released',
                'released_at' => now(),
                'released_by' => auth()->id(),
            ]);

            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Released Medical Certificate',
                'module'  => 'Medical Certificates',
                'details' => 'Released medical certificate ' . $certificate->certificate_number
                           . ' (' . $certificate->resolvedPatientName() . ').',
            ]);

            return ['success', 'Medical certificate released successfully.'];
        });

        return redirect()
            ->route('staff.medical-certificates.index')
            ->with($result[0], $result[1]);
    }
}
