<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UserLog;
use App\Services\BillingReportService;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffBillingController extends Controller
{
    protected BillingService $billing;
    protected BillingReportService $reports;

    public function __construct(BillingService $billing, BillingReportService $reports)
    {
        $this->billing = $billing;
        $this->reports = $reports;
    }

    public function index(Request $request)
    {
        // Pay-after-service: only invoices whose consultation is Completed
        // (or a separate Medical Certificate invoice) are shown to Staff.
        $query = Invoice::collectible()->with(['patient', 'walkinPatient', 'appointment']);

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

        return view('staff.billing.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        // Not billable until the consultation is Completed.
        abort_unless($invoice->isCollectible(), 404);

        $invoice->load(['items', 'payments.receivedBy', 'patient', 'walkinPatient', 'appointment']);

        $discountTypes = config('billing.discount_types', []);

        return view('staff.billing.show', compact('invoice', 'discountTypes'));
    }

    /**
     * Apply per-item discounts BEFORE payment. All the rules (locked once a
     * payment exists, controlled list, 0-100 rate, no stacking) live in
     * BillingService::applyItemDiscounts().
     */
    public function updateDiscounts(Request $request, Invoice $invoice)
    {
        $request->validate([
            'items'                 => 'required|array',
            'items.*.discount_type' => 'nullable|string|max:50',
            'items.*.discount_rate' => 'nullable|numeric|between:0,100',
            'discount_id_number'    => 'nullable|string|max:60',
        ]);

        $before = $this->billing->describeDiscounts($invoice);

        try {
            $updated = $this->billing->applyItemDiscounts(
                $invoice,
                $request->input('items', []),
                $request->input('discount_id_number'),
                auth()->user()
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.billing.show', $invoice->id)
                ->withErrors($e->errors())
                ->withInput();
        }

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Applied Item Discounts',
            'module'  => 'Billing & Payments',
            'details' => 'Updated item discounts for invoice #' . $invoice->invoice_no
                       . ' (' . $invoice->patientName() . '). Before: ' . $before
                       . '. After: ' . $this->billing->describeDiscounts($updated)
                       . ($updated->discount_id_number ? '. ID No.: ' . $updated->discount_id_number : '')
                       . '. Total discount: ₱' . number_format((float) $updated->discount, 2)
                       . ', total amount: ₱' . number_format((float) $updated->total_amount, 2) . '.',
        ]);

        return redirect()
            ->route('staff.billing.show', $invoice->id)
            ->with('success', 'Discounts applied. Invoice totals have been updated.');
    }

    public function storePayment(Request $request, Invoice $invoice)
    {
        $request->validate([
            'amount'           => 'required|numeric',
            'payment_method'   => 'required|in:Cash,GCash',
            'reference_number' => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:1000',
            'submission_token' => 'nullable|string|max:64',
        ]);

        try {
            $payment = $this->billing->recordPayment($invoice, $request->only([
                'amount', 'payment_method', 'reference_number', 'notes', 'submission_token',
            ]), auth()->user());
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.billing.show', $invoice->id)
                ->withErrors($e->errors())
                ->withInput();
        }

        // A repeated submit of the same form returns the payment that was
        // already saved: no second record, no second log line.
        if ($payment->wasRecentlyCreated) {
            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Recorded Payment',
                'module'  => 'Billing & Payments',
                'details' => 'Recorded ' . $payment->payment_method
                           . ' payment of ₱' . number_format((float) $payment->amount, 2)
                           . ' (RCPT-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . ')'
                           . ($payment->reference_number ? ' ref ' . $payment->reference_number : '')
                           . ' for invoice #' . $invoice->invoice_no
                           . ' (' . $invoice->patientName() . ').',
            ]);
        }

        // The receipt route requires view_payment_history. A Staff member who
        // may record payments but not view payment history would otherwise be
        // sent straight to a 403 right after a successful payment — so they
        // go back to the invoice instead (the payment is already saved).
        $destination = auth()->user()->hasPermission('view_payment_history')
            ? route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id])
            : route('staff.billing.show', $invoice->id);

        return redirect($destination)->with(
            'success',
            $payment->wasRecentlyCreated
                ? 'Payment recorded successfully!'
                : 'This payment was already recorded — it was not saved a second time.'
        );
    }

    /**
     * Revenue Report — income per Consultation / Medical Certificate /
     * Medicine for a date range. Route-protected by view_billing_reports.
     */
    public function report(Request $request)
    {
        [$from, $to, $range, $label] = $this->reports->resolveRange($request);

        return view('staff.billing.report', [
            'reportTotals' => $this->reports->summary($from, $to),
            'report'      => $this->reports->build($from, $to),
            'reportRange' => ['range' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label],
            'labels'      => BillingReportService::LABELS,
        ]);
    }

    /** Printer-friendly version of the same report (opens the print dialog). */
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
            'backRoute'   => route('staff.billing.report', ['range' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString()]),
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
            'invoice' => $invoice,
            'payment' => $payment,
            'backRoute' => route('staff.billing.show', $invoice->id),
        ]);
    }

    /**
     * Resolve the dashboard date range (today / week / month / custom).
     * The logic lives in BillingReportService so the cards, the Revenue
     * Report and the print view always agree on the same range.
     *
     * @return array{0:\Carbon\Carbon,1:\Carbon\Carbon,2:string,3:string}
     */
    private function resolveReportRange(Request $request): array
    {
        return $this->reports->resolveRange($request);
    }
}