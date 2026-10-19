@extends('layouts.admin')

@php
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $cats = $report['categories'];
    $t    = $report['totals'];

    // Same card component as Inventory Reports: row 1 = 4 cards, row 2 = 3 cards.
    $summaryCards = [
        ['label' => 'Gross Sales',         'value' => $peso($reportTotals['grossSales']),                'icon' => 'fa-sack-dollar',        'class' => ''],
        ['label' => 'Total Discount',      'value' => $peso($reportTotals['totalDiscount']),             'icon' => 'fa-tags',               'class' => 'card-out'],
        ['label' => 'Net Sales',           'value' => $peso($reportTotals['netSales']),                  'icon' => 'fa-chart-line',         'class' => ''],
        ['label' => 'Outstanding Balance', 'value' => $peso($reportTotals['totalOutstanding']),          'icon' => 'fa-hourglass-half',     'class' => 'card-low'],
        ['label' => 'Cash Payments',       'value' => $peso($reportTotals['cashPayments']),              'icon' => 'fa-money-bill-wave',    'class' => 'card-available'],
        ['label' => 'Non-Cash Payments',   'value' => $peso($reportTotals['nonCashPayments']),           'icon' => 'fa-mobile-screen-button','class' => 'card-blue'],
        ['label' => 'Payment Transactions','value' => number_format($reportTotals['paymentTransactions']),'icon' => 'fa-receipt',           'class' => ''],
    ];
@endphp

@section('head')
{{-- staff-medicine-reports.css = the Inventory Reports design (hero, cards,
     white sections, tables). staff-revenue-report.css only adds what the
     revenue page needs on top (range filter, totals row, notes). --}}
<link rel="stylesheet" href="{{ asset('css/staff-medicine-reports.css') }}">
<link rel="stylesheet" href="{{ asset('css/staff-revenue-report.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container reports-page">

    {{-- ================= HERO (same banner style as Inventory Reports) ================= --}}
    <div class="page-hero">
        <div class="page-hero-group">
            <div class="hero-icon-badge">
                <i class="fa-solid fa-chart-column"></i>
            </div>
            <div>
                <h1>Revenue Report</h1>
                <p>Sales, collections and outstanding balances at a glance.</p>
            </div>
        </div>

        <div class="page-hero-actions">
            <a href="{{ route('admin.billing.index') }}" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Billing</span>
            </a>
            <a href="{{ route('admin.billing.report.print', ['range' => $reportRange['range'], 'from' => $reportRange['from'], 'to' => $reportRange['to']]) }}"
               target="_blank" rel="noopener" class="back-btn back-btn--ghost">
                <i class="fa-solid fa-print"></i>
                <span>Print Report</span>
            </a>
        </div>
    </div>

    {{-- ================= DATE RANGE ================= --}}
    <form method="GET" action="{{ route('admin.billing.report') }}" class="rr-filter" id="rangeForm">
        <div class="rr-tabs" role="radiogroup" aria-label="Report period">
            @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom'] as $key => $label)
                <label class="rr-tab {{ $reportRange['range'] === $key ? 'active' : '' }}">
                    <input type="radio" name="range" value="{{ $key }}" {{ $reportRange['range'] === $key ? 'checked' : '' }}
                           onchange="{{ $key === 'custom' ? "document.getElementById('customRange').style.display='flex'" : 'this.form.submit()' }}">
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>

        <div class="rr-custom" id="customRange" style="display: {{ $reportRange['range'] === 'custom' ? 'flex' : 'none' }};">
            <input type="date" name="from" value="{{ $reportRange['from'] }}" aria-label="From date">
            <span>to</span>
            <input type="date" name="to" value="{{ $reportRange['to'] }}" aria-label="To date">
            <button type="submit" class="rr-apply">Apply</button>
        </div>

        <span class="rr-showing">Showing: <strong>{{ $reportRange['label'] }}</strong></span>
    </form>

    {{-- ================= SUMMARY CARDS ================= --}}
    <div class="report-cards">
        @foreach($summaryCards as $card)
            <div class="report-card {{ $card['class'] }}">
                <span class="report-card-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
                <span class="report-card-label">{{ $card['label'] }}</span>
                <span class="report-card-value">{{ $card['value'] }}</span>
            </div>
        @endforeach
    </div>
    <p class="rr-cards-note">
        Sales figures follow the invoice date and payment figures follow the date the payment was recorded.
        Outstanding Balance covers all unpaid and partially paid invoices, regardless of the selected period.
    </p>

    {{-- ================= REVENUE BY SERVICE ================= --}}
    <div class="report-section">
        <div class="report-section-head">
            <div class="report-section-icon"><i class="fa-solid fa-chart-pie"></i></div>
            <div>
                <h3>Revenue by Service</h3>
                <p class="report-section-sub">Sales and discounts for each service (paid / unpaid split is an estimate).</p>
            </div>
        </div>

        <div class="table-container">
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="col-num">Qty</th>
                        <th class="col-num">Gross</th>
                        <th class="col-num">Discount / Waiver</th>
                        <th class="col-num">Net Sales</th>
                        <th class="col-num">Paid so far (est.)</th>
                        <th class="col-num">Unpaid (est.)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cats as $key => $c)
                        <tr>
                            <td><span class="movement-label cat-{{ $key }}">{{ $labels[$key] }}</span></td>
                            <td class="col-num">{{ number_format($c['qty']) }}</td>
                            <td class="col-num">{{ $peso($c['gross']) }}</td>
                            <td class="col-num">{{ $peso($c['discount']) }}</td>
                            <td class="col-num">{{ $peso($c['net']) }}</td>
                            <td class="col-num">{{ $peso($c['collected']) }}</td>
                            <td class="col-num">{{ $peso($c['uncollected']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        <td class="col-num">{{ number_format($t['qty']) }}</td>
                        <td class="col-num">{{ $peso($t['gross']) }}</td>
                        <td class="col-num">{{ $peso($t['discount']) }}</td>
                        <td class="col-num">{{ $peso($t['net']) }}</td>
                        <td class="col-num">{{ $peso($t['collected']) }}</td>
                        <td class="col-num">{{ $peso($t['uncollected']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="rr-note">
            Based on {{ $report['invoiceCount'] }} invoice{{ $report['invoiceCount'] === 1 ? '' : 's' }} created in this period
            (voided / Cancelled invoices excluded).
            <strong>Paid so far</strong> and <strong>Unpaid</strong> are <em>estimates</em>: a payment is recorded for a whole
            invoice, not for one line, so it is spread over the invoice's lines in proportion to their net amounts.
            For the exact money received, see "Payments Received" below.
            @if($report['waived']['count'] > 0)
                Includes {{ $report['waived']['count'] }} waived consultation{{ $report['waived']['count'] === 1 ? '' : 's' }}
                ({{ $peso($report['waived']['amount']) }} waived).
            @endif
        </p>
    </div>

    {{-- ================= PAYMENTS RECEIVED ================= --}}
    <div class="report-section">
        <div class="report-section-head">
            <div class="report-section-icon"><i class="fa-solid fa-wallet"></i></div>
            <div>
                <h3>Payments Received in this Period</h3>
                <p class="report-section-sub">Money received, grouped by payment method.</p>
            </div>
        </div>

        <div class="table-container">
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Payment Method</th>
                        <th class="col-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="movement-label movement-in">Cash</span></td>
                        <td class="col-num">{{ $peso($report['payments']['cash']) }}</td>
                    </tr>
                    <tr>
                        <td><span class="movement-label cat-medical_certificate">GCash / Non-cash</span></td>
                        <td class="col-num">{{ $peso($report['payments']['gcash']) }}</td>
                    </tr>
                    <tr>
                        <td><span class="movement-label">Payment transactions</span></td>
                        <td class="col-num">{{ number_format($report['payments']['transactions']) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total Received</td>
                        <td class="col-num">{{ $peso($report['payments']['cash'] + $report['payments']['gcash']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="rr-note">Counted by the date the payment was recorded, so it can differ from "Net Sales" above when an older invoice is paid today.</p>
    </div>

    {{-- ================= DAILY BREAKDOWN (only for multi-day ranges) ================= --}}
    @if(count($report['daily']) > 1)
    <div class="report-section">
        <div class="report-section-head">
            <div class="report-section-icon"><i class="fa-solid fa-calendar-days"></i></div>
            <div>
                <h3>Daily Breakdown (Net Sales)</h3>
                <p class="report-section-sub">Net sales per day for the selected period.</p>
            </div>
        </div>

        <div class="table-container">
            <table class="medicine-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        @foreach($cats as $key => $c)
                            <th class="col-num">{{ $labels[$key] }}</th>
                        @endforeach
                        <th class="col-num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['daily'] as $date => $d)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($date)->format('M d, Y (D)') }}</td>
                            @foreach($cats as $key => $c)
                                <td class="col-num">{{ $peso($d[$key]) }}</td>
                            @endforeach
                            <td class="col-num">{{ $peso($d['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total</td>
                        @foreach($cats as $key => $c)
                            <td class="col-num">{{ $peso($c['net']) }}</td>
                        @endforeach
                        <td class="col-num">{{ $peso($t['net']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- ================= WHAT THESE FIGURES MEAN ================= --}}
    <div class="report-section">
        <div class="report-section-head">
            <div class="report-section-icon"><i class="fa-solid fa-circle-info"></i></div>
            <div>
                <h3>What these figures mean</h3>
                <p class="report-section-sub">So everyone reads the totals the same way.</p>
            </div>
        </div>
        <ul class="rr-note">
            <li><strong>Gross Sales</strong> — charges before any discount, on invoices created in the period.</li>
            <li><strong>Total Discount</strong> — Senior Citizen / PWD discounts and Doctor fee waivers on those invoices.</li>
            <li><strong>Net Sales</strong> — Gross Sales minus Total Discount: what the clinic billed, whether or not it was paid yet.</li>
            <li><strong>Cash / Non-Cash Payments</strong> — money actually received in the period, by the date the payment was recorded (not the invoice date).</li>
            <li><strong>Payment Transactions</strong> — how many payment records were saved in the period.</li>
            <li><strong>Outstanding Balance</strong> — what patients still owe right now on unpaid and partly paid invoices (not limited to the period).</li>
            <li>Voided (Cancelled) invoices are never counted. A fully discounted or waived invoice has a ₱0.00 total and is shown as Paid.</li>
            <li>The Staff Dashboard's "Today's Discounts" counts discounts on invoices whose <em>first payment</em> was today, while this report counts invoices <em>created</em> in the period, so the two can differ.</li>
            <li>Dates use Philippine time (Asia/Manila). "This Week" runs Monday to Sunday.</li>
        </ul>
    </div>

</div>
@endsection