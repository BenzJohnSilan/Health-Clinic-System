<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    /**
     * Modules a log entry can belong to. Used to drive the Module filter
     * on the Staff Activity Logs page (and available for reuse anywhere
     * else that groups activity by feature area).
     */
    public const MODULES = [
        'Appointments',
        'Patients',
        'Billing & Payments',
        'Medicine Inventory',
        'Medical Records',
        'Services',
        'Account',
    ];

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'details'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logs are scoped to the account that performed the action — never
     * trust a request parameter for this. Always filter through this
     * scope (or an equivalent explicit where('user_id', ...)) rather than
     * pulling all logs and filtering in the view.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}