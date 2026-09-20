<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Medicine;
use App\Models\Appointment;
use App\Models\User;

class Prescription extends Model
{
    protected $fillable = [
        'appointment_id',
        'medicine_id',
        'manual_medicine_name',
        'dosage',
        'frequency',
        'duration',
        'instructions',
        'quantity_prescribed',
        'dispense_status',
        'dispensed_quantity',
        'dispensed_by',
        'dispensed_at',
    ];

    protected $casts = [
        'dispensed_at' => 'datetime',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function dispensedBy()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    /**
     * True once Staff has released this medicine. Manual (non-inventory)
     * prescriptions can also be marked Dispensed for record-keeping —
     * they just never trigger a stock deduction (see dispense logic).
     */
    public function isDispensed(): bool
    {
        return $this->dispense_status === 'Dispensed';
    }
}
