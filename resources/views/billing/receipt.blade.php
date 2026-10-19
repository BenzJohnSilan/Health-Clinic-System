<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt {{ $invoice->invoice_no }}</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css/billing-receipt.css') }}">
</head>
<body>

@php
    // Figures AS OF THIS PAYMENT. A later payment on the same invoice must
    // never change what an earlier receipt says, so these are derived from
    // the payments up to and including this one (not the invoice's live fields).
    $snap       = $payment->snapshotAfter();
    $afterState = $snap['balance'] <= 0 ? 'Paid' : 'Partially Paid';
@endphp

<div class="rc-action-bar">
    <a href="{{ $backRoute }}" class="btn-rc-back" id="rcCloseBtn">
        <i class="fa-solid fa-xmark"></i> Close
    </a>
    <button type="button" class="btn-rc-print" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print Receipt
    </button>
</div>

<div class="rc-page">

    <!-- ================= HEADER ================= -->
    <div class="rc-header">
        <div class="rc-clinic-icon"><i class="fa-solid fa-staff-snake"></i></div>
        <div class="rc-clinic-name">Clinic RMS</div>
        <div class="rc-clinic-tag">Official Clinic Payment Receipt</div>
    </div>

    <hr class="rc-divider-thick">

    <!-- ================= RECEIPT INFO ================= -->
    <table class="rc-meta">
        <tr>
            <td>
                <span class="rc-meta-label">Receipt No.</span>
                <span class="rc-meta-value">RCPT-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</span>
            </td>
            <td>
                <span class="rc-meta-label">Invoice No.</span>
                <span class="rc-meta-value">{{ $invoice->invoice_no }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="rc-meta-label">Date &amp; Time</span>
                <span class="rc-meta-value">{{ $payment->paid_at->format('F d, Y · h:i A') }}</span>
            </td>
            <td>
                <span class="rc-meta-label">Received By</span>
                <span class="rc-meta-value">{{ $payment->receivedByName() }}</span>
            </td>
        </tr>
    </table>

    <hr class="rc-divider-thin">

    <!-- ================= PATIENT ================= -->
    <div class="rc-block">
        <div class="rc-block-label">Patient</div>
        <div class="rc-block-value">{{ $invoice->patientName() }}</div>
        @if($invoice->appointmentRef())
            <div class="rc-block-sub">
                Appointment {{ $invoice->appointmentRef() }}
                @if($invoice->appointment)
                    · {{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}
                @endif
            </div>
        @endif
        @if($invoice->discount_id_number)
            <div class="rc-block-sub">Discount ID No.: {{ $invoice->discount_id_number }}</div>
        @endif
    </div>

    <hr class="rc-divider-thin">

    <!-- ================= ITEMS ================= -->
    <table class="rc-items-table">
        <colgroup>
            <col class="rc-col-item">
            <col class="rc-col-qty">
            <col class="rc-col-price">
            <col class="rc-col-disc">
            <col class="rc-col-amt">
        </colgroup>
        <thead>
            <tr>
                <th>Item</th>
                <th class="rc-num">Qty</th>
                <th class="rc-num">Unit Price</th>
                <th class="rc-num">Discount</th>
                <th class="rc-num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->description }}
                        @if($item->discount_type)
                            <span class="rc-item-discount-note">
                                {{ $item->discountLabel() }} · {{ rtrim(rtrim(number_format((float) $item->discount_rate, 2), '0'), '.') }}%
                            </span>
                        @endif
                    </td>
                    <td class="rc-num">{{ $item->quantity }}</td>
                    <td class="rc-num">₱{{ number_format($item->unit_price, 2) }}</td>
                    <td class="rc-num">₱{{ number_format($item->discount_amount, 2) }}</td>
                    <td class="rc-num">₱{{ number_format($item->netAmount(), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="rc-empty">No items on this invoice.</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- ================= TOTALS ================= -->
    <div class="rc-summary">
        <table class="rc-totals">
            <tr>
                <td>Subtotal</td>
                <td class="rc-num">₱{{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @if($invoice->discount > 0)
            <tr>
                <td>Total Discount</td>
                <td class="rc-num">-₱{{ number_format($invoice->discount, 2) }}</td>
            </tr>
            @endif
            <tr class="rc-total-row">
                <td>TOTAL</td>
                <td class="rc-num">₱{{ number_format($invoice->total_amount, 2) }}</td>
            </tr>
        </table>

        <table class="rc-totals rc-totals--payment">
            <tr>
                <td>This Payment</td>
                <td class="rc-num">₱{{ number_format($payment->amount, 2) }}</td>
            </tr>
            <tr>
                <td>Payment Method</td>
                <td class="rc-num">{{ $payment->payment_method }}</td>
            </tr>
            @if($payment->payment_method === 'GCash')
            <tr>
                <td>Reference No.</td>
                <td class="rc-num">{{ $payment->reference_number }}</td>
            </tr>
            @endif
            <tr>
                <td>Total Paid (up to this payment)</td>
                <td class="rc-num">₱{{ number_format($snap['paid_total'], 2) }}</td>
            </tr>
            <tr class="rc-total-row">
                <td>Remaining Balance</td>
                <td class="rc-num">₱{{ number_format($snap['balance'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="rc-status">{{ $afterState }}</div>

    <hr class="rc-divider-thin">

    <p class="rc-footnote">
        This receipt confirms a payment made physically at the clinic counter. Please keep this for your records.
    </p>

</div>

<script>
    // The receipt opens in its own tab: Close shuts that tab. If the browser
    // won't let the script close it, the link falls back to the invoice page.
    (function () {
        var btn = document.getElementById('rcCloseBtn');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            window.close();
            setTimeout(function () {
                if (!window.closed) { window.location.href = btn.getAttribute('href'); }
            }, 250);
        });
    })();
</script>

</body>
</html>