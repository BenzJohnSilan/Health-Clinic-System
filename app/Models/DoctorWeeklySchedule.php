<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorWeeklySchedule extends Model
{
    protected $fillable = [
        'doctor_id',
        'day_of_week',
        'status',
    ];

    /**
     * Canonical order of days used for displaying the weekly table.
     */
    public const DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function slots()
    {
        return $this->hasMany(DoctorWeeklyScheduleSlot::class)->orderBy('start_time');
    }
}
