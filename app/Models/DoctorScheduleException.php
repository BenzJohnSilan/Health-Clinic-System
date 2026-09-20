<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorScheduleException extends Model
{
    protected $fillable = [
        'doctor_id',
        'exception_date',
        'type',
        'start_time',
        'end_time',
        'reason',
    ];

    protected $casts = [
        'exception_date' => 'date',
    ];

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }
}
