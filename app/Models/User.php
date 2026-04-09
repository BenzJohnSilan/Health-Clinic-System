<?php

namespace App\Models;

use Carbon\Carbon; // ✅ Import Carbon
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    // ====================
    // Mass Assignable Fields
    // ====================
    protected $fillable = [
        'avatar',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',        // ✅ consistent sa controllers
        'gender',
        'civil_status',
        'address',
        'contact_number',
        'username',
        'email',
        'password',
        'role',
        'status',
        'approval_status',  // ✅ for admin approval workflow
    ];

    // ====================
    // Hidden Fields
    // ====================
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ====================
    // Casts
    // ====================
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birthdate' => 'date', // ✅ consistent
    ];

    // ====================
    // Accessors (Computed Fields)
    // ====================

    /**
     * Automatically compute age from birthdate
     */
    public function getAgeAttribute()
    {
        return $this->birthdate
            ? Carbon::parse($this->birthdate)->age
            : null;
    }

    // ====================
    // Relationships
    // ====================

    /**
     * Get the appointments for the patient.
     */
    public function appointments()
    {
        return $this->hasMany(\App\Models\Appointment::class, 'patient_id');
    }
}