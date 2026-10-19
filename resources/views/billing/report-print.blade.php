<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Revenue Report — {{ $reportRange['label'] }}</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css/billing-report.css') }}">
</head>
<body class="rp-print-body">

<div class="rp-action-bar">
    <a href="{{ $backRoute }}" class="rp-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <button type="button" class="rp-btn rp-btn--primary" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print Report
    </button>
</div>

<div class="rp-sheet">
    <div class="rp-print-header">
        <div class="rp-report-title">Revenue Report</div>
        <div class="rp-report-period">{{ $reportRange['label'] }}</div>
        <div class="rp-report-sub">Consultation · Medical Certificate · Medicine</div>
    </div>

    {{-- Key figures at a glance --}}
    @php
        $pesoP = fn ($n) => '₱' . number_format((float) $n, 2);
        $tot   = $report['totals'];
        $pay   = $report['payments'];
    @endphp
    <div class="rp-summary">
        <div class="rp-summary-item">
            <span class="rp-summary-label">Net Sales</span>
            <span class="rp-summary-value">{{ $pesoP($tot['net']) }}</span>
        </div>
        <div class="rp-summary-item">
            <span class="rp-summary-label">Collected</span>
            <span class="rp-summary-value">{{ $pesoP($tot['collected']) }}</span>
        </div>
        <div class="rp-summary-item">
            <span class="rp-summary-label">Uncollected</span>
            <span class="rp-summary-value">{{ $pesoP($tot['uncollected']) }}</span>
        </div>
        <div class="rp-summary-item">
            <span class="rp-summary-label">Payments Received</span>
            <span class="rp-summary-value">{{ $pesoP($pay['cash'] + $pay['gcash']) }}</span>
        </div>
    </div>

    @include('billing._report-body', ['report' => $report, 'labels' => $labels])

    <div class="rp-signatures">
        <div class="rp-sign-block">
            <div class="rp-sign-space">{{ $preparedBy }}</div>
            <div class="rp-sign-line"></div>
            <div class="rp-sign-label">Prepared by</div>
        </div>
        <div class="rp-sign-block">
            <div class="rp-sign-space"></div>
            <div class="rp-sign-line"></div>
            <div class="rp-sign-label">Noted by</div>
        </div>
    </div>

    <div class="rp-printed-at">Printed on {{ now()->format('F d, Y · h:i A') }}</div>
</div>

<script>
    // Open the print dialog right away; the buttons above stay for a re-print.
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
</body>
</html>