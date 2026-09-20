<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;


/**
 * Admin monitoring for Billing & Payments — read-only. Admin does not
 * record payments here (that stays with Staff at the counter) but can
 * see every invoice/payment across the clinic and totals.
 */
class AdminBillingController extends Controller
{
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

        $totalCollected = Payment::sum('amount');
        $totalOutstanding = Invoice::whereIn('status', ['Unpaid', 'Partially Paid'])->sum('balance');
        $cashCollected  = Payment::where('payment_method', 'Cash')->sum('amount');
        $gcashCollected = Payment::where('payment_method', 'GCash')->sum('amount');

        $counts = [
            'Unpaid'          => Invoice::where('status', 'Unpaid')->count(),
            'Partially Paid'  => Invoice::where('status', 'Partially Paid')->count(),
            'Paid'            => Invoice::where('status', 'Paid')->count(),
            'Cancelled'       => Invoice::where('status', 'Cancelled')->count(),
        ];

        return view('admin.billing.index', compact(
            'invoices',
            'totalCollected',
            'totalOutstanding',
            'cashCollected',
            'gcashCollected',
            'counts'
        ));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items', 'payments.receivedBy', 'patient', 'walkinPatient', 'appointment', 'createdBy']);

        return view('admin.billing.show', compact('invoice'));
    }

    public function receipt(Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $invoice->load(['items', 'patient', 'walkinPatient', 'appointment']);
        $payment->load('receivedBy');

        return view('billing.receipt', [
            'invoice'   => $invoice,
            'payment'   => $payment,
            'backRoute' => route('admin.billing.show', $invoice->id),
        ]);
    }
}
