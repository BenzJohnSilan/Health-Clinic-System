<?php

namespace App\Models;

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
        'subtotal',
        'discount',
        'total_amount',
        'amount_paid',
        'balance',
        'status',
        'created_by',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'discount'     => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid'  => 'decimal:2',
        'balance'      => 'decimal:2',
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
            return 'Unknown Patient';
        }

        return trim($p->first_name . ' ' . $p->last_name);
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

        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->total_amount;
        $balance = max($total - $paid, 0);

        $status = 'Unpaid';
        if ($paid > 0 && $balance > 0.004) {
            $status = 'Partially Paid';
        } elseif ($balance <= 0.004 && $total > 0) {
            $status = 'Paid';
        }

        $this->amount_paid = round($paid, 2);
        $this->balance     = round($balance, 2);
        $this->status      = $status;
        $this->save();
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
