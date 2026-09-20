@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-billing.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container">

    {{-- ================= INVOICE HEADER ================= --}}
    <div class="inv-header">
        <a href="{{ route('patient.billing.index') }}" class="pb-back-link inv-back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Billing
        </a>

        <div class="inv-header__row">
            <h1 class="page-title inv-header__title">Invoice {{ $invoice->invoice_no }}</h1>
            <span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }} status-badge--lg">
                {{ $invoice->status }}
            </span>
        </div>
    </div>

    <div class="billing-grid">

        {{-- Left column: Appointment Information + Invoice Items.
             display:contents on mobile (so the grid below controls order),
             a real flex column on desktop (so this column's height is
             independent of the right column — no leftover gap). --}}
        <div class="inv-col-left">

            {{-- ================= APPOINTMENT INFORMATION ================= --}}
            @if($invoice->appointment)
            <div class="billing-card inv-area-appt">
                <h2 class="billing-card__title"><i class="fa-solid fa-calendar-check"></i> Appointment Information</h2>
                <dl class="billing-info-list">
                    <div><dt>Reference No.</dt><dd>{{ $invoice->appointment->reference_no }}</dd></div>
                    <div><dt>Date</dt><dd>{{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}</dd></div>
                </dl>
            </div>
            @endif

            {{-- ================= INVOICE ITEMS ================= --}}
            <div class="billing-card inv-area-items">
                <h2 class="billing-card__title"><i class="fa-solid fa-file-invoice"></i> Invoice Items</h2>

                <!-- Desktop / tablet: item table (scrolls only within this wrapper if needed) -->
                <div class="billing-items-table-wrap">
                    <table class="billing-items-table">
                        <thead>
                            <tr><th>Item</th><th class="num">Qty</th><th class="num">Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td class="num">{{ $item->quantity }}</td>
                                    <td class="num">₱{{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty-row">No items on this invoice.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile: item cards -->
                <div class="billing-items-cards">
                    @forelse($invoice->items as $item)
                        <div class="billing-item-card">
                            <span class="billing-item-card__name">{{ $item->description }}</span>
                            <div class="billing-item-card__meta">
                                <span>Qty: {{ $item->quantity }}</span>
                                <span class="billing-item-card__amount">₱{{ number_format($item->amount, 2) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="empty-row">No items on this invoice.</p>
                    @endforelse
                </div>

                <div class="billing-totals">
                    <div><span>Subtotal</span><span>₱{{ number_format($invoice->subtotal, 2) }}</span></div>
                    @if($invoice->discount > 0)
                        <div><span>Discount</span><span>-₱{{ number_format($invoice->discount, 2) }}</span></div>
                    @endif
                    <div class="billing-totals__bold"><span>Total Amount</span><span>₱{{ number_format($invoice->total_amount, 2) }}</span></div>
                    <div><span>Amount Paid</span><span>₱{{ number_format($invoice->amount_paid, 2) }}</span></div>
                    <div class="billing-totals__bold billing-totals__balance"><span>Remaining Balance</span><span>₱{{ number_format($invoice->balance, 2) }}</span></div>
                </div>
            </div>

        </div>

        {{-- Right column: Payment Summary + Payment History (same trick). --}}
        <div class="inv-col-right">

            {{-- ================= PAYMENT SUMMARY ================= --}}
            <div class="billing-card inv-area-summary">
                <h2 class="billing-card__title"><i class="fa-solid fa-wallet"></i> Payment Summary</h2>

                <div class="pay-summary">
                    <div class="pay-summary__row">
                        <span class="pay-summary__label">Total Amount</span>
                        <span class="pay-summary__value">₱{{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    <div class="pay-summary__row">
                        <span class="pay-summary__label">Amount Paid</span>
                        <span class="pay-summary__value pay-summary__value--paid">₱{{ number_format($invoice->amount_paid, 2) }}</span>
                    </div>
                    <div class="pay-summary__balance {{ $invoice->balance > 0 ? 'pay-summary__balance--due' : 'pay-summary__balance--clear' }}">
                        <span class="pay-summary__label">Remaining Balance</span>
                        <span class="pay-summary__balance-value">₱{{ number_format($invoice->balance, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- ================= PAYMENT HISTORY ================= --}}
            <div class="billing-card inv-area-history">
                <h2 class="billing-card__title"><i class="fa-solid fa-clock-rotate-left"></i> Payment History</h2>

                @if(!auth()->user()->hasPermission('view_payment_history'))
                    <p class="billing-empty">You do not have permission to view payment history.</p>
                @else
                    @forelse($invoice->payments as $payment)
                        <div class="payment-row">
                            <div class="payment-row__main">
                                <span class="payment-method-chip payment-method-chip--{{ strtolower($payment->payment_method) }}">
                                    {{ $payment->payment_method }}
                                </span>
                                <span class="payment-amount">₱{{ number_format($payment->amount, 2) }}</span>
                            </div>
                            <div class="payment-row__meta">
                                {{ $payment->paid_at->format('M d, Y · h:i A') }}
                            </div>
                            <a href="{{ route('patient.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                               class="btn-view-receipt" target="_blank">
                                <i class="fa-solid fa-receipt"></i> View Receipt
                            </a>
                        </div>
                    @empty
                        <div class="billing-empty-state">
                            <i class="fa-solid fa-receipt"></i>
                            <p class="billing-empty">No payments recorded yet.</p>
                            <p class="billing-empty billing-empty--muted">Please pay at the clinic counter (Cash or GCash).</p>
                        </div>
                    @endforelse
                @endif
            </div>

        </div>

    </div>

</div>
@endsection