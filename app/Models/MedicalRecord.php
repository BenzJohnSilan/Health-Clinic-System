<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    protected $table = 'medical_records';

    protected $fillable = [
        'patient_id',
        'walkin_patient_id',
        'doctor_id',
        'appointment_id',
        'chief_complaint',
        'diagnosis',
        'treatment',
        'notes',
        'blood_pressure',
        'temperature',
        'weight',
        'height',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function walkInPatient()
    {
        return $this->belongsTo(Patient::class, 'walkin_patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}