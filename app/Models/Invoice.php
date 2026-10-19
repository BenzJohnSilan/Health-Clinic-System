<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'appointment_id',
        'patient_id',
        'walkin_patient_id',
        'patient_name',
        'appointment_ref',
        'subtotal',
        'discount',
        'total_amount',
        'amount_paid',
        'balance',
        'status',
        'created_by',
        'void_reason',
        'voided_by',
        'voided_at',
        'discount_id_number',
        'discount_id_verified_by',
        'discount_id_verified_at',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'discount'     => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid'  => 'decimal:2',
        'balance'      => 'decimal:2',
        'voided_at'    => 'datetime',
        'discount_id_verified_at' => 'datetime',
    ];

    // ====================
    // AUTO-GENERATE INVOICE NO
    // ====================

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_no)) {
                $invoice->invoice_no = self::generateInvoiceNo();
            }

            // Saved copies, so the invoice still says WHO and WHICH visit it
            // was for after the patient account / appointment is deleted.
            if (empty($invoice->patient_name)) {
                $person = $invoice->patient_id
                    ? User::find($invoice->patient_id)
                    : ($invoice->walkin_patient_id ? Patient::find($invoice->walkin_patient_id) : null);
                $invoice->patient_name = $person ? trim($person->first_name . ' ' . $person->last_name) : null;
            }

            if (empty($invoice->appointment_ref) && $invoice->appointment_id) {
                $invoice->appointment_ref = Appointment::whereKey($invoice->appointment_id)->value('reference_no');
            }
        });

        // An invoice that has payments is financial history. The database
        // also refuses it (payments.invoice_id is RESTRICT); this gives a
        // clear message first.
        static::deleting(function (Invoice $invoice) {
            if ($invoice->payments()->exists()) {
                throw new \LogicException('An invoice with recorded payments cannot be deleted. Keep it for the records.');
            }
        });
    }

    /**
     * Format: INV-YYYYMMDD-XXXXX (e.g. INV-20260904-00001)
     */
    public static function generateInvoiceNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'INV-' . $date . '-';

        $latest = self::where('invoice_no', 'like', $prefix . '%')
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        if ($latest) {
            $lastNumber = (int) substr($latest, strlen($prefix));
            $next       = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // ====================
    // RELATIONSHIPS
    // ====================

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    // Registered patient (users table)
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    // Walk-in patient (patients table)
    public function walkinPatient()
    {
        return $this->belongsTo(Patient::class, 'walkin_patient_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function discountIdVerifiedBy()
    {
        return $this->belongsTo(User::class, 'discount_id_verified_by');
    }

    // ====================
    // HELPERS
    // ====================

    /**
     * Returns whichever patient record exists (registered or walk-in).
     */
    public function resolvedPatient()
    {
        return $this->patient ?? $this->walkinPatient;
    }

    public function patientName(): string
    {
        $p = $this->resolvedPatient();

        if (!$p) {
            // Account deleted: fall back to the name saved on the invoice.
            return $this->patient_name ?: 'Unknown Patient';
        }

        return trim($p->first_name . ' ' . $p->last_name);
    }

    /** Appointment reference to print (live appointment, else the saved copy). */
    public function appointmentRef(): ?string
    {
        return $this->appointment?->reference_no ?? $this->appointment_ref;
    }

    /**
     * The ONE place that turns money figures into an invoice status.
     *
     *  - something paid, nothing left   -> Paid
     *  - something paid, balance left   -> Partially Paid
     *  - nothing paid, total is 0 and the invoice has lines (e.g. a fully
     *    waived consultation) -> Paid (settled; nothing to collect)
     *  - otherwise                      -> Unpaid
     */
    public static function deriveStatus(int $totalCents, int $paidCents, bool $hasItems): string
    {
        $balance = max($totalCents - $paidCents, 0);

        if ($paidCents > 0) {
            return $balance <= 0 ? 'Paid' : 'Partially Paid';
        }

        return ($totalCents <= 0 && $hasItems) ? 'Paid' : 'Unpaid';
    }

    /**
     * Recompute amount_paid / balance / status from the sum of this
     * invoice's payments. This is the single source of truth for the
     * invoice's paid/balance figures so they can never drift from the
     * actual payment history.
     */
    public function recalculate(): void
    {
        if ($this->status === 'Cancelled') {
            return;
        }

        $paidCents  = Money::cents($this->payments()->sum('amount'));
        $totalCents = Money::cents($this->total_amount);

        $this->amount_paid = Money::pesos($paidCents);
        $this->balance     = Money::pesos(max($totalCents - $paidCents, 0));
        $this->status      = self::deriveStatus($totalCents, $paidCents, $this->items()->exists());
        $this->save();
    }

    /**
     * Item discounts may only change while the invoice is still
     * completely untouched by payment: Unpaid, nothing paid, no payment
     * rows. Paid / Partially Paid / Cancelled invoices are finalized.
     */
    public function discountEditable(): bool
    {
        // Open while no payment exists. (A zero-total invoice is stored as
        // 'Paid' with no payment rows; Staff must still be able to undo a
        // 100 % discount they applied.)
        return $this->isOpenForCharges();
    }

    /**
     * Older invoices could carry a discount on the invoice itself with no
     * per-item discount behind it. Recalculation preserves that figure
     * as-is; it is not editable through the per-item workflow.
     */
    /**
     * True while no payment has ever been recorded against this invoice and
     * it is not Cancelled — i.e. it is still safe to add a late charge (a
     * dispensed medicine, a certificate fee) to it. A zero-total invoice
     * (e.g. fully waived consultation) is stored as 'Paid' with no payment
     * rows; it still counts as untouched, because nothing was ever paid.
     */
    public function isOpenForCharges(): bool
    {
        return $this->status !== 'Cancelled'
            && (float) $this->amount_paid <= 0
            && !$this->payments()->exists();
    }

    public function hasLegacyInvoiceDiscount(): bool
    {
        return (float) $this->discount > 0.004
            && !$this->items()->where('discount_amount', '>', 0)->exists();
    }

    /**
     * Pay-after-service: a consultation invoice can only be collected once
     * its consultation is Completed. Invoices with no appointment (a
     * separate Medical Certificate invoice) are always collectible.
     */
    public function isCollectible(): bool
    {
        if (!$this->appointment_id) {
            return true;
        }

        return $this->appointment()->value('status') === 'Completed';
    }

    /**
     * Invoices Staff may see and collect: no appointment (Medical
     * Certificate invoice) or an appointment that is Completed.
     */
    public function scopeCollectible($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('appointment_id')
              ->orWhereIn('appointment_id', function ($sub) {
                  $sub->select('id')->from('appointments')->where('status', 'Completed');
              });
        });
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'Paid'           => 'paid',
            'Partially Paid' => 'partial',
            'Cancelled'      => 'cancelled',
            default          => 'unpaid',
        };
    }
}
