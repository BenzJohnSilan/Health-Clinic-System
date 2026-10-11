<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\UserLog;
use App\Services\BillingService;
use Carbon\Carbon;
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

        // Billing/payment report cards. Only computed/shown when this Staff
        // account has been granted view_billing_reports; otherwise the page
        // is the plain invoice list it always was.
        //
        // Invoice-based metrics (gross sales, discount, net sales) use the
        // invoice created date and exclude Cancelled invoices. Payment-based
        // metrics (cash, non-cash, transactions) use the payment's paid_at,
        // and count payment RECORDS, not invoices. The date range only
        // drives these cards — the invoice list below keeps its own
        // search/status filters so older unpaid invoices never disappear.
        $reportTotals = null;
        [$from, $to, $range, $rangeLabel] = $this->resolveReportRange($request);

        if (auth()->user()->hasPermission('view_billing_reports')) {
            $invoicesInRange = fn () => Invoice::collectible()
                ->where('status', '!=', 'Cancelled')
                ->whereBetween('created_at', [$from, $to]);
            $paymentsInRange = fn () => Payment::whereBetween('paid_at', [$from, $to]);

            // Gross = charges BEFORE discount (invoices.subtotal).
            $grossSales    = (float) $invoicesInRange()->sum('subtotal');
            $totalDiscount = (float) $invoicesInRange()->sum('discount');

            $reportTotals = [
                'grossSales'          => $grossSales,
                'totalDiscount'       => $totalDiscount,
                'netSales'            => max($grossSales - $totalDiscount, 0),
                'cashPayments'        => (float) $paymentsInRange()->where('payment_method', 'Cash')->sum('amount'),
                'nonCashPayments'     => (float) $paymentsInRange()->where('payment_method', '!=', 'Cash')->sum('amount'),
                'paymentTransactions' => $paymentsInRange()->count(),
                // Not date-filtered: what is still owed right now.
                'totalOutstanding'    => (float) Invoice::collectible()
                    ->whereIn('status', ['Unpaid', 'Partially Paid'])
                    ->sum('balance'),
            ];
        }

        $reportRange = [
            'range' => $range,
            'from'  => $from->toDateString(),
            'to'    => $to->toDateString(),
            'label' => $rangeLabel,
        ];

        return view('staff.billing.index', compact('invoices', 'reportTotals', 'reportRange'));
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
        ]);

        try {
            $updated = $this->billing->applyItemDiscounts($invoice, $request->input('items', []));
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
                       . ' (' . $invoice->patientName() . '). Total discount: ₱'
                       . number_format((float) $updated->discount, 2)
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

        // The receipt route requires view_payment_history. A Staff member who
        // may record payments but not view payment history would otherwise be
        // sent straight to a 403 right after a successful payment — so they
        // go back to the invoice instead (the payment is already saved).
        $destination = auth()->user()->hasPermission('view_payment_history')
            ? route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id])
            : route('staff.billing.show', $invoice->id);

        return redirect($destination)->with('success', 'Payment recorded successfully!');
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

    /**
     * Resolve the dashboard date range: today (default), this week,
     * this month, or a custom from/to. Invalid custom dates fall back to
     * today; from > to is swapped.
     *
     * @return array{0:Carbon,1:Carbon,2:string,3:string}
     */
    private function resolveReportRange(Request $request): array
    {
        $range = $request->get('range', 'today');

        if ($range === 'week') {
            $from = now()->startOfWeek();
            $to   = now()->endOfWeek();
        } elseif ($range === 'month') {
            $from = now()->startOfMonth();
            $to   = now()->endOfMonth();
        } elseif ($range === 'custom') {
            try {
                $f = $request->get('from') ? Carbon::parse($request->get('from')) : null;
                $t = $request->get('to') ? Carbon::parse($request->get('to')) : null;
            } catch (\Throwable $e) {
                $f = $t = null;
            }

            $f = $f ?? $t;
            $t = $t ?? $f;

            if (!$f) {
                $range = 'today';
                $from  = now()->startOfDay();
                $to    = now()->endOfDay();
            } else {
                if ($f->gt($t)) {
                    [$f, $t] = [$t, $f];
                }
                $from = $f->copy()->startOfDay();
                $to   = $t->copy()->endOfDay();
            }
        } else {
            $range = 'today';
            $from  = now()->startOfDay();
            $to    = now()->endOfDay();
        }

        $label = $from->isSameDay($to)
            ? $from->format('M d, Y')
            : $from->format('M d, Y') . ' – ' . $to->format('M d, Y');

        return [$from, $to, $range, $label];
    }
}
