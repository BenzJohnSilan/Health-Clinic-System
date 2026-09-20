<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCertificateCorrection extends Model
{
    protected $table = 'medical_certificate_corrections';

    protected $fillable = [
        'medical_certificate_id',
        'patient_id',

        'reason',
        'status',
        'rejection_reason',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ====================
    // RELATIONSHIPS
    // ====================

    public function certificate()
    {
        return $this->belongsTo(MedicalCertificate::class, 'medical_certificate_id');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    // ====================
    // HELPERS
    // ====================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
