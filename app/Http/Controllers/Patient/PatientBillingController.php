<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Patient-facing Billing & Payments — strictly read-only. No "Pay Now",
 * no way to mark a bill paid or edit an amount; patients can only view
 * their own bills, payment history, and receipts.
 */
class PatientBillingController extends Controller
{
    public function index(Request $request)
    {
        $patientId = auth()->id();

        $query = Invoice::with(['appointment', 'items'])
            ->where('patient_id', $patientId);

        if ($search = trim((string) $request->get('search'))) {
            $query->where('invoice_no', 'like', "%{$search}%");
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $invoices = $query->latest()->paginate(10)->withQueryString();

        // Account-wide totals for the summary cards — computed from ALL of
        // the patient's invoices (not just the current page/filtered
        // results) so the cards always reflect the full billing picture.
        $ownInvoices = Invoice::where('patient_id', $patientId)
            ->get(['status', 'amount_paid', 'balance']);

        $summary = [
            'total_invoices' => $ownInvoices->count(),
            'total_paid'     => $ownInvoices->sum('amount_paid'),
            'outstanding'    => $ownInvoices->where('status', '!=', 'Cancelled')->sum('balance'),
        ];

        return view('patient.billing.index', compact('invoices', 'summary'));
    }

    public function show(Invoice $invoice)
    {
        abort_unless($invoice->patient_id === auth()->id(), 403);

        $invoice->load(['items', 'payments.receivedBy', 'appointment']);

        return view('patient.billing.show', compact('invoice'));
    }

    public function receipt(Invoice $invoice, Payment $payment)
    {
        abort_unless($invoice->patient_id === auth()->id(), 403);
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $invoice->load(['items', 'patient', 'appointment']);
        $payment->load('receivedBy');

        return view('billing.receipt', [
            'invoice'   => $invoice,
            'payment'   => $payment,
            'backRoute' => route('patient.billing.show', $invoice->id),
        ]);
    }
}
