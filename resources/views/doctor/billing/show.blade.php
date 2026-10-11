@extends('layouts.doctor')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-billing.css') }}">
<link rel="stylesheet" href="{{ asset('css/staff-billing-show.css') }}">
<style>
    /* Doctor-only bits that the Staff stylesheet does not cover */
    .bs-page .item-type-chip--medical_certificate { background: var(--amber-bg); color: var(--amber); }

    .bs-page .billing-card__header-row { margin-bottom: 18px; }
    .bs-page .billing-card__header-row .billing-card__title { margin: 0; }

    .bs-page .btn-edit-charges {
        background: #fff;
        color: var(--primary);
        border: 1.5px solid var(--primary);
        border-radius: var(--radius-sm);
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 700;
        transition: all .22s ease;
    }
    .bs-page .btn-edit-charges:hover { background: var(--primary); color: #fff; }

    .bs-page .edit-charges-panel { margin-top: 0; }

    .bs-page .charge-row { border: 1.5px solid #e5dcf2; border-radius: 10px; }
    .bs-page .charge-row:has(input:checked) { border-color: var(--primary); background: var(--primary-soft); }
    .bs-page .charge-name { font-weight: 700; color: var(--text-dark); }
    .bs-page .charge-qty { border: 1.5px solid #e5dcf2; border-radius: 8px; }
    .bs-page .charge-medicine-row { border-radius: 10px; }
    .bs-page .charge-est-total { border-top: 1px solid var(--border); }

    .bs-page .edit-charges-actions .btn-confirm-payment {
        width: auto;
        flex: 1;
        padding: 11px 16px;
        font-size: 13.5px;
        box-shadow: none;
    }
    .bs-page .btn-cancel-charges { border-radius: var(--radius-sm); padding: 11px 18px; font-weight: 700; }
</style>
@endsection

@section('content')
<div class="container bs-page">

    {{-- BACK LINK — on top of the hero, same as the Staff invoice page --}}
    <a href="{{ route('doctor.billing.index') }}" class="billing-back-link">
        <i class='bx bx-arrow-back'></i> Back to Billing
    </a>

    {{-- HERO — same gradient card style as the Staff invoice page --}}
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
                </dl>
            </div>

            @php
                $canEditCharges = auth()->user()->hasPermission('edit_invoice')
                    && $invoice->amount_paid <= 0
                    && $invoice->status !== 'Cancelled'
                    && $invoice->appointment;
            @endphp

            <div class="billing-card">
                <div class="billing-card__header-row">
                    <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-file'></i></span> Bill / Invoice</h2>
                    @if($canEditCharges)
                        <button type="button" id="editChargesToggle" class="btn-edit-charges">
                            <i class='bx bx-pencil'></i> Edit Charges
                        </button>
                    @endif
                </div>

                <div class="bs-table-wrap" id="invoiceItemsTable">
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
                                    @if($item->discount_type)
                                        <span>{{ $item->discountLabel() }} · {{ rtrim(rtrim(number_format((float) $item->discount_rate, 2), '0'), '.') }}%</span>
                                        <div class="discount-hint">-₱{{ number_format($item->discount_amount, 2) }}</div>
                                    @else
                                        <span class="discount-none">—</span>
                                    @endif
                                </td>
                                <td class="num item-net">₱{{ number_format($item->netAmount(), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-row">No items on this invoice yet.</td></tr>
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

                @if($canEditCharges)
                    <div id="editChargesPanel" class="edit-charges-panel" style="display:none;">
                        <form action="{{ route('doctor.appointments.saveCharges', $invoice->appointment_id) }}" method="POST" id="chargesForm">
                            @csrf
                            @method('PATCH')

                            @if($services->isEmpty())
                                <p class="billing-empty">No active services configured yet. Ask Admin to add one under Services / Fees.</p>
                            @else
                                @php $existingByService = $invoice->items->keyBy('service_id'); @endphp
                                <div class="charge-list">
                                    @foreach($services as $service)
                                        @php $existing = $existingByService->get($service->id); @endphp
                                        <label class="charge-row">
                                            <input type="checkbox" name="service_ids[]" value="{{ $service->id }}"
                                                   data-price="{{ $service->price }}" class="charge-check"
                                                   {{ $existing ? 'checked' : '' }}>
                                            <span class="charge-name">{{ $service->name }}</span>
                                            <span class="charge-price">₱{{ number_format($service->price, 2) }}</span>
                                            <input type="number" name="service_qty[{{ $service->id }}]" min="1"
                                                   value="{{ $existing->quantity ?? 1 }}" class="charge-qty">
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @if($dispensedMedicines->isNotEmpty())
                                <div class="charge-medicines">
                                    <div class="charge-medicines__label">Medicines (auto-included from prescriptions)</div>
                                    @foreach($dispensedMedicines as $rx)
                                        @if($rx->medicine)
                                            <div class="charge-medicine-row">
                                                <span>{{ $rx->medicine->medicine_name }} × {{ $rx->quantity_prescribed }}</span>
                                                <span>₱{{ number_format($rx->medicine->price * $rx->quantity_prescribed, 2) }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <div class="charge-est-total">
                                <span>Estimated Total</span>
                                <span id="chargesEstTotal">₱0.00</span>
                            </div>

                            <div class="edit-charges-actions">
                                <button type="button" id="cancelEditCharges" class="btn-cancel-charges">Cancel</button>
                                <button type="submit" class="btn-confirm-payment" {{ $services->isEmpty() && $dispensedMedicines->isEmpty() ? 'disabled' : '' }}>
                                    <i class='bx bx-check'></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>

        </div>

        <!-- ============ RIGHT: RECORD PAYMENT (record_payment only) + PAYMENT HISTORY ============ -->
        <div class="billing-col-right">

            @if(auth()->user()->hasPermission('record_payment'))
            <div class="billing-card">
                <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-wallet'></i></span> Record Payment</h2>

                @if($invoice->status === 'Paid')
                    <p class="bs-state"><i class='bx bx-check-circle'></i><span>This invoice is fully paid. No further payment needed.</span></p>
                @elseif($invoice->status === 'Cancelled')
                    <p class="bs-state bs-state--muted"><i class='bx bx-x-circle'></i><span>This invoice has been cancelled.</span></p>
                @else
                    <form method="POST" action="{{ route('doctor.billing.payments.store', $invoice->id) }}" id="paymentForm">
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
                                    <span><i class='bx bx-money'></i> Cash</span>
                                </label>
                                <label class="pay-method-option">
                                    <input type="radio" name="payment_method" value="GCash">
                                    <span><i class='bx bx-mobile-alt'></i> GCash</span>
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
                            <i class='bx bx-check'></i> Confirm Payment
                        </button>
                    </form>
                @endif
            </div>
            @endif

            <div class="billing-card">
                <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-history'></i></span> Payment History</h2>

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
                            @if(auth()->user()->hasPermission('view_payment_history'))
                                <a href="{{ route('doctor.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                                   class="btn-view-receipt" target="_blank">
                                    <i class='bx bx-receipt'></i> View Receipt
                                </a>
                            @endif
                        </div>
                    @empty
                        <p class="billing-empty">No payments recorded yet.</p>
                    @endforelse
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

    if (!paymentForm) {
        return;
    }

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

    paymentForm.addEventListener('submit', function () {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing…';
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const editToggle  = document.getElementById('editChargesToggle');
    const editPanel   = document.getElementById('editChargesPanel');
    const itemsTable  = document.getElementById('invoiceItemsTable');
    const cancelBtn   = document.getElementById('cancelEditCharges');
    const chargesForm = document.getElementById('chargesForm');
    const totalEl     = document.getElementById('chargesEstTotal');

    if (!editToggle || !editPanel) {
        return;
    }

    function openPanel() {
        editPanel.style.display = 'block';
        if (itemsTable) itemsTable.style.display = 'none';
        editToggle.style.display = 'none';
    }

    function closePanel() {
        editPanel.style.display = 'none';
        if (itemsTable) itemsTable.style.display = '';
        editToggle.style.display = '';
    }

    editToggle.addEventListener('click', openPanel);
    cancelBtn?.addEventListener('click', closePanel);

    if (chargesForm && totalEl) {
        const medicinesFlat = Array.from(chargesForm.querySelectorAll('.charge-medicine-row span:last-child'))
            .reduce((sum, el) => sum + (parseFloat(el.textContent.replace(/[^0-9.]/g, '')) || 0), 0);

        function recompute() {
            let sum = medicinesFlat;
            chargesForm.querySelectorAll('.charge-check').forEach(function (chk) {
                if (!chk.checked) return;
                const row = chk.closest('.charge-row');
                const qtyInput = row ? row.querySelector('.charge-qty') : null;
                const qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                sum += parseFloat(chk.dataset.price || 0) * qty;
            });
            totalEl.textContent = '₱' + sum.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        chargesForm.querySelectorAll('.charge-check, .charge-qty').forEach(function (el) {
            el.addEventListener('input', recompute);
            el.addEventListener('change', recompute);
        });

        recompute();
    }
});
</script>
@endsection