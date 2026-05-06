<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Prescription;
use App\Models\Review;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'rescheduled_by',
        'diagnosis', // 🩺 BAGO
    ];

    protected $casts = [
        'appointment_date' => 'date:Y-m-d',
        'appointment_time' => 'string',
    ];

    // ====================
    // RELATIONSHIPS
    // ====================

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'appointment_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class, 'appointment_id');
    }

    // ====================
    // ACCESSORS
    // ====================

    public function getDateTimeAttribute()
    {
        if (!$this->appointment_date || !$this->appointment_time) {
            return null;
        }

        return Carbon::parse($this->appointment_date . ' ' . $this->appointment_time);
    }

    public function getFormattedTimeAttribute()
    {
        try {
            return Carbon::createFromFormat('H:i:s', $this->appointment_time)
                ->format('h:i A');
        } catch (\Exception $e) {
            return $this->appointment_time;
        }
    }

    // ====================
    // SCOPES
    // ====================

    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc');
    }

    public function scopePast($query)
    {
        return $query->where('appointment_date', '<', now()->toDateString())
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc');
    }
}