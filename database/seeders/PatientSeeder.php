<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample Patient account.
 *
 * An approved, email-verified Patient whose profile is COMPLETE (blood
 * type, allergies and emergency contact filled in), so it can log in and
 * book an appointment straight away. It receives the standard Patient
 * permissions only (view_billing and view_payment_history — read-only access
 * to their own invoices and receipts).
 *
 * Run:  php artisan db:seed --class=PatientSeeder
 *
 * Login:  patient@example.com  /  password123
 *
 * Safe to run more than once: the account is created only if it does not
 * exist yet, and its permissions are assigned only when it is first created.
 */
class PatientSeeder extends Seeder
{
    public function run(): void
    {
        // The permission catalog must exist before permissions can be
        // assigned (idempotent — it only adds/updates catalog rows).
        $this->call(PermissionSeeder::class);

        $patient = User::firstOrCreate(
            ['email' => 'patient@example.com'],

            [
                'first_name'        => 'Benz John',
                'middle_name'       => null,
                'last_name'         => 'Silan',
                'suffix'            => null,

                'username'          => 'Benz',
                'password'          => Hash::make('Benz@123/'),
                'role'              => 'Patient',
                'email_verified_at' => now(),

                'birthdate'         => '2004-12-19',
                'gender'            => 'Male',
                'civil_status'      => 'Single',
                'address'           => 'Brgy. Dayap Calauan, Laguna',
                'contact_number'    => '09916365449',

                // ================= MEDICAL INFORMATION =================
                'blood_type'        => 'O+',
                'allergies'         => 'None',

                // ================= EMERGENCY CONTACT =================
                'emergency_name'           => 'Zandra Silan',
                'emergency_contact_number' => '09916365888',
                'relationship'             => 'Mother',
                'emergency_address'        => 'Brgy. Dayap Calauan, Laguna',

                'status'            => 'Active',
                'approval_status'   => 'Approved',
            ]
        );

        // Only for a freshly created account (see class note).
        if ($patient->wasRecentlyCreated) {
            Permission::assignDefaultsTo($patient);
        }
    }
}