<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            // CONDITION (check if already exists)
            ['email' => 'admin@example.com'],

            // VALUES (if not exists, create this)
            [
                'first_name'        => 'Admin',
                'middle_name'       => null,
                'last_name'         => 'Account',
                'suffix'            => null,

                'username'          => 'admin',
                'password'          => Hash::make('password123'),
                'role'              => 'Admin',
                'email_verified_at' => now(),

                'birthdate'         => '1993-03-29',
                'gender'            => 'Male',
                'civil_status'      => 'Single',
                'address'           => 'Admin Street, City',
                'contact_number'    => '09123456789',

                // ACCOUNT CONTROL
                'status'            => 'Active',

                // APPROVAL SYSTEM
                'approval_status'   => 'Approved',
            ]
        );
    }
}