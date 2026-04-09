<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Appointment;
use App\Models\User;

class AppointmentSeeder extends Seeder
{
    public function run()
    {
        $patient = User::where('role', 'Patient')->first();
        $doctor = User::where('role', 'Doctor')->first();

        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'date' => now()->addDays(1)->toDateString(),
            'time' => '14:00:00',
            'status' => 'Confirmed',
            'notes' => 'First appointment'
        ]);

        Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'date' => now()->addDays(3)->toDateString(),
            'time' => '10:00:00',
            'status' => 'Pending',
            'notes' => 'Follow-up'
        ]);
    }
}
