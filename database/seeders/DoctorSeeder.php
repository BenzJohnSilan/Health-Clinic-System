<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'doctor@example.com'],

            [
                'first_name'        => 'Maria',
                'middle_name'       => null,
                'last_name'         => 'Santos',
                'suffix'            => null,

                'username'          => 'doctor',
                'password'          => Hash::make('maria@123'),
                'role'              => 'Doctor',
                'email_verified_at' => now(),

                'birthdate'         => '1985-05-10',
                'gender'            => 'Male',
                'civil_status'      => 'Married',
                'address'           => 'Doctor Street, City',
                'contact_number'    => '09123456788',

                // ================= DOCTOR INFO =================
                'specialization'    => 'Cardiology',
                'license_number'    => 'PRC-1234567',

                'status'            => 'Active',
                'approval_status'   => 'Approved',
            ]
        );
    }
}