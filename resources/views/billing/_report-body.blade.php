{{-- Revenue report tables, shared by the Staff / Admin pages and the print
     view. Expects: $report (BillingReportService::build), $labels. --}}
@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $cats = $report['categories'];
    $t    = $report['totals'];
@endphp

<section class="rp-section">
    <h2 class="rp-heading">Revenue by Service</h2>
    <table class="rp-table">
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Qty</th>
                <th class="num">Gross</th>
                <th class="num">Discount / Waiver</th>
                <th class="num">Net Sales</th>
                <th class="num">Paid so far (est.)</th>
                <th class="num">Unpaid (est.)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cats as $key => $c)
            <tr>
                <td>{{ $labels[$key] }}</td>
                <td class="num">{{ number_format($c['qty']) }}</td>
                <td class="num">{{ $peso($c['gross']) }}</td>
                <td class="num">{{ $peso($c['discount']) }}</td>
                <td class="num">{{ $peso($c['net']) }}</td>
                <td class="num">{{ $peso($c['collected']) }}</td>
                <td class="num">{{ $peso($c['uncollected']) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                <td class="num">{{ number_format($t['qty']) }}</td>
                <td class="num">{{ $peso($t['gross']) }}</td>
                <td class="num">{{ $peso($t['discount']) }}</td>
                <td class="num">{{ $peso($t['net']) }}</td>
                <td class="num">{{ $peso($t['collected']) }}</td>
                <td class="num">{{ $peso($t['uncollected']) }}</td>
            </tr>
        </tfoot>
    </table>
    <p class="rp-note">
        Based on {{ $report['invoiceCount'] }} invoice{{ $report['invoiceCount'] === 1 ? '' : 's' }} created in this period
        (voided / Cancelled invoices excluded).
        <strong>Paid so far</strong> and <strong>Unpaid</strong> are <em>estimates</em>: a payment is recorded for a whole
        invoice, not for one line, so it is spread over the invoice's lines in proportion to their net amounts.
        For the exact money received, use "Payments Received" below.
        @if($report['waived']['count'] > 0)
            Includes {{ $report['waived']['count'] }} waived consultation{{ $report['waived']['count'] === 1 ? '' : 's' }}
            ({{ $peso($report['waived']['amount']) }} waived).
        @endif
    </p>
</section>

<section class="rp-section">
    <h2 class="rp-heading">Payments Received in this Period</h2>
    <table class="rp-table rp-table--compact">
        <tbody>
            <tr><td>Cash</td><td class="num">{{ $peso($report['payments']['cash']) }}</td></tr>
            <tr><td>GCash / Non-cash</td><td class="num">{{ $peso($report['payments']['gcash']) }}</td></tr>
            <tr><td>Payment transactions</td><td class="num">{{ number_format($report['payments']['transactions']) }}</td></tr>
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL RECEIVED</td>
                <td class="num">{{ $peso($report['payments']['cash'] + $report['payments']['gcash']) }}</td>
            </tr>
        </tfoot>
    </table>
    <p class="rp-note">Exact figures: counted by the date each payment was recorded, so it can differ from "Net Sales" above when an older invoice is paid today.</p>
</section>

<section class="rp-section rp-definitions">
    <h2 class="rp-heading">What these figures mean</h2>
    <ul class="rp-note">
        <li><strong>Gross Sales</strong> — charges before any discount, on invoices created in the period.</li>
        <li><strong>Total Discount</strong> — Senior Citizen / PWD discounts and Doctor fee waivers on those invoices.</li>
        <li><strong>Net Sales</strong> — Gross Sales minus Total Discount: what the clinic billed, whether or not it was paid yet.</li>
        <li><strong>Cash Payments / Non-Cash (GCash)</strong> — money actually received in the period, by the date the payment was recorded.</li>
        <li><strong>Outstanding Balance</strong> — what patients still owe right now on unpaid and partly paid invoices (not limited to the period).</li>
        <li>Voided (Cancelled) invoices are never counted. A fully discounted or waived invoice has a ₱0.00 total and is shown as Paid.</li>
        <li>Dates use Philippine time (Asia/Manila). "This Week" runs Monday to Sunday.</li>
    </ul>
</section>

@if(count($report['daily']) > 1)
<section class="rp-section">
    <h2 class="rp-heading">Daily Breakdown (Net Sales)</h2>
    <table class="rp-table">
        <thead>
            <tr>
                <th>Date</th>
                @foreach($cats as $key => $c)
                    <th class="num">{{ $labels[$key] }}</th>
                @endforeach
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['daily'] as $date => $d)
            <tr>
                <td>{{ \Carbon\Carbon::parse($date)->format('M d, Y (D)') }}</td>
                @foreach($cats as $key => $c)
                    <td class="num">{{ $peso($d[$key]) }}</td>
                @endforeach
                <td class="num">{{ $peso($d['total']) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                @foreach($cats as $key => $c)
                    <td class="num">{{ $peso($c['net']) }}</td>
                @endforeach
                <td class="num">{{ $peso($t['net']) }}</td>
            </tr>
        </tfoot>
    </table>
</section>
@endif
