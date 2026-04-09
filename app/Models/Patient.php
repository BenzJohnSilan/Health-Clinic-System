<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Patient extends Model
{
    use HasFactory;

    protected $table = 'patients';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'age',
        'gender',
        'civil_status',
        'contact_number',
        'address',
        'is_walk_in',
    ];

    // Optional (bonus)
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}