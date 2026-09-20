<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientNotificationRead extends Model
{
    protected $table = 'patient_notification_reads';

    protected $fillable = [
        'user_id',
        'notification_key',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
