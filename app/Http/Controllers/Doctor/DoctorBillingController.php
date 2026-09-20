<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\UserLog;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Doctor-facing Billing — focused on invoice/charge management for the
 * Doctor's own appointments. Shares the exact same Invoice/Payment
 * models and BillingService as Admin/Staff/Patient Billing; the only
 * difference is scope (own appointments only) and available actions
 * (gated by the Billing & Payments permission set — see
 * App\Models\Permission::catalog()).
 *
 * Charge creation/editing itself still happens on the existing
 * Doctor Appointment consultation page (DoctorAppointmentController::
 * saveCharges) — this controller is the read/monitoring entry point
 * plus, only when Admin has explicitly granted `record_payment`, the
 * same Record Payment action Staff uses at the counter.
 */
class DoctorBillingController extends Controller
{
    protected BillingService $billing;

    public function __construct(BillingService $billing)
    {
        $this->billing = $billing;
    }

    public function index(Request $request)
    {
        $doctorId = auth()->id();

        $query = Invoice::with(['patient', 'walkinPatient', 'appointment'])
            ->whereHas('appointment', function ($q) use ($doctorId) {
                $q->where('doctor_id', $doctorId);
            });

        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('walkinPatient', function ($q2) use ($search) {
                        $q2->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $invoices = $query->latest()->paginate(10)->withQueryString();

        return view('doctor.billing.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        abort_unless(
            $invoice->appointment && $invoice->appointment->doctor_id === auth()->id(),
            403
        );

        $invoice->load(['items', 'payments.receivedBy', 'patient', 'walkinPatient', 'appointment']);

        // Only needed to render the inline "Edit Charges" form (see
        // resources/views/doctor/billing/show.blade.php). Cheap to
        // compute and harmless to pass even when the button won't show
        // (permission/paid/cancelled), since the Blade guards render it.
        $services = Service::where('is_active', true)->orderBy('name')->get();

        $dispensedMedicines = $invoice->appointment
            ? Prescription::where('appointment_id', $invoice->appointment_id)
                ->whereNotNull('medicine_id')
                ->with('medicine')
                ->get()
            : collect();

        return view('doctor.billing.show', compact('invoice', 'services', 'dispensedMedicines'));
    }

    /**
     * Only reachable when Admin has explicitly granted `record_payment`
     * to this Doctor (route middleware) — reuses the exact same
     * BillingService::recordPayment() validation/locking Staff uses,
     * nothing duplicated or weakened.
     */
    public function storePayment(Request $request, Invoice $invoice)
    {
        abort_unless(
            $invoice->appointment && $invoice->appointment->doctor_id === auth()->id(),
            403
        );

        $request->validate([
            'amount'           => 'required|numeric',
            'payment_method'   => 'required|in:Cash,GCash',
            'reference_number' => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:1000',
        ]);

        try {
            $payment = $this->billing->recordPayment($invoice, $request->only([
                'amount', 'payment_method', 'reference_number', 'notes',
            ]), auth()->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('doctor.billing.show', $invoice->id)
                ->withErrors($e->errors())
                ->withInput();
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Recorded Payment',
            'module'  => 'Billing & Payments',
            'details' => 'Recorded ' . $payment->payment_method
                       . ' payment of ₱' . number_format((float) $payment->amount, 2)
                       . ' for invoice #' . $invoice->invoice_no
                       . ' (' . $invoice->patientName() . ').',
        ]);

        return redirect()
            ->route('doctor.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id])
            ->with('success', 'Payment recorded successfully!');
    }

    public function receipt(Invoice $invoice, Payment $payment)
    {
        abort_unless(
            $invoice->appointment && $invoice->appointment->doctor_id === auth()->id(),
            403
        );
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $invoice->load(['items', 'patient', 'walkinPatient', 'appointment']);
        $payment->load('receivedBy');

        return view('billing.receipt', [
            'invoice'   => $invoice,
            'payment'   => $payment,
            'backRoute' => route('doctor.billing.show', $invoice->id),
        ]);
    }
}
