<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        // PROFILE
        'avatar',

        // PERSONAL INFO
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'gender',
        'civil_status',
        'address',
        'contact_number',

        // VERIFICATION
        'id_type',
        'valid_id',

        // REASON
        'reason',

        // MEDICAL INFO
        'blood_type',
        'allergies',

        // EMERGENCY CONTACT
        'emergency_name',
        'emergency_contact_number',
        'relationship',
        'emergency_address',

        // LOGIN INFO
        'username',
        'email',
        'password',

        // ACCOUNT CONTROL
        'role',
        'status',
        'approval_status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'birthdate' => 'date',
    ];

    /**
     * Auto compute age
     */
    public function getAgeAttribute()
    {
        return $this->birthdate
            ? Carbon::parse($this->birthdate)->age
            : null;
    }

    /**
     * Patient appointments
     */
    public function appointments()
    {
        return $this->hasMany(\App\Models\Appointment::class, 'patient_id');
    }
}