@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/staff-billing.css') }}">
<link rel="stylesheet" href="{{ asset('css/staff-billing-show.css') }}">
@endsection

@section('content')
<div class="container bs-page">

    {{-- BACK LINK — on top of the hero, same as the Patient invoice page --}}
    <a href="{{ route('staff.billing.index') }}" class="billing-back-link">
        <i class='bx bx-arrow-back'></i> Back to Billing
    </a>

    {{-- HERO — same gradient card style as the Staff Dashboard greeting --}}
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
                // Discounts are editable only while the invoice is untouched by payment
                // (BillingService::applyItemDiscounts() re-checks this on the server).
                $canEditDiscount = auth()->user()->hasPermission('record_payment')
                    && $invoice->discountEditable()
                    && !$invoice->hasLegacyInvoiceDiscount();

                $fmtRate = fn ($r) => rtrim(rtrim(number_format((float) $r, 2), '0'), '.');
            @endphp

            <div class="billing-card">
                <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-file'></i></span> Bill / Invoice</h2>

                @if($canEditDiscount)
                <form method="POST" action="{{ route('staff.billing.discounts.update', $invoice->id) }}" id="discountForm">
                    @csrf
                @endif

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
                            {{-- Consultation fee waiver: the Doctor's decision. Shown locked with its
                                 reason/audit info; deliberately NOT a .discount-row, so Staff can neither
                                 change it nor does the discount preview script touch it. --}}
                            @if($item->isWaiver())
                                <tr class="waiver-row">
                                    <td>{{ $item->description }}</td>
                                    <td><span class="item-type-chip item-type-chip--{{ $item->item_type }}">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span></td>
                                    <td class="num">{{ $item->quantity }}</td>
                                    <td class="num">₱{{ number_format($item->unit_price, 2) }}</td>
                                    <td>
                                        <span>{{ $item->discountLabel() }}</span>
                                        <div class="discount-hint">-₱{{ number_format($item->discount_amount, 2) }} waived (original fee ₱{{ number_format($item->grossAmount(), 2) }})</div>
                                        @if($item->waiver_notes)
                                            <div class="discount-hint">Notes: {{ $item->waiver_notes }}</div>
                                        @endif
                                        <div class="discount-hint">
                                            Locked — applied by
                                            {{ $item->waivedBy ? 'Dr. ' . $item->waivedBy->first_name . ' ' . $item->waivedBy->last_name : 'the Doctor' }}
                                            @if($item->waived_at) · {{ $item->waived_at->format('M d, Y h:i A') }} @endif
                                        </div>
                                    </td>
                                    <td class="num item-net">₱{{ number_format($item->netAmount(), 2) }}</td>
                                </tr>
                                @continue
                            @endif
                            @php
                                $selType = old('items.' . $item->id . '.discount_type', $item->discount_type);
                                $selRate = old('items.' . $item->id . '.discount_rate', $item->discount_rate);
                            @endphp
                            <tr class="discount-row"
                                data-gross="{{ number_format($item->grossAmount(), 2, '.', '') }}"
                                data-saved-type="{{ $item->discount_type }}"
                                data-saved-rate="{{ $item->discount_rate !== null ? (float) $item->discount_rate : '' }}">
                                <td>{{ $item->description }}</td>
                                <td><span class="item-type-chip item-type-chip--{{ $item->item_type }}">{{ ucfirst(str_replace('_', ' ', $item->item_type)) }}</span></td>
                                <td class="num">{{ $item->quantity }}</td>
                                <td class="num">₱{{ number_format($item->unit_price, 2) }}</td>

                                <td>
                                    @if($canEditDiscount)
                                        <div class="discount-cell">
                                            <select name="items[{{ $item->id }}][discount_type]"
                                                    class="discount-select"
                                                    aria-label="Discount for {{ $item->description }}">
                                                <option value="">None</option>
                                                @foreach($discountTypes as $key => $type)
                                                    <option value="{{ $key }}"
                                                            data-editable="{{ !empty($type['rate_editable']) ? '1' : '0' }}"
                                                            data-rate="{{ $type['default_rate'] ?? '' }}"
                                                            @selected((string) $selType === (string) $key)>
                                                        {{ $type['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>

                                            <span class="discount-rate" style="{{ $selType ? '' : 'display:none;' }}">
                                                <input type="number" step="0.01" min="0" max="100"
                                                       name="items[{{ $item->id }}][discount_rate]"
                                                       class="discount-rate-input"
                                                       value="{{ $selRate }}"
                                                       placeholder="0"
                                                       aria-label="Discount rate for {{ $item->description }}"
                                                       {{ $selType ? '' : 'disabled' }}>%
                                            </span>
                                        </div>
                                        <div class="discount-preview discount-hint" style="{{ $item->discount_amount > 0 ? '' : 'display:none;' }}">
                                            @if($item->discount_amount > 0)
                                                -₱{{ number_format($item->discount_amount, 2) }}
                                            @endif
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

                @if($canEditDiscount)
                    @if($invoice->items->isNotEmpty())
                        <div class="discount-actions">
                            <button type="submit" class="btn-apply-discount" id="applyDiscountBtn">
                                <i class='bx bx-purchase-tag-alt'></i> Apply Discounts
                            </button>
                            <span class="discount-hint" id="discountDirtyHint" style="display:none;">
                                Unsaved changes. The amounts shown above are a preview until you click Apply Discounts.
                            </span>
                        </div>
                        <div class="discount-locked-note">
                            Before applying a Senior Citizen or PWD discount, verify the patient's eligibility
                            (e.g. valid ID) according to clinic policy. Each item can have only one discount.
                        </div>
                    @endif
                </form>
                @elseif($invoice->hasLegacyInvoiceDiscount())
                    <div class="discount-locked-note">
                        This invoice carries an invoice-level discount from before per-item discounts and cannot be edited here.
                    </div>
                @elseif($invoice->status === 'Cancelled')
                    <div class="discount-locked-note">This invoice has been cancelled. Discounts cannot be changed.</div>
                @elseif(!$invoice->discountEditable())
                    <div class="discount-locked-note">
                        <i class='bx bx-lock-alt'></i><span>Discounts are locked because a payment has already been recorded on this invoice.</span>
                    </div>
                @endif

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
            </div>

        </div>

        <!-- ============ RIGHT: RECORD PAYMENT + PAYMENT HISTORY (beside Bill / Invoice) ============ -->
        <div class="billing-col-right">
            <div class="billing-card">
                <h2 class="billing-card__title"><span class="bs-icon"><i class='bx bx-wallet'></i></span> Record Payment</h2>

                @if(!auth()->user()->hasPermission('record_payment'))
                    <p class="bs-state bs-state--muted"><i class='bx bx-lock-alt'></i><span>You do not have permission to record payments. Contact your Admin if you need this access.</span></p>
                @elseif($invoice->status === 'Paid')
                    <p class="bs-state"><i class='bx bx-check-circle'></i><span>This invoice is fully paid. No further payment needed.</span></p>
                @elseif($invoice->status === 'Cancelled')
                    <p class="bs-state bs-state--muted"><i class='bx bx-x-circle'></i><span>This invoice has been cancelled.</span></p>
                @else
                    <form method="POST" action="{{ route('staff.billing.payments.store', $invoice->id) }}" id="paymentForm">
                        @csrf

                        <div class="pay-summary">
                            <div><span>Invoice No.</span><strong>{{ $invoice->invoice_no }}</strong></div>
                            <div><span>Patient</span><strong>{{ $invoice->patientName() }}</strong></div>
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

                        {{-- Cash only. A cashier's helper to work out the change — it has no
                             name attribute, so it is NOT submitted and NOT stored (the payment
                             recorded is always the "Amount to Pay" above). --}}
                        <div class="form-group" id="cashTenderGroup">
                            <label class="form-label">Amount Tendered <span style="font-weight:400;color:#6b7280;">(cash received — for change only)</span></label>
                            <input type="number" step="0.01" min="0" id="amountTendered" class="form-input"
                                   placeholder="e.g. 600.00" inputmode="decimal">
                            <div class="pay-summary" id="payChange" style="display:none; margin-top:8px;">
                                <div><span>Change</span><strong id="payChangeValue">₱0.00</strong></div>
                            </div>
                            <small id="tenderWarning" style="display:none; color:#b91c1c;">
                                Amount tendered is less than the amount to pay.
                            </small>
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
                        <a href="{{ route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                           class="btn-view-receipt" target="_blank">
                            <i class='bx bx-receipt'></i> View Receipt
                        </a>
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

    function syncGcashField() {
        if (!gcashGroup) return;   // invoice already paid/cancelled → no payment form
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

    // ---------------------------------------------------------------
    // Cash: Amount Tendered -> Change. Display only (the tendered field
    // is never submitted); the server records exactly "Amount to Pay".
    // ---------------------------------------------------------------
    const tenderGroup   = document.getElementById('cashTenderGroup');
    const tenderInput   = document.getElementById('amountTendered');
    const payAmountInput = document.getElementById('payAmount');
    const changeBox     = document.getElementById('payChange');
    const changeValue   = document.getElementById('payChangeValue');
    const tenderWarning = document.getElementById('tenderWarning');

    function isCash() {
        return document.querySelector('input[name="payment_method"]:checked')?.value === 'Cash';
    }

    function tenderIsShort() {
        const tendered = parseFloat(tenderInput.value);
        const pay      = parseFloat(payAmountInput.value);
        return isCash() && !isNaN(tendered) && !isNaN(pay) && Math.round(tendered * 100) < Math.round(pay * 100);
    }

    function syncChange() {
        if (!tenderGroup) return;   // no payment form on this invoice
        tenderGroup.style.display = isCash() ? 'block' : 'none';

        const tendered = parseFloat(tenderInput.value);
        const pay      = parseFloat(payAmountInput.value);
        const hasBoth  = !isNaN(tendered) && !isNaN(pay);

        if (!isCash() || !hasBoth) {
            changeBox.style.display = 'none';
            tenderWarning.style.display = 'none';
            return;
        }

        const short = tenderIsShort();
        tenderWarning.style.display = short ? 'block' : 'none';
        changeBox.style.display = short ? 'none' : 'block';
        changeValue.textContent = '₱' + Math.max(0, (Math.round(tendered * 100) - Math.round(pay * 100)) / 100)
            .toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    if (tenderGroup) {
        methodRadios.forEach(r => r.addEventListener('change', syncChange));
        tenderInput.addEventListener('input', syncChange);
        payAmountInput.addEventListener('input', syncChange);
        syncChange();
    }

    // ---------------------------------------------------------------
    // Per-item discount: preview only. The server (BillingService) is
    // the authority and recalculates everything when Apply is clicked.
    // Rates and "editable" flags come from config('billing.discount_types')
    // through the <option data-*> attributes, nothing is hard-coded here.
    // ---------------------------------------------------------------
    const discountForm = document.getElementById('discountForm');
    if (discountForm) {
        const peso = n => '₱' + Number(n).toLocaleString('en-PH', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
        const dirtyHint = document.getElementById('discountDirtyHint');
        const applyBtn  = document.getElementById('applyDiscountBtn');

        function refreshRow(row) {
            const gross   = parseFloat(row.dataset.gross) || 0;
            const select  = row.querySelector('.discount-select');
            const rateBox = row.querySelector('.discount-rate');
            const rateIn  = row.querySelector('.discount-rate-input');
            const preview = row.querySelector('.discount-preview');
            const netCell = row.querySelector('.item-net');
            const opt     = select.options[select.selectedIndex];

            let rate = 0;

            if (!select.value) {
                rateBox.style.display = 'none';
                rateIn.disabled = true;
            } else {
                rateBox.style.display = '';
                rateIn.disabled = false;
                const editable = opt.dataset.editable === '1';
                rateIn.readOnly = !editable;
                if (!editable) {
                    rateIn.value = opt.dataset.rate;     // fixed rate from config
                }
                rateIn.required = editable;
                rate = parseFloat(rateIn.value);
                if (isNaN(rate) || rate < 0) rate = 0;
                if (rate > 100) rate = 100;
            }

            const disc = Math.min(Math.round(gross * rate) / 100, gross);
            netCell.textContent = peso(gross - disc);
            if (disc > 0) {
                preview.textContent = '-' + peso(disc);
                preview.style.display = '';
            } else {
                preview.textContent = '';
                preview.style.display = 'none';
            }

            const savedType = row.dataset.savedType || '';
            const savedRate = row.dataset.savedRate === '' ? NaN : parseFloat(row.dataset.savedRate);
            const nowRate   = parseFloat(rateIn.value);
            row.dataset.dirty = (select.value !== savedType
                || (select.value && !(nowRate === savedRate))) ? '1' : '0';
        }

        function refreshAll() {
            const rows = discountForm.querySelectorAll('.discount-row');
            rows.forEach(refreshRow);
            const anyDirty = Array.from(rows).some(r => r.dataset.dirty === '1');
            if (dirtyHint) dirtyHint.style.display = anyDirty ? '' : 'none';
        }

        discountForm.querySelectorAll('.discount-row').forEach(row => {
            // Switching type resets the rate so one type's rate never carries over.
            row.querySelector('.discount-select').addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const rateIn = row.querySelector('.discount-rate-input');
                rateIn.value = (this.value && opt.dataset.editable !== '1') ? opt.dataset.rate : '';
                refreshAll();
            });
            row.querySelector('.discount-rate-input').addEventListener('input', refreshAll);
        });
        refreshAll();

        discountForm.addEventListener('submit', function () {
            applyBtn.disabled = true;
            applyBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Applying…';
        });
    }

    // Prevent duplicate/double payment submission
    if (paymentForm) {
        paymentForm.addEventListener('submit', function (e) {
            // Cash received that does not cover the payment: stop before submitting.
            if (typeof tenderIsShort === 'function' && tenderIsShort()) {
                e.preventDefault();
                tenderInput.focus();
                return;
            }
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing…';
        });
    }
});
</script>
@endsection