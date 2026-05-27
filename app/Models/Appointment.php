<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Review;
use Carbon\Carbon;

class Appointment extends Model
{
    use HasFactory;

    protected $table = 'appointments';

    protected $fillable = [
        'patient_id',
        'walkin_patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'reason',
        'rescheduled_by',
        'diagnosis',
        'reference_no',
        'booked_by_staff',
    ];

    protected $casts = [
        'appointment_date' => 'date:Y-m-d',
        'appointment_time' => 'string',
        'booked_by_staff'  => 'boolean',
    ];

    // ====================
    // AUTO-GENERATE REFERENCE NO
    // ====================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($appointment) {
            if (empty($appointment->reference_no)) {
                $appointment->reference_no = self::generateReferenceNo();
            }
        });
    }

    /**
     * Generate unique reference number
     * Format: APT-YYYYMMDD-XXXXX (e.g. APT-20260521-00042)
     */
    private static function generateReferenceNo(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'APT-' . $date . '-';

        $latest = self::where('reference_no', 'like', $prefix . '%')
            ->orderByDesc('reference_no')
            ->value('reference_no');

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

    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class, 'appointment_id');
    }

    // ====================
    // HELPERS
    // ====================

    /**
     * Returns whichever patient record exists (registered or walk-in).
     * Usage: $appointment->resolvedPatient()
     */
    public function resolvedPatient()
    {
        return $this->patient ?? $this->walkinPatient;
    }

    /**
     * Returns the full display name of the patient.
     * Usage: $appointment->patientName()
     */
    public function patientName(): string
    {
        $p = $this->resolvedPatient();

        if (!$p) {
            return 'Unknown Patient';
        }

        $name = trim(
            $p->first_name . ' ' .
            ($p->middle_name ? $p->middle_name . ' ' : '') .
            $p->last_name
        );

        if (!empty($p->suffix)) {
            $name .= ', ' . $p->suffix;
        }

        return $name;
    }

    /**
     * Returns patient email (registered patients only, walk-in = null).
     * Usage: $appointment->patientEmail()
     */
    public function patientEmail(): ?string
    {
        return $this->patient?->email ?? null;
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