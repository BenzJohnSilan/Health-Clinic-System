<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * One physical stock lot of a Medicine — its own batch/lot number,
 * quantity, and expiration date. A Medicine can have many batches;
 * see Medicine::batches().
 */
class MedicineBatch extends Model
{
    protected $fillable = [
        'medicine_id',
        'batch_no',
        'quantity',
        'expiration_date',
    ];

    protected $casts = [
        'quantity'        => 'integer',
        'expiration_date' => 'date',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(MedicineStockMovement::class, 'batch_id');
    }

    /**
     * Whether this batch still has stock to dispense from. A batch
     * with quantity 0 is kept for history but no longer counts toward
     * the medicine's total or shows up as "active".
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->quantity > 0;
    }

    public function getIsExpiredAttribute(): bool
    {
        return Carbon::parse($this->expiration_date)->startOfDay()->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return ! $this->is_expired
            && Carbon::parse($this->expiration_date)->startOfDay()->lessThanOrEqualTo(now()->addDays(30)->startOfDay());
    }

    /**
     * "Valid" | "Expiring Soon" | "Expired" — this is the batch's own
     * expiration status. It is intentionally separate from the
     * Medicine's stock-availability status (Available/Low Stock/Out
     * of Stock); the two are never combined.
     */
    public function getBatchStatusAttribute(): string
    {
        return match (true) {
            $this->is_expired       => 'Expired',
            $this->is_expiring_soon => 'Expiring Soon',
            default                 => 'Valid',
        };
    }
}
