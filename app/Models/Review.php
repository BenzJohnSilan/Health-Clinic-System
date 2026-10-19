<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Appointment;

class Review extends Model
{
    use HasFactory;

    protected $table = 'reviews';

    protected $fillable = [
        'appointment_id',
        'follow_up_appointment_id',
        'next_review_date',
        'message',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /** The Follow-up appointment created from this review's schedule. */
    public function followUpAppointment()
    {
        return $this->belongsTo(Appointment::class, 'follow_up_appointment_id');
    }
}