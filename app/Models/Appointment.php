<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory;

    // Table name (optional, Laravel defaults to 'appointments')
    protected $table = 'appointments';

    // Fillable fields for mass assignment
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',   // changed to match DB column
        'appointment_time',   // changed to match DB column
        'status',             // e.g., 'Pending', 'Approved', 'Rejected'
        'reason',             // optional reason or notes
    ];

    // Cast fields to proper types
    protected $casts = [
        'appointment_date' => 'date',   // casts to Carbon date object
        'appointment_time' => 'string', // store time as string HH:MM:SS
    ];

    // ====================
    // Relationships
    // ====================

    /**
     * Get the patient that owns this appointment.
     */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Get the doctor associated with this appointment.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    // ====================
    // Accessors / Helpers
    // ====================

    /**
     * Get full appointment datetime as Carbon instance.
     */
    public function getDateTimeAttribute()
    {
        return Carbon::parse($this->appointment_date->format('Y-m-d') . ' ' . $this->appointment_time);
    }

    // ====================
    // Query Scopes
    // ====================

    /**
     * Upcoming appointments (today or later)
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
                     ->orderBy('appointment_date', 'asc')
                     ->orderBy('appointment_time', 'asc');
    }

    /**
     * Past appointments (before today)
     */
    public function scopePast($query)
    {
        return $query->where('appointment_date', '<', now()->toDateString())
                     ->orderBy('appointment_date', 'desc')
                     ->orderBy('appointment_time', 'desc');
    }
}
