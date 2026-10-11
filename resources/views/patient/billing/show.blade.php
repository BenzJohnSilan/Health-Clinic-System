@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/patient-invoice.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
@php
    $paidPercent = $invoice->total_amount > 0
        ? min(100, (int) round(($invoice->amount_paid / $invoice->total_amount) * 100))
        : 0;
    $itemCount = $invoice->items->count();
@endphp

<div class="inv-page">

    {{-- ================= BACK LINK ================= --}}
    <a href="{{ route('patient.billing.index') }}" class="inv-back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Billing
    </a>

    {{-- ================= HERO ================= --}}
    <div class="inv-hero">
        <div class="inv-hero__main">
            <div class="inv-hero__icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <div class="inv-hero__text">
                <h1 class="inv-hero__title">Invoice {{ $invoice->invoice_no }}</h1>
                @if($invoice->appointment)
                    <div class="inv-hero__meta">
                        <span class="inv-hero__chip">
                            <i class="fa-solid fa-calendar-check"></i>
                            {{ $invoice->appointment->reference_no }}
                        </span>
                        <span class="inv-hero__chip">
                            <i class="fa-regular fa-calendar"></i>
                            {{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <span class="inv-status inv-status--{{ $invoice->statusBadgeClass() }}">
            <span class="inv-status__dot"></span>{{ $invoice->status }}
        </span>
    </div>

    <div class="inv-grid">

        {{-- ================= LEFT: INVOICE ITEMS ================= --}}
        <div class="inv-col-left">
            <div class="inv-card inv-area-items">

                <div class="inv-card__header">
                    <div class="inv-card__title">
                        <div class="inv-card__icon inv-card__icon--purple"><i class="fa-solid fa-file-invoice"></i></div>
                        <h3>Invoice Items</h3>
                    </div>
                    <span class="inv-count-pill">{{ $itemCount }} {{ \Illuminate\Support\Str::plural('item', $itemCount) }}</span>
                </div>

                <!-- Desktop / tablet: item table -->
                <div class="inv-table-wrap">
                    <table class="inv-table">
                        <thead>
                            <tr><th>Item</th><th class="num">Qty</th><th class="num">Amount</th></tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->items as $item)
                                <tr>
                                    <td>{{ $item->description }}
                                        @if($item->isWaiver())<br><small style="color:#6b7280;">Fee waived (−₱{{ number_format($item->discount_amount, 2) }}) — {{ $item->waiverReasonLabel() }}</small>@endif
                                    </td>
                                    <td class="num">{{ $item->quantity }}</td>
                                    <td class="num">₱{{ number_format($item->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="inv-empty-row">No items on this invoice.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile: item cards -->
                <div class="inv-item-cards">
                    @forelse($invoice->items as $item)
                        <div class="inv-item-card">
                            <span class="inv-item-card__name">{{ $item->description }}</span>
                        @if($item->isWaiver())<small style="color:#6b7280;">Fee waived (−₱{{ number_format($item->discount_amount, 2) }}) — {{ $item->waiverReasonLabel() }}</small>@endif
                            <div class="inv-item-card__meta">
                                <span>Qty: {{ $item->quantity }}</span>
                                <span class="inv-item-card__amount">₱{{ number_format($item->amount, 2) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="inv-empty-row">No items on this invoice.</p>
                    @endforelse
                </div>

                <div class="inv-totals">
                    <div class="inv-totals__row">
                        <span>Subtotal</span>
                        <span>₱{{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    @if($invoice->discount > 0)
                        <div class="inv-totals__row inv-totals__row--discount">
                            <span>Discount</span>
                            <span>-₱{{ number_format($invoice->discount, 2) }}</span>
                        </div>
                    @endif
                    <div class="inv-totals__row inv-totals__row--total">
                        <span>Total Amount</span>
                        <span>₱{{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= RIGHT: SUMMARY + HISTORY ================= --}}
        <div class="inv-col-right">

            {{-- PAYMENT SUMMARY --}}
            <div class="inv-card inv-area-summary">
                <div class="inv-card__header">
                    <div class="inv-card__title">
                        <div class="inv-card__icon inv-card__icon--blue"><i class="fa-solid fa-wallet"></i></div>
                        <h3>Payment Summary</h3>
                    </div>
                </div>

                <div class="inv-summary">
                    <div class="inv-summary__row">
                        <span class="inv-summary__label">Total Amount</span>
                        <span class="inv-summary__value">₱{{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    <div class="inv-summary__row">
                        <span class="inv-summary__label">Amount Paid</span>
                        <span class="inv-summary__value inv-summary__value--paid">₱{{ number_format($invoice->amount_paid, 2) }}</span>
                    </div>

                    <div class="inv-progress" title="{{ $paidPercent }}% paid">
                        <div class="inv-progress__bar" style="width: {{ $paidPercent }}%"></div>
                    </div>
                    <div class="inv-progress__label">{{ $paidPercent }}% paid</div>

                    <div class="inv-balance {{ $invoice->balance > 0 ? 'inv-balance--due' : 'inv-balance--clear' }}">
                        <span class="inv-balance__label">Remaining Balance</span>
                        <span class="inv-balance__value">₱{{ number_format($invoice->balance, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- PAYMENT HISTORY --}}
            <div class="inv-card inv-area-history">
                <div class="inv-card__header">
                    <div class="inv-card__title">
                        <div class="inv-card__icon inv-card__icon--green"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <h3>Payment History</h3>
                    </div>
                </div>

                @if(!auth()->user()->hasPermission('view_payment_history'))
                    <p class="inv-muted-note">You do not have permission to view payment history.</p>
                @else
                    @forelse($invoice->payments as $payment)
                        <div class="inv-payment">
                            <div class="inv-payment__main">
                                <span class="inv-method inv-method--{{ strtolower($payment->payment_method) }}">
                                    {{ $payment->payment_method }}
                                </span>
                                <span class="inv-payment__amount">₱{{ number_format($payment->amount, 2) }}</span>
                            </div>
                            <div class="inv-payment__meta">
                                <i class="fa-regular fa-clock"></i>
                                {{ $payment->paid_at->format('M d, Y · h:i A') }}
                            </div>
                            <div class="inv-payment__footer">
                                <a href="{{ route('patient.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                                   class="inv-receipt-btn" target="_blank">
                                    <i class="fa-solid fa-receipt"></i> View Receipt
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="inv-empty">
                            <i class="fa-solid fa-receipt"></i>
                            <p>No payments recorded yet.</p>
                            <span>Please pay at the clinic counter (Cash or GCash).</span>
                        </div>
                    @endforelse
                @endif
            </div>

        </div>
    </div>

</div>
@endsection