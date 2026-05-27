<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';

    protected $fillable = [
        'user_id',

        // Personal Information
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        // 'age' — NOT included; computed via accessor
        'gender',
        'civil_status',
        'contact_number',
        'address',

        // Medical Information
        'blood_type',
        'allergies',

        // Emergency Contact
        'emergency_name',
        'emergency_contact',
        'relationship',
        'emergency_address',

        // Meta
        'is_walk_in',
    ];

    protected $casts = [
        'birthdate'  => 'date',
        'is_walk_in' => 'boolean',
    ];

    // =====================
    // ACCESSORS
    // =====================

    /**
     * Auto-compute age from birthdate.
     * Usage: $patient->age
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birthdate) return null;
        return Carbon::parse($this->birthdate)->age;
    }

    /**
     * Full name helper.
     * Usage: $patient->full_name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // =====================
    // RELATIONSHIPS
    // =====================

    /**
     * Registered patient linked to a User record.
     * Usage: $patient->user
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}