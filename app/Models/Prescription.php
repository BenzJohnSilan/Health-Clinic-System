<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Medicine;
use App\Models\Appointment;

class Prescription extends Model
{
    protected $fillable = [
        'appointment_id',
        'medicine_id',
        'manual_medicine_name',
        'dosage',
        'frequency',
        'duration',
        'quantity_prescribed',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}