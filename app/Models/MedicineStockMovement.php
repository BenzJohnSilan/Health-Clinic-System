<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single, immutable audit record for a Medicine Inventory stock
 * change (Stock In, Stock Out, Adjustment, or Dispense).
 *
 * IMPORTANT: These records are the Medicine Inventory audit trail.
 * Nothing in the application should ever call update() or delete()
 * on a MedicineStockMovement — there is deliberately no controller
 * action, route, or UI affordance for editing or removing history.
 * Rows are only ever created by MedicineController's
 * stockIn()/stockOut()/adjustStock() methods, or by
 * StaffPrescriptionController::dispense() — always inside a
 * DB::transaction() alongside the corresponding Medicine/batch update.
 */
class MedicineStockMovement extends Model
{
    public const TYPE_STOCK_IN   = 'stock_in';
    public const TYPE_STOCK_OUT  = 'stock_out';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_DISPENSE   = 'dispense';

    protected $fillable = [
        'medicine_id',
        'batch_id',
        'user_id',
        'movement_type',
        'quantity_change',
        'quantity_before',
        'quantity_after',
        'reason',
        'reference_no',
        'batch_no',
        'notes',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after'  => 'integer',
    ];

    /**
     * Human-readable label for the movement type, used by the Stock
     * History table and Inventory Reports summary.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->movement_type) {
            self::TYPE_STOCK_IN   => 'Stock In',
            self::TYPE_STOCK_OUT  => 'Stock Out',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_DISPENSE   => 'Dispense',
            default               => ucfirst($this->movement_type),
        };
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
