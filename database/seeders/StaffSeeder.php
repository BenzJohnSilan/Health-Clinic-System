<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'staff@example.com'],

            [
                'first_name'        => 'Staff',
                'middle_name'       => null,
                'last_name'         => 'Account',
                'suffix'            => null,

                'username'          => 'staff',
                'password'          => Hash::make('password123'),
                'role'              => 'Staff',
                'email_verified_at' => now(),

                'birthdate'         => '1995-01-01',
                'gender'            => 'Male',
                'civil_status'      => 'Single',
                'address'           => 'Staff Street, City',
                'contact_number'    => '09999999999',

                // ✅ ADD THESE
                'employee_id'       => 'EMP-0001',
                'position'          => 'Receptionist',

                'status'            => 'Active',
                'approval_status'   => 'Approved',
            ]
        );
    }
}