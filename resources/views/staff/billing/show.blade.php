@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-billing.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endsection

@section('content')
<div class="container">

    <div class="page-header">
        <div>
            <a href="{{ route('staff.billing.index') }}" class="billing-back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Billing
            </a>
            <h1 class="page-title">Invoice {{ $invoice->invoice_no }}</h1>
        </div>
        <span class="status-badge status-badge--{{ $invoice->statusBadgeClass() }} status-badge--lg">
            {{ $invoice->status }}
        </span>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="billing-grid">

        <!-- ============ LEFT: INVOICE DETAILS ============ -->
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
                </dl>
            </div>

            <div class="billing-card">
                <h2 class="billing-card__title"><i class="fa-solid fa-file-invoice"></i> Bill / Invoice</h2>
                <table class="billing-items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th class="num">Qty</th>
                            <th class="num">Unit Price</th>
                            <th class="num">Amount</th>
                        </tr>
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

            <div class="billing-card">
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
                            · Received by {{ $payment->receivedBy->first_name ?? '—' }}
                            @if($payment->payment_method === 'GCash')
                                · Ref# {{ $payment->reference_number }}
                            @endif
                        </div>
                        <a href="{{ route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                           class="btn-view-receipt" target="_blank">
                            <i class="fa-solid fa-receipt"></i> View Receipt
                        </a>
                    </div>
                @empty
                    <p class="billing-empty">No payments recorded yet.</p>
                @endforelse
                @endif
            </div>

        </div>

        <!-- ============ RIGHT: RECORD PAYMENT ============ -->
        <div class="billing-col-right">
            <div class="billing-card billing-card--sticky">
                <h2 class="billing-card__title"><i class="fa-solid fa-cash-register"></i> Record Payment</h2>

                @if(!auth()->user()->hasPermission('record_payment'))
                    <p class="billing-empty">You do not have permission to record payments. Contact your Admin if you need this access.</p>
                @elseif($invoice->status === 'Paid')
                    <p class="billing-empty">This invoice is fully paid. No further payment needed.</p>
                @elseif($invoice->status === 'Cancelled')
                    <p class="billing-empty">This invoice has been cancelled.</p>
                @else
                    <form method="POST" action="{{ route('staff.billing.payments.store', $invoice->id) }}" id="paymentForm">
                        @csrf

                        <div class="pay-summary">
                            <div><span>Invoice No.</span><strong>{{ $invoice->invoice_no }}</strong></div>
                            <div><span>Total Amount</span><strong>₱{{ number_format($invoice->total_amount, 2) }}</strong></div>
                            <div><span>Amount Paid</span><strong>₱{{ number_format($invoice->amount_paid, 2) }}</strong></div>
                            <div><span>Remaining Balance</span><strong>₱{{ number_format($invoice->balance, 2) }}</strong></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Method <span class="required">*</span></label>
                            <div class="pay-method-toggle">
                                <label class="pay-method-option">
                                    <input type="radio" name="payment_method" value="Cash" checked>
                                    <span><i class="fa-solid fa-money-bill-wave"></i> Cash</span>
                                </label>
                                <label class="pay-method-option">
                                    <input type="radio" name="payment_method" value="GCash">
                                    <span><i class="fa-solid fa-mobile-screen-button"></i> GCash</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Amount to Pay <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance }}"
                                   name="amount" id="payAmount" class="form-input"
                                   value="{{ old('amount', number_format((float) $invoice->balance, 2, '.', '')) }}" required>
                        </div>

                        <div class="form-group" id="gcashRefGroup" style="display:none;">
                            <label class="form-label">GCash Reference Number <span class="required">*</span></label>
                            <input type="text" name="reference_number" id="referenceNumber" class="form-input"
                                   value="{{ old('reference_number') }}" placeholder="e.g. 1234567890">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-input" rows="2" placeholder="Optional">{{ old('notes') }}</textarea>
                        </div>

                        <button type="submit" class="btn-confirm-payment" id="confirmPaymentBtn">
                            <i class="fa-solid fa-check"></i> Confirm Payment
                        </button>
                    </form>
                @endif
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const methodRadios = document.querySelectorAll('input[name="payment_method"]');
    const gcashGroup    = document.getElementById('gcashRefGroup');
    const referenceInput = document.getElementById('referenceNumber');
    const paymentForm   = document.getElementById('paymentForm');
    const confirmBtn     = document.getElementById('confirmPaymentBtn');

    function syncGcashField() {
        const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
        const isGcash  = selected === 'GCash';
        gcashGroup.style.display = isGcash ? 'block' : 'none';
        if (referenceInput) {
            if (isGcash) {
                referenceInput.setAttribute('required', 'required');
            } else {
                referenceInput.removeAttribute('required');
                referenceInput.value = '';
            }
        }
    }

    methodRadios.forEach(r => r.addEventListener('change', syncGcashField));
    syncGcashField();

    // Prevent duplicate/double payment submission
    if (paymentForm) {
        paymentForm.addEventListener('submit', function () {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing…';
        });
    }
});
</script>
@endsection
