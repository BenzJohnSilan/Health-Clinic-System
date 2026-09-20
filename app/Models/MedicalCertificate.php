<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCertificate extends Model
{
    protected $table = 'medical_certificates';

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'doctor_id',

        'certificate_number',

        'purpose',
        'other_purpose',
        'request_details',

        'medical_statement',
        'recommendation',

        'rest_start_date',
        'rest_end_date',

        'additional_remarks',

        'status',

        'requested_at',
        'issued_at',
        'rejected_at',

        'rejection_reason',
    ];

    protected $casts = [
        'rest_start_date' => 'date',
        'rest_end_date'   => 'date',
        'requested_at'    => 'datetime',
        'issued_at'       => 'datetime',
        'rejected_at'     => 'datetime',
    ];

    // ====================
    // RELATIONSHIPS
    // ====================

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function corrections()
    {
        return $this->hasMany(MedicalCertificateCorrection::class, 'medical_certificate_id');
    }

    /**
     * The correction request currently awaiting doctor review, if any.
     */
    public function pendingCorrection()
    {
        return $this->hasOne(MedicalCertificateCorrection::class, 'medical_certificate_id')
            ->where('status', 'pending')
            ->latestOfMany();
    }

    /**
     * Most recent correction request regardless of status (for history display).
     */
    public function latestCorrection()
    {
        return $this->hasOne(MedicalCertificateCorrection::class, 'medical_certificate_id')
            ->latestOfMany();
    }

    // ====================
    // HELPERS
    // ====================

    public function displayPurpose(): string
    {
        if ($this->purpose === 'Other' && $this->other_purpose) {
            return $this->other_purpose;
        }

        return $this->purpose;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCorrectionRequested(): bool
    {
        return $this->status === 'correction_requested';
    }

    /**
     * True once the certificate has been issued at least once — used to
     * decide whether the "issued content" fields (medical statement,
     * recommendation, etc.) should be shown, even while a correction is
     * being reviewed.
     */
    public function hasIssuedContent(): bool
    {
        return in_array($this->status, ['issued', 'correction_requested'], true)
            || $this->issued_at !== null;
    }

    /**
     * True when the patient is allowed to submit a brand new request for
     * this same appointment (no active request/certificate blocking it).
     */
    public function blocksNewRequest(): bool
    {
        return in_array($this->status, ['pending', 'issued', 'correction_requested'], true);
    }

    /**
     * Returns whichever patient record applies — the registered patient
     * (users table) if there is one, otherwise the walk-in patient
     * (patients table) via the linked appointment. Needed because
     * `patient_id` is null for certificates created directly by a doctor
     * for a walk-in / face-to-face patient.
     */
    public function resolvedPatient()
    {
        return $this->patient ?? $this->appointment?->walkinPatient;
    }

    /**
     * Full display name of whichever patient this certificate belongs to.
     */
    public function resolvedPatientName(): string
    {
        $p = $this->resolvedPatient();

        if (!$p) {
            return 'Unknown Patient';
        }

        return trim($p->first_name . ' ' . $p->last_name);
    }

    /**
     * Generate a unique certificate number.
     * Format: MC-YYYY-XXXX (e.g. MC-2026-0001)
     */
    public static function generateCertificateNumber(): string
    {
        $year   = now()->format('Y');
        $prefix = 'MC-' . $year . '-';

        $latest = self::where('certificate_number', 'like', $prefix . '%')
            ->orderByDesc('certificate_number')
            ->value('certificate_number');

        if ($latest) {
            $lastNumber = (int) substr($latest, strlen($prefix));
            $next       = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
