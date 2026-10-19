<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method',
        'reference_number',
        'reference_key',
        'submission_token',
        'received_by',
        'received_by_name',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            // Keep the cashier's name on the payment itself: the account may
            // be deleted later, the receipt must still say who received it.
            if (empty($payment->received_by_name) && $payment->received_by) {
                $u = User::find($payment->received_by);
                $payment->received_by_name = $u ? trim($u->first_name . ' ' . $u->last_name) : null;
            }

            // Normalized GCash reference (UNIQUE in the database). Cash
            // payments have no reference, so their key stays NULL.
            if ($payment->payment_method === 'GCash' && filled($payment->reference_number)) {
                $payment->reference_key = static::normalizeReference($payment->reference_number);
            }
        });

        // A recorded payment is financial history: it is never edited or
        // deleted through the application (corrections are new records).
        static::updating(function (Payment $payment) {
            $locked = ['invoice_id', 'amount', 'payment_method', 'reference_number', 'reference_key', 'paid_at'];
            if ($payment->isDirty($locked)) {
                throw new \LogicException('A recorded payment cannot be changed.');
            }
        });

        static::deleting(function () {
            throw new \LogicException('A recorded payment cannot be deleted.');
        });
    }

    public static function normalizeReference(?string $reference): string
    {
        return mb_strtolower(trim((string) $reference));
    }

    /** Name to print: the live account if it still exists, else the saved copy. */
    public function receivedByName(): string
    {
        if ($this->receivedBy) {
            return trim($this->receivedBy->first_name . ' ' . $this->receivedBy->last_name);
        }

        return $this->received_by_name ?: '—';
    }

    /**
     * What the invoice looked like right after THIS payment: everything paid
     * up to and including it. Receipts use this so an old receipt never
     * changes when a later payment is recorded on the same invoice.
     *
     * @return array{paid_total: float, balance: float}
     */
    public function snapshotAfter(): array
    {
        $paidCents = Money::cents(
            static::where('invoice_id', $this->invoice_id)
                ->where(function ($q) {
                    $q->where('paid_at', '<', $this->paid_at)
                      ->orWhere(fn ($q2) => $q2->where('paid_at', $this->paid_at)->where('id', '<=', $this->id));
                })
                ->sum('amount')
        );

        $totalCents = Money::cents($this->invoice?->total_amount);

        return [
            'paid_total' => Money::pesos($paidCents),
            'balance'    => Money::pesos(max($totalCents - $paidCents, 0)),
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
