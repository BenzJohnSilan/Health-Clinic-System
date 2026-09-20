<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineStockMovement;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared Medicine Inventory module.
 *
 * Used by the Admin panel (admin.medicines.*), the Staff panel
 * (staff.medicines.*), and the Doctor panel (doctor.medicines.*) so
 * there is exactly one CRUD implementation and one set of medicine
 * records — never separate per-role data or views.
 *
 * Access is enforced by the route-level `permission:` middleware
 * (see routes/web.php). Admin bypasses every permission check via
 * User::hasPermission(); Staff and Doctor are each checked against
 * whatever the Admin granted them individually in Manage Permissions.
 *
 * BATCH INVENTORY
 * ---------------
 * A Medicine is the product record. Its actual stock lives in
 * MedicineBatch rows (one per batch/lot number), each with its own
 * quantity and expiration date — different deliveries of the same
 * product can expire on different dates and are never merged into
 * one number. medicines.quantity/expiration_date are kept as cached
 * totals (see Medicine::recalculateFromBatches()) for backward
 * compatibility with the rest of the app.
 *
 * Stock quantity is NEVER changed directly through store()/update().
 * Every quantity change (initial stock, Stock In, Stock Out, Adjust
 * Stock) is routed through a dedicated method that runs inside a
 * DB::transaction() with the relevant medicine AND batch rows locked
 * (lockForUpdate()), so two concurrent requests can never corrupt the
 * final quantity, and every change leaves a MedicineStockMovement
 * audit record linked to the batch it affected.
 */
class MedicineController extends Controller
{
    // ===============================
    // Compute stock status
    // ===============================
    private function computeStatus(int $quantity): string
    {
        return match (true) {
            $quantity <= 0  => 'Out of Stock',
            $quantity <= 10 => 'Low Stock',
            default         => 'Available',
        };
    }

    /**
     * The route name prefix ("admin", "staff", or "doctor") for the
     * currently authenticated user, so one controller/view pair can
     * redirect and build URLs correctly for whichever panel is in use.
     */
    private function routePrefix(): string
    {
        return match (auth()->user()->role) {
            'Admin'  => 'admin',
            'Doctor' => 'doctor',
            default  => 'staff',
        };
    }

    /**
     * Shared validation rules for the movement modals (Stock In,
     * Stock Out). Adjust Stock has its own rules since it validates
     * a batch + increase/decrease delta instead.
     */
    private function movementRules(): array
    {
        return [
            'quantity'     => 'required|integer|min:1',
            'reason'       => 'required|string|max:255',
            'reference_no' => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:1000',
        ];
    }

    private function logAction(string $action, string $details): void
    {
        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => $action,
            'module'  => 'Medicine Inventory',
            'details' => $details,
        ]);
    }

    /**
     * Build a batch/lot identifier when the user leaves the field
     * blank. Real manufacturer/supplier lot numbers are always used
     * when provided — this is only a fallback so a batch can still be
     * tracked without one.
     */
    private function generateBatchNo(Medicine $medicine): string
    {
        return 'AUTO-' . $medicine->id . '-' . strtoupper(Str::random(5));
    }

    // ===============================
    // Show all medicines
    // ===============================
    public function index()
    {
        $user = auth()->user();

        $medicines = Medicine::with(['batches' => function ($q) {
                $q->orderBy('expiration_date');
            }])
            ->orderBy('medicine_name')
            ->get();

        return view('medicine.index', [
            'medicines'   => $medicines,
            'routePrefix' => $this->routePrefix(),
            'canAdd'              => $user->hasPermission('add_medicine'),
            'canEdit'             => $user->hasPermission('edit_medicine'),
            'canDelete'           => $user->hasPermission('delete_medicine'),
            'canStockIn'          => $user->hasPermission('stock_in'),
            'canStockOut'         => $user->hasPermission('stock_out'),
            'canAdjustStock'      => $user->hasPermission('adjust_stock'),
            'canViewStockHistory' => $user->hasPermission('view_stock_history'),
            'canViewReports'      => $user->hasPermission('view_inventory_reports'),
        ]);
    }

    // ===============================
    // View medicine details (read-only, used by the View modal)
    // ===============================
    public function show(Medicine $medicine)
    {
        $activeBatches = $medicine->activeBatches()->orderBy('expiration_date')->get();

        return response()->json([
            'id'              => $medicine->id,
            'medicine_name'   => $medicine->medicine_name,
            'brand'           => $medicine->brand,
            'category'        => $medicine->category,
            'dosage'          => $medicine->dosage,
            'quantity'        => $medicine->quantity,
            'unit'            => $medicine->unit,
            'price'           => number_format((float) $medicine->price, 2),
            'expiration_date' => optional($medicine->expiration_date)->format('M d, Y'),
            'batch_count'     => $activeBatches->count(),
            'status'          => $medicine->is_expired ? 'Expired' : $medicine->status,
        ]);
    }

    // ===============================
    // View a medicine's batches (used by the "N Batches ›" link and
    // the "View Batches" kebab item)
    // ===============================
    public function batches(Medicine $medicine)
    {
        $batches = $medicine->batches()->orderBy('expiration_date')->get();

        return response()->json([
            'medicine_name' => $medicine->medicine_name,
            'batches'       => $batches->map(fn (MedicineBatch $b) => [
                'id'              => $b->id,
                'batch_no'        => $b->batch_no,
                'quantity'        => $b->quantity,
                'expiration_date'    => optional($b->expiration_date)->format('M d, Y'),
                'expiration_date_iso' => optional($b->expiration_date)->format('Y-m-d'),
                'batch_status'       => $b->batch_status,
                'is_active'       => $b->is_active,
            ]),
        ]);
    }

    // ===============================
    // Update a batch's expiration date
    // ===============================
    public function updateBatch(Request $request, Medicine $medicine, MedicineBatch $batch)
    {
        // Route model binding alone is not enough because a valid batch
        // ID could belong to a different medicine. Always verify the
        // parent-child relationship before allowing the update.
        if ((int) $batch->medicine_id !== (int) $medicine->id) {
            abort(404);
        }

        $validated = $request->validate([
            'expiration_date' => 'required|date',
        ]);

        $oldExpiration = optional($batch->expiration_date)->format('Y-m-d');
        $newExpiration = $validated['expiration_date'];

        if ($oldExpiration === $newExpiration) {
            return redirect()
                ->route($this->routePrefix() . '.medicines.index')
                ->with('success', 'Batch expiration date is already set to that date.');
        }

        DB::transaction(function () use ($medicine, $batch, $newExpiration, $oldExpiration) {
            // Lock both rows so the cached medicine expiration cannot be
            // recalculated from a stale batch value during a concurrent
            // inventory operation.
            $lockedMedicine = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();
            $lockedBatch = MedicineBatch::whereKey($batch->id)
                ->where('medicine_id', $lockedMedicine->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBatch->update([
                'expiration_date' => $newExpiration,
            ]);

            // Keep medicines.quantity/status/expiration_date synchronized
            // with its active batches. If this is the earliest active batch,
            // the medicine-level cached expiration will automatically change.
            $lockedMedicine->recalculateFromBatches();

            $this->logAction(
                'Updated Batch Expiration',
                "{$lockedMedicine->medicine_name}: Batch {$lockedBatch->batch_no} expiration changed from {$oldExpiration} to {$newExpiration}"
            );
        });

        return redirect()
            ->route($this->routePrefix() . '.medicines.index')
            ->with('success', 'Batch expiration date updated successfully!');
    }

    // ===============================
    // Store new medicine (creates the product AND its first batch)
    // ===============================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'medicine_name'   => 'required|string|max:255',
            'brand'           => 'required|string|max:255',
            'category'        => 'required|string|max:100',
            'dosage'          => 'required|string|max:100',
            'quantity'        => 'required|integer|min:0',
            'unit'            => 'required|string|max:50',
            'price'           => 'required|numeric|min:0',
            'batch_no'        => 'nullable|string|max:100',
            'expiration_date' => 'required|date',
        ]);

        // Same product identity = name + brand + dosage. A different
        // expiration date or batch is NOT a different product — it's
        // additional stock, which belongs in Stock In instead.
        $duplicate = Medicine::whereRaw('LOWER(medicine_name) = ?', [mb_strtolower(trim($validated['medicine_name']))])
            ->whereRaw('LOWER(brand) = ?', [mb_strtolower(trim($validated['brand']))])
            ->whereRaw('LOWER(dosage) = ?', [mb_strtolower(trim($validated['dosage']))])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'medicine_name' => 'Medicine already exists. Please use Stock In to add additional stock.',
            ]);
        }

        DB::transaction(function () use ($validated) {
            $initialQuantity = (int) $validated['quantity'];

            $medicine = Medicine::create([
                'medicine_name'   => $validated['medicine_name'],
                'brand'           => $validated['brand'],
                'category'        => $validated['category'],
                'dosage'          => $validated['dosage'],
                'quantity'        => 0,
                'unit'            => $validated['unit'],
                'price'           => $validated['price'],
                'expiration_date' => $validated['expiration_date'],
                'status'          => $this->computeStatus(0),
            ]);

            $batchNo = trim((string) ($validated['batch_no'] ?? '')) ?: $this->generateBatchNo($medicine);

            $batch = MedicineBatch::create([
                'medicine_id'     => $medicine->id,
                'batch_no'        => $batchNo,
                'quantity'        => $initialQuantity,
                'expiration_date' => $validated['expiration_date'],
            ]);

            // Any starting stock is recorded as its own auditable
            // movement instead of being baked silently into the
            // create() call, so the very first row in a medicine's
            // stock history always explains where its quantity came
            // from.
            if ($initialQuantity > 0) {
                MedicineStockMovement::create([
                    'medicine_id'      => $medicine->id,
                    'batch_id'         => $batch->id,
                    'user_id'          => auth()->id(),
                    'movement_type'    => MedicineStockMovement::TYPE_STOCK_IN,
                    'quantity_change'  => $initialQuantity,
                    'quantity_before'  => 0,
                    'quantity_after'   => $initialQuantity,
                    'reason'           => 'Initial stock',
                    'batch_no'         => $batchNo,
                    'notes'            => 'Recorded automatically when the medicine was added to inventory.',
                ]);
            }

            $medicine->recalculateFromBatches();

            $this->logAction('Added Medicine', "{$medicine->medicine_name} (Batch {$batchNo})");
        });

        return redirect()
            ->route($this->routePrefix() . '.medicines.index')
            ->with('success', 'Medicine added successfully!');
    }

    // ===============================
    // Update medicine (descriptive fields only — never quantity or
    // expiration date, since expiration now belongs to each batch)
    // ===============================
    public function update(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'medicine_name'   => 'required|string|max:255',
            'brand'           => 'required|string|max:255',
            'category'        => 'required|string|max:100',
            'dosage'          => 'required|string|max:100',
            'unit'            => 'required|string|max:50',
            'price'           => 'required|numeric|min:0',
        ]);

        // Deliberately excludes quantity/status/expiration_date. Stock
        // levels and batch expirations may only change through
        // stockIn()/stockOut()/adjustStock() so every change is
        // recorded as an auditable movement.
        $medicine->update($validated);

        $this->logAction('Updated Medicine', $medicine->medicine_name);

        return redirect()
            ->route($this->routePrefix() . '.medicines.index')
            ->with('success', 'Medicine updated successfully!');
    }

    // ===============================
    // Stock In — tops up an existing batch, or creates a new one
    // ===============================
    public function stockIn(Request $request, Medicine $medicine)
    {
        $validated = $request->validate(array_merge($this->movementRules(), [
            'batch_no'        => 'required|string|max:100',
            'expiration_date' => 'required|date',
        ]));

        DB::transaction(function () use ($validated, $medicine) {
            // Re-fetch and lock the row for the duration of the
            // transaction so a concurrent Stock In/Out/Adjustment on
            // the same medicine can't read a stale quantity.
            $locked = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();

            $batchNo = trim($validated['batch_no']);
            $change  = (int) $validated['quantity'];
            $before  = $locked->quantity;

            $batch = MedicineBatch::where('medicine_id', $locked->id)
                ->where('batch_no', $batchNo)
                ->lockForUpdate()
                ->first();

            if ($batch) {
                // Existing lot — top it up. The lot's expiration date
                // already belongs to that batch and isn't overwritten
                // by whatever the form happened to submit.
                $batch->increment('quantity', $change);
            } else {
                $batch = MedicineBatch::create([
                    'medicine_id'     => $locked->id,
                    'batch_no'        => $batchNo,
                    'quantity'        => $change,
                    'expiration_date' => $validated['expiration_date'],
                ]);
            }

            $locked->recalculateFromBatches();
            $after = $locked->quantity;

            MedicineStockMovement::create([
                'medicine_id'     => $locked->id,
                'batch_id'        => $batch->id,
                'user_id'         => auth()->id(),
                'movement_type'   => MedicineStockMovement::TYPE_STOCK_IN,
                'quantity_change' => $change,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'reason'          => $validated['reason'],
                'reference_no'    => $validated['reference_no'] ?? null,
                'batch_no'        => $batchNo,
                'notes'           => $validated['notes'] ?? null,
            ]);

            $this->logAction(
                'Stock In',
                "{$locked->medicine_name}: +{$change} on batch {$batchNo} ({$before} \u{2192} {$after})"
            );
        });

        return redirect()
            ->route($this->routePrefix() . '.medicines.index')
            ->with('success', 'Stock added successfully!');
    }

    // ===============================
    // Stock Out — removes stock from one specific, non-expired batch
    // ===============================
    public function stockOut(Request $request, Medicine $medicine)
    {
        $validated = $request->validate(array_merge($this->movementRules(), [
            'batch_id' => 'required|integer|exists:medicine_batches,id',
        ]));

        DB::transaction(function () use ($validated, $medicine) {
            $locked = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();

            $batch = MedicineBatch::where('id', $validated['batch_id'])
                ->where('medicine_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                throw ValidationException::withMessages([
                    'batch_id' => 'That batch no longer exists for this medicine.',
                ]);
            }

            if ($batch->is_expired) {
                throw ValidationException::withMessages([
                    'batch_id' => "Batch {$batch->batch_no} is expired and cannot be used for Stock Out.",
                ]);
            }

            $change = (int) $validated['quantity'];

            // Authoritative check against the *locked* batch, not
            // whatever the browser last saw — stock must never go
            // negative even under concurrent requests.
            if ($change > $batch->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Stock Out quantity cannot exceed the available stock in batch {$batch->batch_no} ({$batch->quantity}).",
                ]);
            }

            $before = $locked->quantity;
            $batch->decrement('quantity', $change);

            $locked->recalculateFromBatches();
            $after = $locked->quantity;

            MedicineStockMovement::create([
                'medicine_id'     => $locked->id,
                'batch_id'        => $batch->id,
                'user_id'         => auth()->id(),
                'movement_type'   => MedicineStockMovement::TYPE_STOCK_OUT,
                'quantity_change' => -$change,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'reason'          => $validated['reason'],
                'reference_no'    => $validated['reference_no'] ?? null,
                'batch_no'        => $batch->batch_no,
                'notes'           => $validated['notes'] ?? null,
            ]);

            $this->logAction(
                'Stock Out',
                "{$locked->medicine_name}: -{$change} from batch {$batch->batch_no} ({$before} \u{2192} {$after})"
            );
        });

        return redirect()
            ->route($this->routePrefix() . '.medicines.index')
            ->with('success', 'Stock removed successfully!');
    }

    // ===============================
    // Adjust Stock (physical count reconciliation) — increases or
    // decreases ONE batch by a delta; never sets an absolute total,
    // since the total no longer identifies which lot changed.
    // ===============================
    public function adjustStock(Request $request, Medicine $medicine)
    {
        $validated = $request->validate([
            'batch_id'         => 'required|integer|exists:medicine_batches,id',
            'adjustment_type'  => 'required|in:increase,decrease',
            'quantity'         => 'required|integer|min:1',
            'reason'           => 'required|string|max:255',
            'reference_no'     => 'nullable|string|max:100',
            'notes'            => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($validated, $medicine) {
            $locked = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();

            $batch = MedicineBatch::where('id', $validated['batch_id'])
                ->where('medicine_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                throw ValidationException::withMessages([
                    'batch_id' => 'That batch no longer exists for this medicine.',
                ]);
            }

            $delta = (int) $validated['quantity'];
            $signedChange = $validated['adjustment_type'] === 'decrease' ? -$delta : $delta;

            if ($signedChange < 0 && abs($signedChange) > $batch->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot decrease batch {$batch->batch_no} by more than its current stock ({$batch->quantity}).",
                ]);
            }

            $before = $locked->quantity;
            $batch->increment('quantity', $signedChange);

            $locked->recalculateFromBatches();
            $after = $locked->quantity;

            MedicineStockMovement::create([
                'medicine_id'     => $locked->id,
                'batch_id'        => $batch->id,
                'user_id'         => auth()->id(),
                'movement_type'   => MedicineStockMovement::TYPE_ADJUSTMENT,
                'quantity_change' => $signedChange,
                'quantity_before' => $before,
                'quantity_after'  => $after,
                'reason'          => $validated['reason'],
                'reference_no'    => $validated['reference_no'] ?? null,
                'batch_no'        => $batch->batch_no,
                'notes'           => $validated['notes'] ?? null,
            ]);

            $sign = $signedChange >= 0 ? '+' . $signedChange : (string) $signedChange;
            $this->logAction(
                'Stock Adjustment',
                "{$locked->medicine_name}: {$sign} on batch {$batch->batch_no} ({$before} \u{2192} {$after})"
            );
        });

        return redirect()
            ->route($this->routePrefix() . '.medicines.index')
            ->with('success', 'Stock adjusted successfully!');
    }

    // ===============================
    // Stock History — dedicated page, scoped to ONE medicine
    // ===============================
    public function stockHistory(Medicine $medicine)
    {
        $movements = $medicine->stockMovements()
            ->with(['user:id,first_name,last_name,role', 'batch:id,batch_no'])
            ->paginate(15);

        return view('medicine.stock-history', [
            'medicine'    => $medicine,
            'movements'   => $movements,
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    // ===============================
    // Inventory Reports — dedicated page
    // ===============================
    public function reports()
    {
        $medicines = Medicine::all();

        $totalMedicines = $medicines->count();
        $available      = $medicines->where('status', 'Available')->count();
        $lowStock       = $medicines->where('status', 'Low Stock')->count();
        $outOfStock     = $medicines->where('status', 'Out of Stock')->count();
        $expired        = $medicines->filter(fn ($m) => $m->is_expired)->count();
        $expiringSoon   = $medicines->filter(fn ($m) => !$m->is_expired && $m->is_expiring_soon)->count();
        $inventoryValue = $medicines->sum(fn ($m) => $m->price * $m->quantity);

        $movementSummary = MedicineStockMovement::selectRaw(
                'movement_type, COUNT(*) as movement_count, SUM(ABS(quantity_change)) as total_quantity'
            )
            ->groupBy('movement_type')
            ->get()
            ->keyBy('movement_type');

        $recentMovements = MedicineStockMovement::with(['medicine:id,medicine_name', 'user:id,first_name,last_name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('medicine.reports', [
            'routePrefix'     => $this->routePrefix(),
            'totalMedicines'  => $totalMedicines,
            'available'       => $available,
            'lowStock'        => $lowStock,
            'outOfStock'      => $outOfStock,
            'expired'         => $expired,
            'expiringSoon'    => $expiringSoon,
            'inventoryValue'  => $inventoryValue,
            'movementSummary' => $movementSummary,
            'recentMovements' => $recentMovements,
        ]);
    }

    // ===============================
    // Delete medicine (soft delete — history is preserved)
    // ===============================
    public function destroy(Medicine $medicine)
    {
        $this->logAction('Deleted Medicine', $medicine->medicine_name);

        // Soft delete only. The medicine_stock_movements and
        // medicine_batches rows for this medicine keep their foreign
        // key intact and remain fully queryable — deleting a medicine
        // must never destroy its audit history.
        $medicine->delete();

        return redirect()
            ->route($this->routePrefix() . '.medicines.index')
            ->with('success', 'Medicine deleted successfully!');
    }
}
