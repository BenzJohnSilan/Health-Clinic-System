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
        'next_review_date',
        'message',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}