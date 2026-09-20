<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\MedicineBatch;
use App\Models\MedicineStockMovement;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffPrescriptionController extends Controller
{
    /**
     * Staff releases a prescribed medicine to the patient. This is the
     * ONLY place inventory is ever deducted for a prescription — never
     * at Doctor creation time (Section 10 of the workflow spec).
     *
     * Deduction is FIFO across active, non-expired batches (same
     * ordering MedicineController::stockOut() effectively enforces
     * manually), with one MedicineStockMovement row written per batch
     * touched so the audit trail always adds up. Locking + a
     * transaction keep this safe under concurrent dispensing.
     */
    public function dispense(Request $request, Prescription $prescription)
    {
        $request->validate([
            'dispensed_quantity' => 'required|integer|min:1',
        ]);

        $prescription->load('appointment', 'medicine');
        $appointment = $prescription->appointment;

        // ── Guards ───────────────────────────────────────────────────
        if (!$appointment || $appointment->status !== 'Completed') {
            return back()->with('error', 'Medicine can only be dispensed after the consultation is Completed.');
        }

        if ($prescription->dispense_status === 'Dispensed') {
            return back()->with('error', 'This prescription has already been dispensed.');
        }

        if ($request->dispensed_quantity > $prescription->quantity_prescribed) {
            return back()->with('error', 'Dispensed quantity cannot exceed the quantity prescribed.');
        }

        // Manual (non-inventory) prescriptions are simply marked
        // Dispensed for record-keeping — there is no Medicine row to
        // deduct stock from.
        if (!$prescription->medicine_id) {
            $prescription->update([
                'dispense_status'    => 'Dispensed',
                'dispensed_quantity' => $request->dispensed_quantity,
                'dispensed_by'       => auth()->id(),
                'dispensed_at'       => now(),
            ]);

            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Dispensed Medicine (Manual)',
                'module'  => 'Medicine Inventory',
                'details' => 'Marked manual prescription "' . $prescription->manual_medicine_name . '" as dispensed for appointment ' . $appointment->reference_no . '.',
            ]);

            return back()->with('success', 'Prescription marked as dispensed.');
        }

        // ── Inventory-linked prescription: FIFO deduction ───────────────
        try {
            DB::transaction(function () use ($request, $prescription, $appointment) {

                $medicine = $prescription->medicine()
                    ->lockForUpdate()
                    ->firstOrFail();

                $needed = (int) $request->dispensed_quantity;

                $batches = MedicineBatch::where('medicine_id', $medicine->id)
                    ->where('quantity', '>', 0)
                    ->where('expiration_date', '>=', now()->toDateString())
                    ->orderBy('expiration_date')
                    ->lockForUpdate()
                    ->get();

                $available = (int) $batches->sum('quantity');

                if ($available < $needed) {
                    throw ValidationException::withMessages([
                        'dispensed_quantity' => "Not enough stock available to dispense this quantity. Only {$available} unit(s) left in active, non-expired batches.",
                    ]);
                }

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
                        'reason'          => 'Dispensed for Prescription #' . $prescription->id . ' (Appointment ' . $appointment->reference_no . ')',
                        'reference_no'    => $appointment->reference_no,
                        'batch_no'        => $batch->batch_no,
                        'notes'           => null,
                    ]);

                    $needed -= $take;
                }

                $prescription->update([
                    'dispense_status'    => 'Dispensed',
                    'dispensed_quantity' => $request->dispensed_quantity,
                    'dispensed_by'       => auth()->id(),
                    'dispensed_at'       => now(),
                ]);

                UserLog::create([
                    'user_id' => auth()->id(),
                    'action'  => 'Dispensed Medicine',
                    'module'  => 'Medicine Inventory',
                    'details' => 'Dispensed ' . $request->dispensed_quantity . ' unit(s) of '
                               . $medicine->medicine_name . ' for appointment ' . $appointment->reference_no . '.',
                ]);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Medicine dispensed and inventory updated.');
    }
}
