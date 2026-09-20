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

<div class="rc-action-bar">
    <a href="{{ $backRoute }}" class="btn-rc-back">
        <i class="fa-solid fa-arrow-left"></i> Back
    </a>
    <button class="btn-rc-print" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Print Receipt
    </button>
</div>

<div class="rc-page">

    <div class="rc-header">
        <div class="rc-clinic-icon"><i class="fa-solid fa-staff-snake"></i></div>
        <div class="rc-clinic-name">Health Clinic Record Management System</div>
        <div class="rc-clinic-tag">Official Clinic Payment Receipt</div>
    </div>

    <hr class="rc-divider-thick">

    <div class="rc-meta-grid">
        <div>
            <span class="rc-meta-label">Receipt No.</span>
            <span class="rc-meta-value">RCPT-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</span>
        </div>
        <div>
            <span class="rc-meta-label">Invoice No.</span>
            <span class="rc-meta-value">{{ $invoice->invoice_no }}</span>
        </div>
        <div>
            <span class="rc-meta-label">Date &amp; Time</span>
            <span class="rc-meta-value">{{ $payment->paid_at->format('F d, Y · h:i A') }}</span>
        </div>
        <div>
            <span class="rc-meta-label">Received By</span>
            <span class="rc-meta-value">{{ $payment->receivedBy->first_name ?? '—' }} {{ $payment->receivedBy->last_name ?? '' }}</span>
        </div>
    </div>

    <hr class="rc-divider-thin">

    <div class="rc-block">
        <div class="rc-block-label">Patient</div>
        <div class="rc-block-value">{{ $invoice->patientName() }}</div>
        @if($invoice->appointment)
            <div class="rc-block-sub">
                Appointment {{ $invoice->appointment->reference_no }} ·
                {{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}
            </div>
        @endif
    </div>

    <hr class="rc-divider-thin">

    <table class="rc-items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="rc-num">Qty</th>
                <th class="rc-num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="rc-num">{{ $item->quantity }}</td>
                    <td class="rc-num">₱{{ number_format($item->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="rc-empty">No items on this invoice.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="rc-totals">
        <div class="rc-totals-row">
            <span>Subtotal</span>
            <span>₱{{ number_format($invoice->subtotal, 2) }}</span>
        </div>
        @if($invoice->discount > 0)
        <div class="rc-totals-row">
            <span>Discount</span>
            <span>-₱{{ number_format($invoice->discount, 2) }}</span>
        </div>
        @endif
        <div class="rc-totals-row rc-totals-row--bold">
            <span>Total Amount</span>
            <span>₱{{ number_format($invoice->total_amount, 2) }}</span>
        </div>
        <div class="rc-totals-row">
            <span>This Payment</span>
            <span>₱{{ number_format($payment->amount, 2) }}</span>
        </div>
        <div class="rc-totals-row">
            <span>Payment Method</span>
            <span>{{ $payment->payment_method }}</span>
        </div>
        @if($payment->payment_method === 'GCash')
        <div class="rc-totals-row">
            <span>Reference No.</span>
            <span>{{ $payment->reference_number }}</span>
        </div>
        @endif
        <div class="rc-totals-row">
            <span>Amount Paid (Total)</span>
            <span>₱{{ number_format($invoice->amount_paid, 2) }}</span>
        </div>
        <div class="rc-totals-row rc-totals-row--bold">
            <span>Remaining Balance</span>
            <span>₱{{ number_format($invoice->balance, 2) }}</span>
        </div>
    </div>

    <div class="rc-status rc-status--{{ $invoice->statusBadgeClass() }}">
        {{ $invoice->status }}
    </div>

    <hr class="rc-divider-thin">

    <p class="rc-footnote">
        This receipt confirms a payment made physically at the clinic counter. Please keep this for your records.
    </p>

</div>

</body>
</html>
