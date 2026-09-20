@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-billing.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container">

    <div class="page-header">
        <div>
            <a href="{{ route('admin.billing.index') }}" class="billing-back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Billing
            </a>
            <h1 class="page-title">Invoice {{ $invoice->invoice_no }}</h1>
        </div>
        <span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }} status-badge--lg">
            {{ $invoice->status }}
        </span>
    </div>

    <div class="billing-grid">
        <div class="billing-col-left">

            <div class="billing-card">
                <h2 class="billing-card__title"><i class="fa-solid fa-user"></i> Patient</h2>
                <dl class="billing-info-list">
                    <div><dt>Name</dt><dd>{{ $invoice->patientName() }}</dd></div>
                    <div><dt>Type</dt><dd>{{ $invoice->walkin_patient_id ? 'Walk-in' : 'Registered' }}</dd></div>
                    @if($invoice->appointment)
                        <div><dt>Appointment</dt><dd>{{ $invoice->appointment->reference_no }}</dd></div>
                        <div><dt>Consultation Date</dt><dd>{{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}</dd></div>
                    @endif
                    <div><dt>Prepared By</dt><dd>{{ $invoice->createdBy->first_name ?? '—' }} {{ $invoice->createdBy->last_name ?? '' }}</dd></div>
                </dl>
            </div>

            <div class="billing-card">
                <h2 class="billing-card__title"><i class="fa-solid fa-file-invoice"></i> Bill / Invoice</h2>
                <table class="billing-items-table">
                    <thead>
                        <tr><th>Item</th><th>Type</th><th class="num">Qty</th><th class="num">Unit Price</th><th class="num">Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td><span class="item-type-chip item-type-chip--{{ $item->item_type }}">{{ ucfirst($item->item_type) }}</span></td>
                                <td class="num">{{ $item->quantity }}</td>
                                <td class="num">₱{{ number_format($item->unit_price, 2) }}</td>
                                <td class="num">₱{{ number_format($item->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-row">No items on this invoice.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="billing-totals">
                    <div><span>Subtotal</span><span>₱{{ number_format($invoice->subtotal, 2) }}</span></div>
                    @if($invoice->discount > 0)
                        <div><span>Discount</span><span>-₱{{ number_format($invoice->discount, 2) }}</span></div>
                    @endif
                    <div class="billing-totals__bold"><span>Total Amount</span><span>₱{{ number_format($invoice->total_amount, 2) }}</span></div>
                    <div><span>Amount Paid</span><span>₱{{ number_format($invoice->amount_paid, 2) }}</span></div>
                    <div class="billing-totals__bold"><span>Remaining Balance</span><span>₱{{ number_format($invoice->balance, 2) }}</span></div>
                </div>
            </div>

        </div>

        <div class="billing-col-right">
            <div class="billing-card">
                <h2 class="billing-card__title"><i class="fa-solid fa-clock-rotate-left"></i> Payment History</h2>

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
                            · Received by {{ $payment->receivedBy->first_name ?? '—' }}
                            @if($payment->payment_method === 'GCash')
                                · Ref# {{ $payment->reference_number }}
                            @endif
                        </div>
                        <a href="{{ route('admin.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                           class="btn-view-receipt" target="_blank">
                            <i class="fa-solid fa-receipt"></i> View Receipt
                        </a>
                    </div>
                @empty
                    <p class="billing-empty">No payments recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
