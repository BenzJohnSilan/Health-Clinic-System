<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UserLog;
use App\Services\BillingReportService;
use Illuminate\Http\Request;


/**
 * Admin monitoring for Billing & Payments — read-only. Admin does not
 * record payments here (that stays with Staff at the counter) but can
 * see every invoice/payment across the clinic and totals.
 */
class AdminBillingController extends Controller
{
    public function __construct(protected BillingReportService $reports)
    {
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

        return view('admin.billing.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items.waivedBy', 'payments.receivedBy', 'patient', 'walkinPatient', 'appointment', 'createdBy', 'discountIdVerifiedBy', 'voidedBy']);

        return view('admin.billing.show', compact('invoice'));
    }

    /** Revenue Report — income per Consultation / Medical Certificate / Medicine. */
    public function report(Request $request)
    {
        [$from, $to, $range, $label] = $this->reports->resolveRange($request);

        return view('admin.billing.report', [
            'reportTotals' => $this->reports->summary($from, $to),
            'report'      => $this->reports->build($from, $to),
            'reportRange' => ['range' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label],
            'labels'      => BillingReportService::LABELS,
        ]);
    }

    public function printReport(Request $request)
    {
        [$from, $to, $range, $label] = $this->reports->resolveRange($request);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Printed Revenue Report',
            'module'  => 'Billing & Payments',
            'details' => 'Printed the revenue report for ' . $label . '.',
        ]);

        return view('billing.report-print', [
            'report'      => $this->reports->build($from, $to),
            'reportRange' => ['range' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label],
            'labels'      => BillingReportService::LABELS,
            'backRoute'   => route('admin.billing.report', ['range' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString()]),
            'preparedBy'  => trim(auth()->user()->first_name . ' ' . auth()->user()->last_name),
        ]);
    }

    public function receipt(Invoice $invoice, Payment $payment)
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $invoice->load(['items', 'patient', 'walkinPatient', 'appointment']);
        $payment->load('receivedBy');
        $payment->setRelation('invoice', $invoice);

        return view('billing.receipt', [
            'invoice'   => $invoice,
            'payment'   => $payment,
            'backRoute' => route('admin.billing.show', $invoice->id),
        ]);
    }
}