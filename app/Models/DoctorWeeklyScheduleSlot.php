<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorWeeklyScheduleSlot extends Model
{
    protected $fillable = [
        'doctor_weekly_schedule_id',
        'start_time',
        'end_time',
    ];

    public function weeklySchedule()
    {
        return $this->belongsTo(DoctorWeeklySchedule::class, 'doctor_weekly_schedule_id');
    }
}
