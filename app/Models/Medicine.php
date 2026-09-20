<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Medicine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medicine_name',
        'brand',
        'category',
        'dosage',
        'quantity',
        'unit',
        'price',
        'expiration_date',
        'status',
    ];

    // Auto-cast expiration_date as Carbon instance
    protected $casts = [
        'expiration_date' => 'date',
        'price'           => 'decimal:2',
        'quantity'        => 'integer',
    ];

    // Check if medicine is expired
    public function getIsExpiredAttribute(): bool
    {
        return Carbon::parse($this->expiration_date)->isPast();
    }

    // Optional: Expiring soon (recommended upgrade)
    public function getIsExpiringSoonAttribute(): bool
    {
        return Carbon::parse($this->expiration_date)
            ->isBetween(now(), now()->addDays(30));
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * Full audit trail of Stock In / Stock Out / Adjustment records
     * for this medicine. Ordered newest-first by default since that's
     * how both the Stock History page and Inventory Reports read it.
     */
    public function stockMovements()
    {
        return $this->hasMany(MedicineStockMovement::class)->latest();
    }

    /**
     * All stock lots ever recorded for this medicine (including ones
     * that have been fully used up — quantity 0 — which are kept for
     * history but excluded from activeBatches()).
     */
    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }

    /**
     * Batches that still have stock. This is what "total quantity"
     * and the Medicine Inventory table's Expiration Date column are
     * computed from — never the raw expiration_date column alone.
     */
    public function activeBatches()
    {
        return $this->batches()->where('quantity', '>', 0);
    }

    /**
     * Recompute this medicine's cached quantity, stock status, and
     * expiration_date from its active batches, then save. Call this
     * after any operation that adds/removes/creates a batch (Add
     * Medicine's first batch, Stock In, Stock Out, Adjust Stock) so
     * the cached columns never drift from the batches that back them.
     *
     * medicines.quantity/expiration_date are kept as denormalized
     * cache columns (rather than removed) specifically so that other,
     * unrelated parts of the app that already read them directly —
     * the Admin Dashboard's "expiring soon" widget, Doctor
     * prescription dispensing, Inventory Reports — keep working
     * without needing to be rewritten against the batch tables.
     */
    public function recalculateFromBatches(): void
    {
        $active = $this->activeBatches()->orderBy('expiration_date')->get();
        $totalQty = (int) $active->sum('quantity');

        $this->quantity = $totalQty;
        $this->status = match (true) {
            $totalQty <= 0  => 'Out of Stock',
            $totalQty <= 10 => 'Low Stock',
            default         => 'Available',
        };

        // The soonest-expiring active batch stands in for the old
        // single expiration_date so existing readers of that column
        // still see something meaningful. If there are no active
        // batches left, the last known value is left untouched.
        if ($active->isNotEmpty()) {
            $this->expiration_date = $active->first()->expiration_date;
        }

        $this->save();
    }
}
