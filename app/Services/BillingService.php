<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MedicalCertificate;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Billing & Payments — shared business logic.
 *
 * Kept out of the controllers so the same rules (how an invoice is
 * built from an appointment, how a payment is validated and applied)
 * are guaranteed to be identical everywhere they're used.
 */
class BillingService
{
    /**
     * The standard Consultation fee (Admin > Services), or null when it has
     * been removed / deactivated — in which case no consultation line is added.
     */
    public function consultationService(): ?Service
    {
        return Service::where('name', Service::CONSULTATION)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Validate a Doctor's "Fee Waiver / No Charge" choice and turn it into
     * the small array stored on the draft / written onto the invoice line.
     *
     * Rules (server side — the browser is never trusted):
     *  - not requested -> null (no waiver)
     *  - reason must be one of config('billing.waiver_reasons') — only those
     *  - "Other" (notes_required) cannot be submitted without notes
     *  - there is no amount: a waiver always covers the whole Consultation line
     *
     * @throws ValidationException
     */
    public function buildWaiver(bool $requested, ?string $reason, ?string $notes, User $doctor): ?array
    {
        if (!$requested) {
            return null;
        }

        $reasons = config('billing.waiver_reasons', []);
        $reason  = trim((string) $reason);
        $notes   = trim((string) $notes);

        if ($reason === '' || !isset($reasons[$reason])) {
            throw ValidationException::withMessages([
                'waiver_reason' => 'Select a reason for the fee waiver.',
            ]);
        }

        if (!empty($reasons[$reason]['notes_required']) && $notes === '') {
            throw ValidationException::withMessages([
                'waiver_notes' => 'Please explain the reason for the waiver (required when "' . $reasons[$reason]['label'] . '" is selected).',
            ]);
        }

        if (mb_strlen($notes) > 500) {
            throw ValidationException::withMessages([
                'waiver_notes' => 'Waiver notes may not be longer than 500 characters.',
            ]);
        }

        return [
            'reason'     => $reason,
            'notes'      => $notes !== '' ? $notes : null,
            'applied_by' => $doctor->id,
            'applied_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Pay-after-service, step 1: the Doctor PREPARES charges while the
     * consultation is still In Progress. This only stores the selection on
     * the appointment (`billing_draft`) — it never creates an invoice, so
     * there is nothing for Staff to collect yet.
     *
     * The Consultation fee itself is NOT part of the draft: it is charged
     * automatically on completion. The draft holds only the OPTIONAL extra
     * services and the Doctor's fee-waiver decision (if any). Clinic
     * medicine is never drafted here — it is billed only when Staff
     * dispenses it (Prescription != Purchase).
     *
     * @param  array<int,array{service_id:int,quantity:int}>  $serviceLines
     * @param  array{reason:string,notes:?string,applied_by:int,applied_at:string}|null  $waiver
     */
    public function saveChargeDraft(Appointment $appointment, array $serviceLines, User $doctor, ?array $waiver = null): void
    {
        $appointment->forceFill([
            'billing_draft' => [
                'services' => array_values($serviceLines),
                'waiver'   => $waiver,
                'saved_by' => $doctor->id,
                'saved_at' => now()->toDateTimeString(),
            ],
        ])->save();
    }

    /**
     * Pay-after-service, step 2: called by Complete Consultation, right
     * after the appointment became Completed (same DB transaction). Turns
     * the prepared draft into the real invoice Staff will collect, then
     * clears the draft.
     *
     * The Consultation fee is ALWAYS included — with or without a draft —
     * so a consultation can no longer be completed "for free" by forgetting
     * a checkbox. The only way to remove the charge is the Doctor's explicit
     * fee waiver, which keeps the original fee on the invoice for audit.
     *
     * @param  array|null  $waiver          waiver submitted together with Complete
     * @param  bool        $overrideWaiver  true -> use $waiver (even null = "no
     *                                      waiver") instead of the saved draft's
     *
     * Returns the invoice, or null when none was created (no Consultation
     * service configured and nothing else to bill).
     */
    public function finalizeChargesOnCompletion(Appointment $appointment, User $doctor, ?array $waiver = null, bool $overrideWaiver = false): ?Invoice
    {
        $draft = is_array($appointment->billing_draft) ? $appointment->billing_draft : [];

        return DB::transaction(function () use ($appointment, $doctor, $draft, $waiver, $overrideWaiver) {
            $serviceLines = $draft['services'] ?? [];
            $waiverToUse  = $overrideWaiver ? $waiver : ($draft['waiver'] ?? null);

            $invoice = $this->syncInvoiceForAppointment($appointment, $serviceLines, $doctor, $waiverToUse);

            $appointment->forceFill(['billing_draft' => null])->save();

            return $invoice->exists ? $invoice : null;
        });
    }

    /**
     * Create the invoice for an appointment if it doesn't exist yet, or
     * refresh its line items if it does — WITHOUT ever touching payments
     * already recorded against it. Only allowed for a COMPLETED
     * consultation (pay-after-service).
     *
     * What gets (re)built here:
     *  - the Consultation line, exactly once (never a duplicate, however
     *    many times charges are saved or the consultation is completed),
     *    optionally carrying the Doctor's fee waiver;
     *  - the optional extra services in $serviceLines.
     *
     * What is deliberately LEFT ALONE:
     *  - clinic MEDICINE lines. They are created only by Staff dispensing
     *    (addDispensedMedicineToInvoice) from the quantity actually released,
     *    so a Doctor re-saving charges can neither create nor erase them.
     *  - Medical Certificate lines.
     *
     * @param  array<int,array{service_id:int,quantity:int}>  $serviceLines
     * @param  array|null  $waiver  see buildWaiver(); null = no waiver
     */
    public function syncInvoiceForAppointment(Appointment $appointment, array $serviceLines, User $doctor, ?array $waiver = null): Invoice
    {
        if ($appointment->status !== 'Completed') {
            throw ValidationException::withMessages([
                'charges' => 'An invoice can only be created after the consultation is completed.',
            ]);
        }

        return DB::transaction(function () use ($appointment, $serviceLines, $doctor, $waiver) {

            $invoice = $appointment->invoice()->lockForUpdate()->first();

            if (!$invoice) {
                $invoice = Invoice::create([
                    'appointment_id'     => $appointment->id,
                    'patient_id'         => $appointment->patient_id,
                    'walkin_patient_id'  => $appointment->walkin_patient_id,
                    'subtotal'           => 0,
                    'discount'           => 0,
                    'total_amount'       => 0,
                    'amount_paid'        => 0,
                    'balance'            => 0,
                    'status'             => 'Unpaid',
                    'created_by'         => $doctor->id,
                ]);
            }

            // Never rebuild line items for an invoice that already has a
            // payment recorded against it or is Cancelled — Staff/Admin own
            // it from that point on.
            if ($invoice->status === 'Cancelled'
                || (float) $invoice->amount_paid > 0
                || $invoice->payments()->exists()) {
                return $invoice;
            }

            // Staff may already have applied per-item discounts. The service
            // lines are rebuilt below, so remember each discount by what it
            // was applied to and put it back on the matching rebuilt line.
            // (A fee waiver is the Doctor's decision, re-derived from
            // $waiver every time — never carried over from the old line.)
            $previousDiscounts = $invoice->items()
                ->whereNotIn('item_type', ['medical_certificate', 'medicine'])
                ->whereNotNull('discount_type')
                ->where('discount_type', '!=', InvoiceItem::WAIVER_TYPE)
                ->get()
                ->mapWithKeys(fn (InvoiceItem $i) => [
                    $this->discountCarryKey($i->item_type, $i->service_id, $i->medicine_id) => [
                        'discount_type' => $i->discount_type,
                        'discount_rate' => $i->discount_rate,
                    ],
                ])
                ->all();

            // Did the invoice's discount come from its line items (a Staff
            // discount or a fee waiver) rather than being an old invoice-level
            // discount with nothing behind it? If so, the invoice's stored
            // discount is only a total of lines that are about to be rebuilt —
            // it must not survive the rebuild as if it were a "legacy" discount
            // (e.g. after the Doctor removes a waiver, or drops a discounted
            // service), or the totals would keep the old discount.
            $discountCameFromItems = $invoice->items()
                ->where(fn ($q) => $q->whereNotNull('discount_type')->orWhere('discount_amount', '>', 0))
                ->exists();

            // Only the service lines are rebuilt. Medical Certificate lines
            // and Staff-dispensed MEDICINE lines stay exactly as they are.
            $invoice->items()
                ->whereNotIn('item_type', ['medical_certificate', 'medicine'])
                ->delete();

            // ---- 1. Consultation — automatic, exactly one line ----
            $consultation = $this->consultationService();

            if ($consultation) {
                $price = round((float) $consultation->price, 2);

                $attributes = [
                    'invoice_id'  => $invoice->id,
                    'description' => $consultation->name,
                    'item_type'   => 'service',
                    'service_id'  => $consultation->id,
                    'quantity'    => 1,
                    'unit_price'  => $consultation->price,
                    'amount'      => $price,
                ];

                if ($waiver && $price > 0) {
                    // The original fee stays on the line (amount/unit_price);
                    // the waiver is a 100 % line discount, so
                    //   net = fee - waived = 0
                    // and the waiver's reason / notes / doctor / time are kept.
                    $attributes += [
                        'discount_type'   => InvoiceItem::WAIVER_TYPE,
                        'discount_rate'   => 100,
                        'discount_amount' => $price,
                        'waiver_reason'   => $waiver['reason'],
                        'waiver_notes'    => $waiver['notes'] ?? null,
                        'waived_by'       => $waiver['applied_by'] ?? $doctor->id,
                        'waived_at'       => $waiver['applied_at'] ?? now(),
                    ];
                } else {
                    $attributes += $previousDiscounts[$this->discountCarryKey('service', $consultation->id, null)] ?? [];
                }

                InvoiceItem::create($attributes);
            }

            // ---- 2. Optional extra services ----
            if (!empty($serviceLines)) {
                // The Medical Certificate fee is billed through the
                // certificate workflow (ensureInvoiceForMedicalCertificate),
                // never picked as a consultation charge.
                $services = Service::forConsultation()
                    ->whereIn('id', array_column($serviceLines, 'service_id'))
                    ->get()
                    ->keyBy('id');

                foreach ($serviceLines as $line) {
                    $service = $services->get($line['service_id']);
                    if (!$service) {
                        continue;
                    }

                    // The consultation already has its own automatic line;
                    // never add it a second time.
                    if ($consultation && $service->id === $consultation->id) {
                        continue;
                    }

                    $qty    = max((int) ($line['quantity'] ?? 1), 1);
                    $amount = round((float) $service->price * $qty, 2);

                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'description' => $service->name,
                        'item_type'   => 'service',
                        'service_id'  => $service->id,
                        'quantity'    => $qty,
                        'unit_price'  => $service->price,
                        'amount'      => $amount,
                    ] + ($previousDiscounts[$this->discountCarryKey('service', $service->id, null)] ?? []));
                }
            }

            // Nothing left to bill (no Consultation service configured, no
            // extra services, and no medicine / certificate line on the
            // invoice): never keep a meaningless PHP 0.00 invoice. It has
            // no payments (checked above), so removing it loses nothing.
            if (!$invoice->items()->exists()) {
                $invoice->delete();

                return $invoice;
            }

            if ($discountCameFromItems && (float) $invoice->discount > 0) {
                $invoice->discount = 0;
                $invoice->save();
            }

            // Subtotal / discount / total / balance / status all come from
            // the line items (single source of truth).
            return $this->recalculateInvoiceTotals($invoice);
        });
    }

    /**
     * Bill a clinic medicine that Staff has just dispensed — the ONLY way
     * clinic medicine ever reaches an invoice.
     *
     * Called from StaffPrescriptionController::dispense(), inside the same
     * DB transaction that deducts the stock, with the quantity ACTUALLY
     * released. So, for a clinic medicine, always:
     *
     *     dispensed quantity = billed quantity = stock deducted
     *
     * (the quantity PRESCRIBED is a separate number and is never billed).
     *
     * Idempotent: invoice_items.prescription_id is UNIQUE and is checked
     * first, so a prescription can be billed only once. An "Other (Manual
     * Input)" prescription has no medicine and is refused outright.
     *
     * Which invoice gets the line:
     *  - the visit's invoice, while no payment has been recorded on it;
     *  - if that invoice is already Paid / Partially Paid / Cancelled it is
     *    never modified — the medicine goes on a separate invoice for the
     *    same patient (same approach as a late Medical Certificate), reused
     *    for further medicines of that visit while it is still unpaid.
     *
     * @throws ValidationException
     */
    public function addDispensedMedicineToInvoice(Prescription $prescription, int $quantity, User $staff): InvoiceItem
    {
        return DB::transaction(function () use ($prescription, $quantity, $staff) {

            // Billed once only.
            $existing = InvoiceItem::where('prescription_id', $prescription->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $prescription->loadMissing('medicine', 'appointment');
            $medicine    = $prescription->medicine;
            $appointment = $prescription->appointment;

            if (!$medicine || !$appointment) {
                throw ValidationException::withMessages([
                    'dispensed_quantity' => 'Only a clinic-inventory prescription can be billed. Outside-pharmacy prescriptions are never dispensed or billed by the clinic.',
                ]);
            }

            if ($quantity < 1 || $quantity > (int) $prescription->quantity_prescribed) {
                throw ValidationException::withMessages([
                    'dispensed_quantity' => 'Invalid quantity to bill.',
                ]);
            }

            // ---- an older invoice may already bill this very medicine ----
            // Before dispensing drove billing, the Doctor could tick a
            // prescription as "buying", which wrote a medicine line with no
            // prescription link. Adopt that line instead of billing the same
            // medicine a second time. While the invoice is still open its
            // quantity becomes the quantity actually dispensed; a line that
            // is already paid / finalized is only linked, never altered.
            $legacy = InvoiceItem::whereNull('prescription_id')
                ->where('item_type', 'medicine')
                ->where('medicine_id', $medicine->id)
                ->whereHas('invoice', fn ($q) => $q->where('appointment_id', $appointment->id))
                ->lockForUpdate()
                ->first();

            if ($legacy) {
                $legacyInvoice = Invoice::whereKey($legacy->invoice_id)->lockForUpdate()->first();
                $open          = $legacyInvoice->isOpenForCharges();

                $legacy->prescription_id = $prescription->id;
                if ($open) {
                    $legacy->quantity = $quantity;
                }
                $legacy->save();

                if ($open) {
                    $this->recalculateInvoiceTotals($legacyInvoice);
                }

                return $legacy->fresh();
            }

            // ---- pick the invoice ----
            $invoice = Invoice::where('appointment_id', $appointment->id)->lockForUpdate()->first();

            if ($invoice && !$invoice->isOpenForCharges()) {
                $prescriptionIds = Prescription::where('appointment_id', $appointment->id)->select('id');

                $separate = Invoice::whereNull('appointment_id')
                    ->whereHas('items', fn ($q) => $q->where('item_type', 'medicine')->whereIn('prescription_id', $prescriptionIds))
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $invoice = ($separate && $separate->isOpenForCharges()) ? $separate : null;

                if (!$invoice) {
                    $invoice = $this->newMedicineInvoice($appointment, null, $staff);
                }
            } elseif (!$invoice) {
                $invoice = $this->newMedicineInvoice($appointment, $appointment->id, $staff);
            }

            $unitPrice = round((float) $medicine->price, 2);

            $item = InvoiceItem::create([
                'invoice_id'      => $invoice->id,
                'description'     => $medicine->medicine_name,
                'item_type'       => 'medicine',
                'medicine_id'     => $medicine->id,
                'prescription_id' => $prescription->id,
                'quantity'        => $quantity,
                'unit_price'      => $unitPrice,
                'amount'          => round($unitPrice * $quantity, 2),
            ]);

            $this->recalculateInvoiceTotals($invoice);

            return $item;
        });
    }

    private function newMedicineInvoice(Appointment $appointment, ?int $appointmentId, User $creator): Invoice
    {
        return Invoice::create([
            'appointment_id'    => $appointmentId,
            'patient_id'        => $appointment->patient_id,
            'walkin_patient_id' => $appointment->walkin_patient_id,
            'subtotal'          => 0,
            'discount'          => 0,
            'total_amount'      => 0,
            'amount_paid'       => 0,
            'balance'           => 0,
            'status'            => 'Unpaid',
            'created_by'        => $creator->id,
        ]);
    }

    private function discountCarryKey(?string $itemType, $serviceId, $medicineId): string
    {
        return $itemType . ':' . ($serviceId ?? '') . ':' . ($medicineId ?? '');
    }

    /**
     * Recompute an invoice's money fields from its line items.
     *
     *  1. gross    = quantity x unit_price            (invoice_items.amount)
     *  2. discount = gross x discount_rate / 100      (invoice_items.discount_amount)
     *  3. net      = gross - discount
     *  4. invoices.subtotal     = SUM(gross)
     *  5. invoices.discount     = SUM(discount_amount)
     *  6. invoices.total_amount = SUM(net)   (the discount is NOT subtracted twice)
     *  7. amount_paid is preserved, balance = total - amount_paid
     *  8. status follows the existing Unpaid / Partially Paid / Paid rules
     *
     * Never touches a Cancelled invoice, nor one that already has a payment
     * recorded against it (a finalized financial transaction).
     *
     * An older invoice that carries a discount on the invoice itself with no
     * per-item discount behind it keeps that discount untouched
     * ($keepLegacyInvoiceDiscount). applyItemDiscounts() turns that off — it
     * has already refused legacy invoices, so any stale value there is ours.
     */
    public function recalculateInvoiceTotals(Invoice $invoice, bool $keepLegacyInvoiceDiscount = true): Invoice
    {
        if ($invoice->status === 'Cancelled'
            || (float) $invoice->amount_paid > 0
            || $invoice->payments()->exists()) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $keepLegacyInvoiceDiscount) {
            $items = $invoice->items()->get();

            $subtotal     = 0.0;
            $itemDiscount = 0.0;

            foreach ($items as $item) {
                $gross = round(max((int) $item->quantity, 0) * (float) $item->unit_price, 2);
                $rate  = $item->discount_type ? (float) $item->discount_rate : 0.0;
                $disc  = $rate > 0 ? min(round($gross * $rate / 100, 2), $gross) : 0.0;

                $item->amount          = $gross;
                $item->discount_amount = $disc;
                if ($item->isDirty()) {
                    $item->save();
                }

                $subtotal     += $gross;
                $itemDiscount += $disc;
            }

            $discount = $itemDiscount;
            if ($keepLegacyInvoiceDiscount
                && $itemDiscount < 0.005
                && (float) $invoice->discount > 0.004
                && $items->every(fn ($i) => !$i->discount_type)) {
                $discount = min((float) $invoice->discount, $subtotal);
            }

            $total   = max($subtotal - $discount, 0);
            $paid    = (float) $invoice->amount_paid;
            $balance = max($total - $paid, 0);

            $invoice->subtotal     = round($subtotal, 2);
            $invoice->discount     = round($discount, 2);
            $invoice->total_amount = round($total, 2);
            $invoice->balance      = round($balance, 2);
            // A zero-total invoice with no payment (e.g. a fully waived
            // consultation and nothing else) has nothing to collect, so it
            // is settled — otherwise it would sit as "Unpaid" with a ₱0
            // balance that can never be paid. Adding a billable line later
            // (a dispensed medicine) recalculates it back to Unpaid.
            $invoice->status       = $paid > 0
                ? ($balance <= 0.004 ? 'Paid' : 'Partially Paid')
                : (($total <= 0.004 && $items->isNotEmpty()) ? 'Paid' : 'Unpaid');
            $invoice->save();

            return $invoice;
        });
    }

    /**
     * Apply per-item discounts chosen by Staff, then recalculate the
     * invoice. $inputs is keyed by invoice item id:
     *   [itemId => ['discount_type' => 'senior_citizen'|...|'' , 'discount_rate' => '12.5']]
     *
     * Rules (enforced here, never trusted from the browser):
     *  - only while the invoice has no payment and is not Cancelled
     *  - discount_type must come from config('billing.discount_types');
     *    one type per item, so discounts never stack on an item
     *  - fixed-rate types (Senior Citizen / PWD) always use the configured
     *    rate; the submitted rate is ignored
     *  - authorized-percentage types need a rate between 0 and 100
     *  - items not listed in $inputs are left unchanged
     *
     * @throws ValidationException
     */
    public function applyItemDiscounts(Invoice $invoice, array $inputs): Invoice
    {
        $types = config('billing.discount_types', []);

        return DB::transaction(function () use ($invoice, $inputs, $types) {

            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'Cancelled') {
                throw ValidationException::withMessages([
                    'discount' => 'This invoice has been cancelled. Discounts can no longer be changed.',
                ]);
            }

            if (!$locked->isCollectible()) {
                throw ValidationException::withMessages([
                    'discount' => 'This invoice is not available yet — the consultation has not been completed.',
                ]);
            }

            if (!$locked->discountEditable()) {
                throw ValidationException::withMessages([
                    'discount' => 'Discounts are locked because a payment has already been recorded on this invoice.',
                ]);
            }

            if ($locked->hasLegacyInvoiceDiscount()) {
                throw ValidationException::withMessages([
                    'discount' => 'This invoice already carries an invoice-level discount and cannot use per-item discounts.',
                ]);
            }

            $items  = $locked->items()->get()->keyBy('id');
            $errors = [];

            foreach ($inputs as $itemId => $row) {
                $item = $items->get((int) $itemId);
                if (!$item) {
                    $errors['discount'] = 'One of the submitted items does not belong to this invoice.';
                    continue;
                }

                // A Consultation fee waiver belongs to the Doctor: Staff can
                // neither change nor clear it, whatever is submitted.
                if ($item->isWaiver()) {
                    continue;
                }

                $key = trim((string) ($row['discount_type'] ?? ''));

                if ($key === '' || strtolower($key) === 'none') {
                    $item->discount_type = null;
                    $item->discount_rate = null;
                    continue;
                }

                if (!isset($types[$key])) {
                    $errors["items.{$item->id}.discount_type"] = "Select a valid discount for \"{$item->description}\".";
                    continue;
                }

                $rate = $types[$key]['rate_editable']
                    ? ($row['discount_rate'] ?? null)
                    : $types[$key]['default_rate'];

                if (!is_numeric($rate) || (float) $rate < 0 || (float) $rate > 100) {
                    $errors["items.{$item->id}.discount_rate"] =
                        "Discount rate for \"{$item->description}\" must be between 0 and 100.";
                    continue;
                }

                $rate = round((float) $rate, 2);

                // A 0% discount is no discount.
                $item->discount_type = $rate > 0 ? $key : null;
                $item->discount_rate = $rate > 0 ? $rate : null;
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($items as $item) {
                if ($item->isDirty()) {
                    $item->save();
                }
            }

            return $this->recalculateInvoiceTotals($locked, false);
        });
    }

    /**
     * Bill a Medical Certificate (idempotent).
     *
     * Where the fee goes:
     *  - SAME invoice: the certificate's appointment already has a
     *    consultation invoice that is still Unpaid, has no payment recorded
     *    and is not Cancelled (Scenario A — certificate requested during
     *    the visit, before payment). The fee is added as one more
     *    `medical_certificate` line, so the patient pays one total.
     *  - SEPARATE invoice: anything else (Scenario B) — the consultation
     *    invoice is Paid / Partially Paid / Cancelled, or the appointment has
     *    no invoice yet. A new invoice with no appointment is created
     *    (`appointment_id` = null, so Appointment::invoice() can never return
     *    it). A paid / finalized invoice is NEVER modified.
     *
     * The link is stored in `medical_certificates.invoice_id`. It is written
     * inside the same transaction, under a row lock on the certificate, so
     * calling this any number of times (page refresh, double click, two
     * browser tabs) always returns the same invoice and never creates or
     * adds a second charge.
     *
     * The price is read from the "Medical Certificate" service (Admin >
     * Services) and copied onto the invoice item, so a later fee change
     * never alters an existing bill.
     */
    public function ensureInvoiceForMedicalCertificate(MedicalCertificate $certificate, ?User $creator = null): Invoice
    {
        return DB::transaction(function () use ($certificate, $creator) {

            // Lock the certificate row so two simultaneous calls can't
            // bill the same certificate twice.
            $locked = MedicalCertificate::whereKey($certificate->id)
                ->lockForUpdate()
                ->first();

            if ($locked && $locked->invoice_id && ($existing = Invoice::find($locked->invoice_id))) {
                $certificate->setAttribute('invoice_id', $existing->id);
                $certificate->syncOriginalAttribute('invoice_id');

                return $existing;
            }

            $certificate->loadMissing('appointment');
            $appointment = $certificate->appointment;

            $service = Service::firstOrCreate(
                ['name' => Service::MEDICAL_CERTIFICATE],
                [
                    'description' => 'Issuance of a medical certificate.',
                    'price'       => 100.00,
                    'is_active'   => true,
                ]
            );

            $fee = round((float) $service->price, 2);

            // Can the fee join the consultation invoice of this visit?
            $target = null;
            if ($appointment) {
                $candidate = Invoice::where('appointment_id', $appointment->id)
                    ->lockForUpdate()
                    ->first();

                if ($candidate && $this->canReceiveCertificateFee($candidate)) {
                    $target = $candidate;
                }
            }

            if ($target) {
                // ---- Scenario A: add a line to the unpaid consultation invoice ----
                $invoice = $target;
            } else {
                // ---- Scenario B: the certificate gets its own invoice ----
                // Registered patient -> users table; walk-in -> patients table.
                $patientId = $certificate->patient_id ?? $appointment?->patient_id;
                $walkinId  = $patientId ? null : $appointment?->walkin_patient_id;

                $invoice = Invoice::create([
                    'appointment_id'    => null,
                    'patient_id'        => $patientId,
                    'walkin_patient_id' => $walkinId,
                    'subtotal'          => 0,
                    'discount'          => 0,
                    'total_amount'      => 0,
                    'amount_paid'       => 0,
                    'balance'           => 0,
                    'status'            => 'Unpaid',
                    'created_by'        => $creator?->id,
                ]);
            }

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => Service::MEDICAL_CERTIFICATE,
                'item_type'   => 'medical_certificate',
                'service_id'  => $service->id,
                'quantity'    => 1,
                'unit_price'  => $fee,
                'amount'      => $fee,
            ]);

            $this->refreshInvoiceTotals($invoice);

            MedicalCertificate::whereKey($certificate->id)->update(['invoice_id' => $invoice->id]);

            $certificate->setAttribute('invoice_id', $invoice->id);
            $certificate->syncOriginalAttribute('invoice_id');

            return $invoice;
        });
    }

    /**
     * An existing invoice may only receive the certificate fee while it is
     * completely untouched by billing: Unpaid, no payment rows, not
     * Cancelled. Anything else counts as finalized and must not change.
     */
    protected function canReceiveCertificateFee(Invoice $invoice): bool
    {
        // Same rule as before (Unpaid, no payment rows, not Cancelled); a
        // fully waived zero-total invoice is stored as 'Paid' with no
        // payment rows and is just as untouched.
        return $invoice->isOpenForCharges();
    }

    /**
     * Recompute an UNPAID invoice from its line items (item discounts are
     * preserved). Only ever called on invoices with no payment against them.
     */
    protected function refreshInvoiceTotals(Invoice $invoice): void
    {
        $this->recalculateInvoiceTotals($invoice);
    }

    /**
     * Undo the certificate's charge when the certificate is rejected — only
     * if nothing has been paid on the invoice yet (a bill with money against
     * it is left for Staff/Admin to handle through Billing).
     *
     *  - separate invoice            -> the invoice is Cancelled
     *  - combined with consultation  -> only the certificate line is
     *    removed; the consultation charge stays
     */
    public function cancelInvoiceForMedicalCertificate(MedicalCertificate $certificate): void
    {
        $invoice = $certificate->invoice;

        if (!$invoice || !$this->canReceiveCertificateFee($invoice)) {
            return;
        }

        DB::transaction(function () use ($invoice, $certificate) {
            $hasOtherItems = $invoice->items()
                ->where('item_type', '!=', 'medical_certificate')
                ->exists();

            if ($hasOtherItems) {
                $invoice->items()->where('item_type', 'medical_certificate')->delete();
                $this->refreshInvoiceTotals($invoice);

                MedicalCertificate::whereKey($certificate->id)->update(['invoice_id' => null]);
                $certificate->setAttribute('invoice_id', null);
                $certificate->syncOriginalAttribute('invoice_id');

                return;
            }

            $invoice->status  = 'Cancelled';
            $invoice->balance = 0;
            $invoice->save();
        });
    }

    /**
     * Record a verified payment against an invoice.
     *
     * Rules enforced here (never trust the client for these):
     *  - amount must be > 0
     *  - amount cannot exceed the current remaining balance (no overpayment)
     *  - GCash requires a reference number, Cash does not
     *  - a Cancelled or already fully Paid invoice cannot take a new payment
     *
     * @throws ValidationException
     */
    public function recordPayment(Invoice $invoice, array $data, User $staff): Payment
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $method = $data['payment_method'] ?? null;
        $ref    = trim((string) ($data['reference_number'] ?? ''));

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if (!in_array($method, ['Cash', 'GCash'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Select a valid payment method.',
            ]);
        }

        if ($method === 'GCash' && $ref === '') {
            throw ValidationException::withMessages([
                'reference_number' => 'GCash reference number is required before confirming a GCash payment.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $ref, $data, $staff) {

            // Lock the invoice row while processing this payment.
            $lockedInvoice = Invoice::whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Pay-after-service: nothing can be collected before the
            // consultation is Completed.
            if (!$lockedInvoice->isCollectible()) {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice cannot accept payments yet — the consultation has not been completed.',
                ]);
            }

            // Always check the latest invoice status from the database.
            if ($lockedInvoice->status === 'Cancelled') {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice has been cancelled and can no longer accept payments.',
                ]);
            }

            if ($lockedInvoice->status === 'Paid') {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice is already fully paid.',
                ]);
            }

            // Never take a first payment against stale totals: re-derive the
            // invoice from its items (including discounts) under the lock.
            // Once a payment exists the invoice is finalized and untouched.
            if (!$lockedInvoice->payments()->exists()) {
                $this->recalculateInvoiceTotals($lockedInvoice);
            }

            // Get the latest balance after locking the invoice.
            $currentBalance = round((float) $lockedInvoice->balance, 2);

            if ($amount > $currentBalance + 0.004) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed the remaining balance of ₱'
                        . number_format($currentBalance, 2) . '.',
                ]);
            }

            $payment = Payment::create([
                'invoice_id'       => $lockedInvoice->id,
                'amount'           => $amount,
                'payment_method'   => $method,
                'reference_number' => $method === 'GCash' ? $ref : null,
                'received_by'      => $staff->id,
                'paid_at'          => now(),
                'notes'             => $data['notes'] ?? null,
            ]);

            // Recalculate the invoice after recording the payment.
            $lockedInvoice->recalculate();

            return $payment;
        });
    }
}
