@extends('layouts.admin')

@section('head')
{{-- Same design as the Staff invoice page (hero, summary cards, icon-badge cards).
     Admin is read-only here: billing is monitored, payments are recorded by Staff. --}}
<link rel="stylesheet" href="{{ asset('css/staff-billing.css') }}">
<link rel="stylesheet" href="{{ asset('css/staff-billing-show.css') }}">
@endsection

@section('content')
@php
    $fmtRate = fn ($r) => rtrim(rtrim(number_format((float) $r, 2), '0'), '.');
@endphp
<div class="container bs-page">

    {{-- BACK LINK --}}
    <a href="{{ route('admin.billing.index') }}" class="billing-back-link">
        <i class='bx bx-arrow-back'></i> Back to Billing
    </a>

    {{-- HERO --}}
    <div class="bs-hero">
        <div class="bs-hero__left">
            <div class="bs-hero__icon"><i class='bx bx-receipt'></i></div>
            <div>
                <h1 class="page-title">Invoice {{ $invoice->invoice_no }}</h1>
                <p class="bs-hero__sub">{{ $invoice->patientName() }}</p>
                @if($invoice->appointment)
                    <div class="bs-hero__chips">
                        <span class="bs-chip"><i class='bx bx-calendar-check'></i> {{ $invoice->appointment->reference_no }}</span>
                        <span class="bs-chip"><i class='bx bx-calendar'></i> {{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}</span>
                    </div>
                @endif
            </div>
        </div>
        <span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }} status-badge--lg">
            {{ $invoice->status }}
        </span>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="bs-summary">
        <div class="bs-stat">
            <div class="bs-stat__icon"><i class='bx bx-receipt'></i></div>
            <div>
                <p>Total Amount</p>
                <h2>₱{{ number_format($invoice->total_amount, 2) }}</h2>
            </div>
        </div>
        <div class="bs-stat bs-stat--green">
            <div class="bs-stat__icon"><i class='bx bx-check-circle'></i></div>
            <div>
                <p>Amount Paid</p>
                <h2>₱{{ number_format($invoice->amount_paid, 2) }}</h2>
            </div>
        </div>
        <div class="bs-stat bs-stat--amber">
            <div class="bs-stat__icon"><i class='bx bx-wallet'></i></div>
            <div>
                <p>Remaining Balance</p>
                <h2>₱{{ number_format($invoice->balance, 2) }}</h2>
            </div>
        </div>
    </div>

    <div class="billing-grid">

        <!-- ============ LEFT: INVOICE DETAILS ============ -->
        <div class="billing-col-left">

            <div class="billing-card">
                <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-user'></i></span> Patient</h2>
                <dl class="billing-info-list">
                    <div><dt>Name</dt><dd>{{ $invoice->patientName() }}</dd></div>
                    <div><dt>Type</dt><dd>{{ $invoice->walkin_patient_id ? 'Walk-in' : 'Registered' }}</dd></div>
                    @if($invoice->appointment)
                        <div><dt>Appointment</dt><dd>{{ $invoice->appointment->reference_no }}</dd></div>
                        <div><dt>Consultation Date</dt><dd>{{ \Carbon\Carbon::parse($invoice->appointment->appointment_date)->format('F d, Y') }}</dd></div>
                    @endif
                    <div><dt>Prepared By</dt><dd>{{ trim(($invoice->createdBy->first_name ?? '—') . ' ' . ($invoice->createdBy->last_name ?? '')) }}</dd></div>
                </dl>
            </div>

            <div class="billing-card">
                <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-file'></i></span> Bill / Invoice</h2>

                <div class="bs-table-wrap">
                <table class="billing-items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th class="num">Qty</th>
                            <th class="num">Unit Price</th>
                            <th>Discount</th>
                            <th class="num">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td><span class="item-type-chip item-type-chip--{{ $item->item_type }}">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span></td>
                                <td class="num">{{ $item->quantity }}</td>
                                <td class="num">₱{{ number_format($item->unit_price, 2) }}</td>
                                <td>
                                    @if($item->isWaiver())
                                        <span>{{ $item->discountLabel() }}</span>
                                        <div class="discount-hint">-₱{{ number_format($item->discount_amount, 2) }} waived (original fee ₱{{ number_format($item->grossAmount(), 2) }})</div>
                                        @if($item->waiver_notes)
                                            <div class="discount-hint">Notes: {{ $item->waiver_notes }}</div>
                                        @endif
                                        <div class="discount-hint">
                                            Applied by
                                            {{ $item->waivedBy ? 'Dr. ' . $item->waivedBy->first_name . ' ' . $item->waivedBy->last_name : 'the Doctor' }}
                                            @if($item->waived_at) · {{ $item->waived_at->format('M d, Y h:i A') }} @endif
                                        </div>
                                    @elseif($item->discount_type)
                                        <span>{{ $item->discountLabel() }} · {{ $fmtRate($item->discount_rate) }}%</span>
                                        <div class="discount-hint">-₱{{ number_format($item->discount_amount, 2) }}</div>
                                    @else
                                        <span class="discount-none">—</span>
                                    @endif
                                </td>
                                <td class="num item-net">₱{{ number_format($item->netAmount(), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-row">No items on this invoice.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                <div class="billing-totals">
                    <div><span>Subtotal</span><span>₱{{ number_format($invoice->subtotal, 2) }}</span></div>
                    <div>
                        <span>Total Discount</span>
                        <span>{{ $invoice->discount > 0 ? '-' : '' }}₱{{ number_format($invoice->discount, 2) }}</span>
                    </div>
                    <div class="billing-totals__bold"><span>Total Amount</span><span>₱{{ number_format($invoice->total_amount, 2) }}</span></div>
                    <div><span>Amount Paid</span><span>₱{{ number_format($invoice->amount_paid, 2) }}</span></div>
                    <div class="billing-totals__bold"><span>Remaining Balance</span><span>₱{{ number_format($invoice->balance, 2) }}</span></div>
                </div>

                @if($invoice->discount_id_number)
                    <div class="discount-locked-note">
                        <i class='bx bx-id-card'></i>
                        <span>Discount ID No.: <strong>{{ $invoice->discount_id_number }}</strong>
                        @if($invoice->discountIdVerifiedBy) · entered by {{ $invoice->discountIdVerifiedBy->first_name }} {{ $invoice->discountIdVerifiedBy->last_name }} @endif
                        @if($invoice->discount_id_verified_at) · {{ $invoice->discount_id_verified_at->format('M d, Y h:i A') }} @endif</span>
                    </div>
                @endif

                @if($invoice->status === 'Cancelled' && $invoice->voided_at)
                    <div class="discount-locked-note">
                        <i class='bx bx-x-circle'></i>
                        <span>Voided {{ $invoice->voided_at->format('M d, Y h:i A') }}
                        @if($invoice->voidedBy) by {{ $invoice->voidedBy->first_name }} {{ $invoice->voidedBy->last_name }} @endif
                        — Reason: {{ $invoice->void_reason }}</span>
                    </div>
                @endif
            </div>

        </div>

        <!-- ============ RIGHT: PAYMENT HISTORY ============ -->
        <div class="billing-col-right">
            <div class="billing-card">
                <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-history'></i></span> Payment History</h2>

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
                            · Received by {{ $payment->receivedByName() }}
                            @if($payment->payment_method === 'GCash')
                                · Ref# {{ $payment->reference_number }}
                            @endif
                        </div>
                        <a href="{{ route('admin.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                           class="btn-view-receipt" target="_blank">
                            <i class='bx bx-receipt'></i> View Receipt
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