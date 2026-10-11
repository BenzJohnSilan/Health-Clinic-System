<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample Cashier account.
 *
 * A Cashier is NOT a separate role. It is a normal Staff account
 * (role = Staff) whose Position label is "Cashier" and whose access is
 * limited by PERMISSIONS — here the "cashier" preset from
 * Permission::presets(): view_billing, record_payment,
 * view_payment_history and view_billing_reports. It gets no Patients,
 * Appointments, Laboratory, Medical Certificates or Medicine access.
 *
 * Run:  php artisan db:seed --class=CashierSeeder
 *
 * Login:  cashier@example.com  /  password123
 *
 * Safe to run more than once: the account is created only if it does not
 * exist yet, and the preset is applied only when it is first created, so
 * running this again never undoes permissions an Admin changed later in
 * Manage Permissions.
 */
class CashierSeeder extends Seeder
{
    public function run(): void
    {
        // The permission catalog must exist before permissions can be
        // assigned (idempotent — it only adds/updates catalog rows).
        $this->call(PermissionSeeder::class);

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@example.com'],

            [
                'first_name'        => 'Juan',
                'middle_name'       => null,
                'last_name'         => 'Dela Cruz',
                'suffix'            => null,

                'username'          => 'cashier',
                'password'          => Hash::make('password123'),
                'role'              => 'Staff',
                'email_verified_at' => now(),

                'birthdate'         => '1995-01-01',
                'gender'            => 'Male',
                'civil_status'      => 'Single',
                'address'           => 'Cashier Street, City',
                'contact_number'    => '09999999998',

                'employee_id'       => 'EMP-0002',
                'position'          => 'Cashier',

                'status'            => 'Active',
                'approval_status'   => 'Approved',
            ]
        );

        // Only for a freshly created account (see class note).
        if ($cashier->wasRecentlyCreated) {
            Permission::assignDefaultsTo($cashier, 'cashier');
        }
    }
}