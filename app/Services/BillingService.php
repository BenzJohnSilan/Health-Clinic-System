<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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
     * Create the invoice for an appointment if it doesn't exist yet, or
     * refresh its line items if it does — WITHOUT ever touching payments
     * already recorded against it. Called when the Doctor tags the
     * service(s)/charge for a completed (or completing) consultation.
     *
     * Medicine is only billed when the Doctor explicitly selected that
     * prescription for clinic purchase in $medicineLines — a
     * prescription existing is never enough on its own (Prescription
     * != Purchase). Manual / outside-pharmacy prescriptions (no
     * medicine_id) can never be selected in the first place.
     *
     * @param  array<int,array{service_id:int,quantity:int}>  $serviceLines
     * @param  array<int,array{prescription_id:int,quantity:int}>  $medicineLines
     */
    public function syncInvoiceForAppointment(Appointment $appointment, array $serviceLines, User $doctor, array $medicineLines = []): Invoice
    {
        return DB::transaction(function () use ($appointment, $serviceLines, $doctor, $medicineLines) {

            $invoice = $appointment->invoice()->first();

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
            // payment recorded against it or is Cancelled — Staff/Admin
            // own it from that point on.
            if ($invoice->status === 'Cancelled' || $invoice->amount_paid > 0) {
                return $invoice;
            }

            $invoice->items()->delete();

            $subtotal = 0.0;

            // Selected services
            if (!empty($serviceLines)) {
                $services = Service::whereIn('id', array_column($serviceLines, 'service_id'))
                    ->get()
                    ->keyBy('id');

                foreach ($serviceLines as $line) {
                    $service = $services->get($line['service_id']);
                    if (!$service) {
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
                    ]);

                    $subtotal += $amount;
                }
            }

            // Clinic medicine — ONLY the prescriptions explicitly passed
            // in $medicineLines (i.e. the Doctor checked "patient is
            // buying this") become a charge. A prescription that exists
            // but wasn't selected here stays at ₱0, same as one that's
            // unavailable or the patient declined.
            if (!empty($medicineLines)) {
                $prescriptionIds = array_column($medicineLines, 'prescription_id');

                $selectedPrescriptions = Prescription::whereIn('id', $prescriptionIds)
                    ->where('appointment_id', $appointment->id)
                    ->whereNotNull('medicine_id')
                    ->with('medicine')
                    ->get()
                    ->keyBy('id');

                foreach ($medicineLines as $line) {
                    $prescription = $selectedPrescriptions->get($line['prescription_id']);
                    if (!$prescription || !$prescription->medicine) {
                        continue;
                    }

                    $medicine = $prescription->medicine;
                    $qty      = max((int) ($line['quantity'] ?? $prescription->quantity_prescribed), 1);
                    $qty      = min($qty, (int) $prescription->quantity_prescribed);
                    $amount   = round((float) $medicine->price * $qty, 2);

                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'description' => $medicine->medicine_name,
                        'item_type'   => 'medicine',
                        'medicine_id' => $medicine->id,
                        'quantity'    => $qty,
                        'unit_price'  => $medicine->price,
                        'amount'      => $amount,
                    ]);

                    $subtotal += $amount;
                }
            }

            $discount = (float) $invoice->discount;
            $total    = max($subtotal - $discount, 0);

            $invoice->subtotal     = round($subtotal, 2);
            $invoice->total_amount = round($total, 2);
            $invoice->balance      = round($total - (float) $invoice->amount_paid, 2);
            $invoice->status       = $invoice->amount_paid > 0
                ? ($invoice->balance <= 0.004 ? 'Paid' : 'Partially Paid')
                : 'Unpaid';
            $invoice->save();

            return $invoice;
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
