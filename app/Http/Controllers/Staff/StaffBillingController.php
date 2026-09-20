<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UserLog;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffBillingController extends Controller
{
    protected BillingService $billing;

    public function __construct(BillingService $billing)
    {
        $this->billing = $billing;
    }

    public function index(Request $request)
    {
        $query = Invoice::with(['patient', 'walkinPatient', 'appointment']);

        if ($search = $request->get('search')) {
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

        // Billing/payment report totals — same aggregate figures Admin
        // already sees on admin.billing.index (nothing new invented).
        // Only computed/shown when this Staff account has been granted
        // view_billing_reports; otherwise the page is the plain invoice
        // list it always was.
        $reportTotals = null;

        if (auth()->user()->hasPermission('view_billing_reports')) {
            $reportTotals = [
                'totalCollected'   => Payment::sum('amount'),
                'totalOutstanding' => Invoice::whereIn('status', ['Unpaid', 'Partially Paid'])->sum('balance'),
                'cashCollected'    => Payment::where('payment_method', 'Cash')->sum('amount'),
                'gcashCollected'   => Payment::where('payment_method', 'GCash')->sum('amount'),
            ];
        }

        return view('staff.billing.index', compact('invoices', 'reportTotals'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items', 'payments.receivedBy', 'patient', 'walkinPatient', 'appointment']);

        return view('staff.billing.show', compact('invoice'));
    }

    public function storePayment(Request $request, Invoice $invoice)
    {
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
                ->route('staff.billing.show', $invoice->id)
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
            ->route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id])
            ->with('success', 'Payment recorded successfully!');
    }

    public function receipt(Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $invoice->load(['items', 'patient', 'walkinPatient', 'appointment']);
        $payment->load('receivedBy');

        return view('billing.receipt', [
            'invoice' => $invoice,
            'payment' => $payment,
            'backRoute' => route('staff.billing.show', $invoice->id),
        ]);
    }
}
