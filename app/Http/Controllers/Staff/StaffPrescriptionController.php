<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\MedicineBatch;
use App\Models\MedicineStockMovement;
use App\Models\UserLog;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffPrescriptionController extends Controller
{
    public function __construct(private BillingService $billing)
    {
    }

    /**
     * The patient is BUYING a prescribed clinic medicine: Staff releases the
     * quantity actually sold. This is the ONLY place inventory is deducted
     * for a prescription AND the only place clinic medicine reaches an
     * invoice — never at Doctor prescription time (Prescription != Purchase).
     *
     * One action, one DB transaction, one quantity ($request->dispensed_quantity):
     *
     *     stock deducted  =  quantity dispensed  =  quantity billed
     *
     * (quantity_prescribed is only the ceiling; if 10 are prescribed and the
     * patient buys 6, 6 are deducted and 6 are billed.)
     *
     * Safety:
     *  - the prescription row is locked first and its status re-checked
     *    inside the lock, so a double click / two tabs / two Staff can never
     *    deduct stock or create an invoice line twice;
     *  - invoice_items.prescription_id is UNIQUE as a second line of defence;
     *  - a clinic-inventory prescription only: an "Other (Manual Input)"
     *    prescription is for an outside pharmacy and is refused here;
     *  - the quantity must be 1..quantity_prescribed and available in
     *    active, non-expired batches — otherwise nothing at all is changed.
     *
     * Deduction is FIFO across active, non-expired batches with one
     * MedicineStockMovement row per batch touched, so the audit trail always
     * adds up. Route middleware: permission:dispense_prescription.
     */
    public function dispense(Request $request, Prescription $prescription)
    {
        $request->validate([
            'dispensed_quantity' => 'required|integer|min:1',
        ]);

        $quantity = (int) $request->dispensed_quantity;

        try {
            $outcome = DB::transaction(function () use ($quantity, $prescription) {

                // Lock the prescription itself FIRST; every check below
                // reads the locked, up-to-date row.
                $locked = Prescription::whereKey($prescription->id)->lockForUpdate()->firstOrFail();
                $locked->load('appointment.patient', 'appointment.walkinPatient');
                $appointment = $locked->appointment;

                // ── Guards ───────────────────────────────────────────
                if (!$appointment || $appointment->status !== 'Completed') {
                    return ['error' => 'Medicine can only be dispensed after the consultation is Completed.'];
                }

                // "Other (Manual Input)": outside-pharmacy prescription —
                // not stocked, not sold, not billed and never dispensed here.
                if ($locked->isOutsidePharmacy()) {
                    return ['error' => 'This is an outside-pharmacy prescription. The clinic does not dispense or bill it.'];
                }

                if ($locked->dispense_status === 'Dispensed') {
                    return ['error' => 'This prescription has already been dispensed.'];
                }

                if ($quantity > (int) $locked->quantity_prescribed) {
                    return ['error' => 'Dispensed quantity cannot exceed the quantity prescribed.'];
                }

                // ── Stock check (locked) ─────────────────────────────
                $medicine = $locked->medicine()->lockForUpdate()->firstOrFail();

                $batches = MedicineBatch::where('medicine_id', $medicine->id)
                    ->where('quantity', '>', 0)
                    ->where('expiration_date', '>=', now()->toDateString())
                    ->orderBy('expiration_date')
                    ->lockForUpdate()
                    ->get();

                $available = (int) $batches->sum('quantity');

                if ($available < $quantity) {
                    throw ValidationException::withMessages([
                        'dispensed_quantity' => "Not enough stock available to dispense this quantity. Only {$available} unit(s) left in active, non-expired batches.",
                    ]);
                }

                // ── Bill the quantity actually released (once) ───────
                $item    = $this->billing->addDispensedMedicineToInvoice($locked, $quantity, auth()->user());
                $invoice = $item->invoice;

                // ── FIFO stock deduction, same quantity ──────────────
                $needed = $quantity;

                foreach ($batches as $batch) {
                    if ($needed <= 0) {
                        break;
                    }

                    $take = min($needed, $batch->quantity);

                    $before = $medicine->quantity;
                    $batch->decrement('quantity', $take);
                    $medicine->recalculateFromBatches();
                    $after = $medicine->quantity;

                    MedicineStockMovement::create([
                        'medicine_id'     => $medicine->id,
                        'batch_id'        => $batch->id,
                        'user_id'         => auth()->id(),
                        'movement_type'   => MedicineStockMovement::TYPE_DISPENSE,
                        'quantity_change' => -$take,
                        'quantity_before' => $before,
                        'quantity_after'  => $after,
                        'reason'          => 'Dispensed for Prescription #' . $locked->id . ' (Appointment ' . $appointment->reference_no . ')',
                        'reference_no'    => $appointment->reference_no,
                        'batch_no'        => $batch->batch_no,
                        'notes'           => 'Billed on invoice ' . $invoice->invoice_no,
                    ]);

                    $needed -= $take;
                }

                $locked->update([
                    'dispense_status'    => 'Dispensed',
                    'dispensed_quantity' => $quantity,
                    'dispensed_by'       => auth()->id(),
                    'dispensed_at'       => now(),
                ]);

                UserLog::create([
                    'user_id' => auth()->id(),
                    'action'  => 'Dispensed Medicine',
                    'module'  => 'Medicine Inventory',
                    'details' => 'Dispensed ' . $quantity . ' of ' . $locked->quantity_prescribed . ' prescribed unit(s) of '
                               . $medicine->medicine_name . ' for appointment ' . $appointment->reference_no
                               . ' (' . $appointment->patientName() . '); billed on invoice ' . $invoice->invoice_no
                               . ' (₱' . number_format((float) $item->amount, 2) . ').',
                ]);

                return ['success' => true];
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if (isset($outcome['error'])) {
            return back()->with('error', $outcome['error']);
        }

        return back()->with('success', 'Medicine dispensed, inventory updated and the quantity released was added to the invoice.');
    }
}
