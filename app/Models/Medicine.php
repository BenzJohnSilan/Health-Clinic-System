<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Medicine extends Model
{
    protected $fillable = [
        'medicine_name',
        'brand',            // ✅ ADD
        'category',
        'dosage',           // ✅ ADD
        'quantity',
        'unit',
        'price',            // ✅ ADD
        'expiration_date',
        'status',
    ];

    // Auto-cast expiration_date as Carbon instance
    protected $casts = [
        'expiration_date' => 'date',
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
}