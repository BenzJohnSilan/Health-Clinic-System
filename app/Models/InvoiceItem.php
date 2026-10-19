<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    /**
     * discount_type used by a Consultation fee waiver. It is NOT one of the
     * Staff-selectable config('billing.discount_types'): only the Doctor's
     * Fee Waiver sets it, and Staff discount editing never touches it.
     */
    public const WAIVER_TYPE = 'consultation_waiver';

    protected $fillable = [
        'invoice_id',
        'description',
        'item_type',
        'service_id',
        'medicine_id',
        'prescription_id',
        'quantity',
        'unit_price',
        'amount',
        'discount_type',
        'discount_rate',
        'discount_amount',
        'waiver_reason',
        'waiver_notes',
        'waived_by',
        'waived_at',
    ];

    protected $casts = [
        'waived_at'  => 'datetime',
        'quantity'   => 'integer',
        'unit_price' => 'decimal:2',
        'amount'     => 'decimal:2',
        'discount_rate'   => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    // ====================
    // DISCOUNT HELPERS
    // `amount` is the GROSS line amount (quantity x unit_price).
    // ====================

    public function grossAmount(): float
    {
        return round((float) $this->amount, 2);
    }

    public function netAmount(): float
    {
        return round(max($this->grossAmount() - (float) $this->discount_amount, 0), 2);
    }

    /** True when this line is a Consultation fee waiver (not a Staff discount). */
    public function isWaiver(): bool
    {
        return $this->discount_type === self::WAIVER_TYPE;
    }

    /** Human label of the stored waiver reason (e.g. "Doctor/Clinic Courtesy"). */
    public function waiverReasonLabel(): ?string
    {
        if (!$this->waiver_reason) {
            return null;
        }

        return config('billing.waiver_reasons.' . $this->waiver_reason . '.label', $this->waiver_reason);
    }

    public function discountLabel(): ?string
    {
        if (!$this->discount_type) {
            return null;
        }

        if ($this->isWaiver()) {
            return 'Consultation Waiver' . ($this->waiver_reason ? ' — ' . $this->waiverReasonLabel() : '');
        }

        return config(
            'billing.discount_types.' . $this->discount_type . '.label',
            \Illuminate\Support\Str::headline($this->discount_type)   // old rows that used a type that was later removed
        );
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    /** The dispensed clinic prescription this MEDICINE line bills (if any). */
    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    /** Doctor who applied the waiver. */
    public function waivedBy()
    {
        return $this->belongsTo(User::class, 'waived_by');
    }
}