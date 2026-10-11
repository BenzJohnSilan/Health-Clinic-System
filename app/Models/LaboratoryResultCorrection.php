<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaboratoryResultCorrection extends Model
{
    protected $table = 'laboratory_result_corrections';

    protected $fillable = [
        'laboratory_request_id',
        'requested_by',
        'test_name',
        'reason',
        'status',
        'resolving_result_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ====================
    // RELATIONSHIPS
    // ====================

    public function laboratoryRequest()
    {
        return $this->belongsTo(LaboratoryRequest::class, 'laboratory_request_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvingResult()
    {
        return $this->belongsTo(LaboratoryResult::class, 'resolving_result_id');
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
}
