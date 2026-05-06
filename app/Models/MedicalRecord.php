<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $table = 'medical_records';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_id',
        'diagnosis',
        'treatment',
        'notes',
    ];

    // Patient relationship
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    // Doctor relationship
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // Appointment relationship
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}