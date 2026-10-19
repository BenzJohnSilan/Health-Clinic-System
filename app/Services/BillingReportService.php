<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Revenue report for Billing & Payments.
 *
 * Breaks the clinic's income down by what was sold:
 *   Consultation · Medical Certificate · Medicine
 * (laboratory billing is intentionally NOT part of this report).
 *
 * Basis — same as the Staff billing report cards:
 *  - Sales (gross / discount / net) are taken from invoice LINES of
 *    non-Cancelled invoices created inside the date range.
 *  - Only "collectible" invoices are counted (Medical Certificate invoices,
 *    or consultations that are already Completed), so the report matches
 *    what Staff can actually see and collect.
 *  - "Collected" (shown as "Paid so far (est.)") spreads each invoice's
 *    amount_paid over its lines in proportion to their net amounts, down to
 *    the last centavo, so it adds up to what those invoices really paid.
 *  - An older invoice that has a discount on the invoice itself (no per-line
 *    discount) is spread over its lines too, so the table matches the cards.
 *  - Payment method totals use the payment's own paid_at date.
 *
 * METRIC DEFINITIONS (the same words are shown to staff on the report page):
 *  - Gross Sales      : charges before discount, of invoices CREATED in the period.
 *  - Total Discount   : Senior Citizen / PWD discounts and consultation waivers
 *                       on those same invoices.
 *  - Net Sales        : Gross - Discount. What the clinic billed, paid or not.
 *  - Cash / Non-Cash  : money actually RECEIVED in the period (payment date),
 *                       whatever invoice it belongs to. This is the cash-in figure.
 *  - Total Collected  : Cash + Non-Cash.
 *  - Outstanding      : what patients still owe on Unpaid / Partially Paid
 *                       invoices right now (not date-filtered).
 *  - Cancelled (voided) invoices are never counted in any sales figure.
 *  - Per-category "paid so far" is an ESTIMATE: an invoice's payments are
 *    spread over its lines in proportion to each line's net amount, because
 *    a payment is recorded per invoice, not per line.
 */
class BillingReportService
{
    public const CONSULTATION = 'consultation';
    public const CERTIFICATE  = 'medical_certificate';
    public const MEDICINE     = 'medicine';
    public const OTHER        = 'other';

    public const LABELS = [
        self::CONSULTATION => 'Consultation',
        self::CERTIFICATE  => 'Medical Certificate',
        self::MEDICINE     => 'Medicine',
        self::OTHER        => 'Other Services',
    ];

    /**
     * Resolve the date range from the request: today (default), this week,
     * this month, or a custom from/to. Invalid custom dates fall back to
     * today; from > to is swapped.
     *
     * @return array{0:Carbon,1:Carbon,2:string,3:string} [from, to, range, label]
     */
    public function resolveRange(Request $request): array
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

    /**
     * Build the whole report for one date range.
     *
     * @return array{
     *   categories: array<string, array>,
     *   totals: array,
     *   daily: array<string, array>,
     *   payments: array,
     *   waived: array,
     *   invoiceCount: int
     * }
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $blank = fn () => ['qty' => 0, 'lines' => 0, 'gross' => 0, 'discount' => 0, 'net' => 0, 'collected' => 0];

        // All money below is added up in whole centavos (integers) and only
        // turned into pesos at the very end, so nothing drifts by a centavo.
        $categories = [
            self::CONSULTATION => $blank(),
            self::CERTIFICATE  => $blank(),
            self::MEDICINE     => $blank(),
            self::OTHER        => $blank(),
        ];
        $daily       = [];
        $waivedCount = 0;
        $waivedC     = 0;

        $rows = DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->leftJoin('services as s', 's.id', '=', 'ii.service_id')
            ->whereIn('inv.id', Invoice::collectible()->select('invoices.id'))
            ->where('inv.status', '!=', 'Cancelled')
            ->whereBetween('inv.created_at', [$from, $to])
            ->orderBy('inv.id')
            ->orderBy('ii.id')
            ->get([
                'ii.item_type', 'ii.description', 'ii.quantity', 'ii.amount',
                'ii.discount_type', 'ii.discount_amount',
                'inv.id as invoice_id', 'inv.created_at as invoice_date',
                'inv.discount as invoice_discount', 'inv.amount_paid',
                's.name as service_name',
            ]);

        foreach ($rows->groupBy('invoice_id') as $lines) {
            $lines = $lines->values();

            $grossC = [];
            $discC  = [];
            foreach ($lines as $i => $row) {
                $grossC[$i] = max(Money::cents($row->amount), 0);
                $discC[$i]  = min(max(Money::cents($row->discount_amount), 0), $grossC[$i]);
            }

            // Older invoices may carry a discount on the invoice itself with
            // no per-line discount behind it. The summary cards (which read
            // the invoice) include it, so the table must too: spread the
            // leftover over the lines by their remaining amount.
            $leftoverC = max(Money::cents($lines[0]->invoice_discount) - array_sum($discC), 0);
            if ($leftoverC > 0) {
                $room  = array_map(fn ($g, $d) => $g - $d, $grossC, $discC);
                $extra = self::spread(min($leftoverC, array_sum($room)), $room);
                foreach ($extra as $i => $c) {
                    $discC[$i] += $c;
                }
            }

            $netC = array_map(fn ($g, $d) => max($g - $d, 0), $grossC, $discC);

            // ESTIMATE: a payment belongs to the whole invoice, not to a line,
            // so spread what the invoice has paid over its lines by net amount.
            // spread() hands out every centavo, so the lines add up to the
            // invoice's real amount_paid exactly (no rounding drift).
            $paidC     = min(max(Money::cents($lines[0]->amount_paid), 0), array_sum($netC));
            $collected = self::spread($paidC, $netC);

            $day = Carbon::parse($lines[0]->invoice_date)->toDateString();
            $daily[$day] ??= [
                self::CONSULTATION => 0, self::CERTIFICATE => 0,
                self::MEDICINE => 0, self::OTHER => 0, 'total' => 0,
            ];

            foreach ($lines as $i => $row) {
                $key = $this->categorize($row->item_type, $row->service_name ?? $row->description);

                $categories[$key]['qty']       += (int) $row->quantity;
                $categories[$key]['lines']     += 1;
                $categories[$key]['gross']     += $grossC[$i];
                $categories[$key]['discount']  += $discC[$i];
                $categories[$key]['net']       += $netC[$i];
                $categories[$key]['collected'] += $collected[$i];

                $daily[$day][$key]    += $netC[$i];
                $daily[$day]['total'] += $netC[$i];

                if ($row->discount_type === \App\Models\InvoiceItem::WAIVER_TYPE) {
                    $waivedCount++;
                    $waivedC += $discC[$i];
                }
            }
        }

        // "Other Services" only appears when the clinic actually sold some,
        // so the three requested categories always reconcile with invoices.
        if ($categories[self::OTHER]['lines'] === 0) {
            unset($categories[self::OTHER]);
        }

        $totals = $blank();
        foreach ($categories as $c) {
            foreach (['qty', 'lines', 'gross', 'discount', 'net', 'collected'] as $f) {
                $totals[$f] += $c[$f];
            }
        }

        $toPesos = function (array $c): array {
            foreach (['gross', 'discount', 'net', 'collected'] as $f) {
                $c[$f] = Money::pesos($c[$f]);
            }
            $c['uncollected'] = Money::pesos(Money::cents($c['net']) - Money::cents($c['collected']));

            return $c;
        };

        $categories = array_map($toPesos, $categories);
        $totals     = $toPesos($totals);

        ksort($daily);
        $daily = array_map(fn (array $d) => array_map(fn ($c) => Money::pesos($c), $d), $daily);

        $payments = $this->paymentTotals($from, $to);

        return [
            'categories'   => $categories,
            'totals'       => $totals,
            'daily'        => $daily,
            'waived'       => ['count' => $waivedCount, 'amount' => Money::pesos($waivedC)],
            'invoiceCount' => $rows->pluck('invoice_id')->unique()->count(),
            'payments'     => [
                'cash'         => $payments['cash'],
                'gcash'        => $payments['gcash'],
                'transactions' => $payments['transactions'],
            ],
        ];
    }

    /**
     * Split $totalC centavos over lines in proportion to $weights, handing
     * out every last centavo (largest remainder), so the parts always add up
     * to exactly $totalC. Keys of $weights are kept.
     *
     * @param  array<int, int> $weights
     * @return array<int, int>
     */
    private static function spread(int $totalC, array $weights): array
    {
        $out = array_fill_keys(array_keys($weights), 0);
        $sum = array_sum($weights);

        if ($totalC <= 0 || $sum <= 0) {
            return $out;
        }

        $remainders = [];
        foreach ($weights as $k => $w) {
            $out[$k]        = intdiv($totalC * $w, $sum);
            $remainders[$k] = ($totalC * $w) % $sum;
        }

        $left = $totalC - array_sum($out);
        arsort($remainders);                       // biggest leftover fraction first
        foreach (array_keys($remainders) as $k) {
            if ($left <= 0) {
                break;
            }
            if ($weights[$k] > 0) {
                $out[$k]++;
                $left--;
            }
        }

        return $out;
    }

    /**
     * Cash-in for the period, from the payment records themselves
     * (payment date, not invoice date). Used by BOTH the summary cards and
     * the "Payments Received" table so they always agree.
     *
     * @return array{cash: float, gcash: float, total: float, transactions: int}
     */
    public function paymentTotals(Carbon $from, Carbon $to): array
    {
        // One grouped query over the payment rows themselves (never joined to
        // invoices or items, so nothing can be counted twice).
        $rows = Payment::whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('payment_method')
            ->get();

        $cashC = $nonCashC = $count = 0;
        foreach ($rows as $r) {
            $cents = Money::cents($r->total);
            $r->payment_method === 'Cash' ? $cashC += $cents : $nonCashC += $cents;
            $count += (int) $r->n;
        }

        return [
            'cash'         => Money::pesos($cashC),
            'gcash'        => Money::pesos($nonCashC),
            'total'        => Money::pesos($cashC + $nonCashC),
            'transactions' => $count,
        ];
    }

    /**
     * Summary-card figures for the Revenue Report (Staff and Admin pages).
     * See the METRIC DEFINITIONS in the class comment.
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $invoices = fn () => Invoice::collectible()
            ->where('status', '!=', 'Cancelled')
            ->whereBetween('created_at', [$from, $to]);

        $grossC    = Money::cents($invoices()->sum('subtotal'));
        $discountC = Money::cents($invoices()->sum('discount'));
        $pay       = $this->paymentTotals($from, $to);

        return [
            'grossSales'          => Money::pesos($grossC),
            'totalDiscount'       => Money::pesos($discountC),
            'netSales'            => Money::pesos(max($grossC - $discountC, 0)),
            'cashPayments'        => $pay['cash'],
            'nonCashPayments'     => $pay['gcash'],
            'totalCollected'      => $pay['total'],
            'paymentTransactions' => $pay['transactions'],
            'totalOutstanding'    => $this->outstandingTotal(),
        ];
    }

    /**
     * What patients still owe right now: the balance of every collectible,
     * Unpaid / Partially Paid invoice. Voided (Cancelled) invoices and paid or
     * fully-waived invoices owe nothing. NOT date-filtered. The Revenue
     * Report cards and the Admin dashboard both use this one rule.
     */
    public function outstandingTotal(): float
    {
        return Money::pesos(Money::cents(
            Invoice::collectible()
                ->whereIn('status', ['Unpaid', 'Partially Paid'])
                ->sum('balance')
        ));
    }

    private function categorize(?string $itemType, ?string $serviceName): string
    {
        return match (true) {
            $itemType === 'medical_certificate'                                  => self::CERTIFICATE,
            $itemType === 'medicine'                                             => self::MEDICINE,
            $itemType === 'service' && $serviceName === Service::CONSULTATION    => self::CONSULTATION,
            $itemType === 'service' && $serviceName === Service::MEDICAL_CERTIFICATE => self::CERTIFICATE,
            default                                                              => self::OTHER,
        };
    }
}
