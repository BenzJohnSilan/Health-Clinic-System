<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    /**
     * The fixed catalog of permissions grouped by module, in display
     * order. This is the single source of truth used by the
     * PermissionSeeder and by the Manage Permissions page. Add future
     * modules/permissions here first, then re-run the seeder.
     *
     * Slugs are stable and must never change once in use — Blade views
     * and route middleware (permission:<slug>) reference them directly.
     */
    public static function catalog(): array
    {
        return [
            'Appointments' => [
                'view_appointments'        => 'View Appointments',
                'view_appointment_details' => 'View Appointment Details',
                'approve_appointment'      => 'Approve Appointment',
                'reject_appointment'       => 'Reject Appointment',
                'cancel_appointment'       => 'Cancel Appointment',
                'reschedule_appointment'   => 'Reschedule Appointment',
                'mark_no_show_appointment' => 'Mark Appointment as No Show',
                'check_in_appointment'     => 'Check In Appointment',
            ],
            'Patients' => [
                'view_patients'                => 'View Patients',
                'add_walk_in_patient'          => 'Add Walk-in Patient',
                'edit_patient'                 => 'Edit Patient',
                'view_patient_appointments'    => 'View Patient Appointments',
                'schedule_patient_appointment' => 'Schedule Patient Appointment',
            ],
            'Medicine Inventory' => [
                'view_medicine_inventory' => 'View Medicine Inventory',
                'add_medicine'            => 'Add Medicine',
                'edit_medicine'           => 'Edit Medicine',
                'delete_medicine'         => 'Delete Medicine',
                'stock_in'                => 'Stock In',
                'stock_out'               => 'Stock Out',
                'adjust_stock'            => 'Adjust Stock',
                'view_stock_history'      => 'View Stock History',
                'view_inventory_reports'  => 'View Inventory Reports',
            ],
            'Billing & Payments' => [
                'view_billing'         => 'View Billing',
                'create_invoice'       => 'Create Invoice',
                'edit_invoice'         => 'Edit Invoice',
                'record_payment'       => 'Record Payment',
                'view_payment_history' => 'View Payment History',
                'view_billing_reports' => 'View Billing Reports',
            ],
            'Medical Records' => [
                'view_medical_records' => 'View Medical Records',
                'add_medical_record'   => 'Add Medical Record',
                'edit_medical_record'  => 'Edit Medical Record',
            ],
            'Prescriptions' => [
                'view_prescriptions'    => 'View Prescriptions',
                'create_prescription'   => 'Create Prescription',
                'edit_prescription'     => 'Edit Prescription',
                'dispense_prescription' => 'Dispense Prescription',
            ],
            'Medical Certificates' => [
                'view_medical_certificates'   => 'View Medical Certificates',
                'create_medical_certificate'  => 'Create Medical Certificate',
            ],
        ];
    }

    /**
     * Default permission slugs granted automatically to a brand-new
     * Staff account. Admin can change these afterwards from
     * Manage Permissions. Only the currently-implemented Medicine
     * CRUD defaults are granted — never Delete/Stock, per the
     * Medicine module scope for this phase.
     *
     * `view_appointments` is the one Appointment permission that is
     * ALWAYS granted — it is the basic/default Staff Appointment
     * access (view-only). Every other Appointment permission
     * (view_appointment_details, approve_appointment,
     * reject_appointment, cancel_appointment, reschedule_appointment,
     * mark_no_show_appointment) is deliberately left out here and must
     * be granted individually via Manage Permissions.
     *
     * Billing & Payments defaults (recommended starting point only —
     * Admin can add/remove any of these afterwards from Manage
     * Permissions, same as every other module):
     *  - Staff: view_billing, record_payment, view_payment_history,
     *    view_billing_reports (payment collection at the counter).
     *  - Doctor: view_billing, create_invoice, edit_invoice,
     *    view_payment_history (charge/invoice management during
     *    consultation). Doctor never gets record_payment by default —
     *    Admin must explicitly grant it.
     *  - Patient: view_billing, view_payment_history (read-only —
     *    view their own invoices/receipts). Patient never gets
     *    create_invoice, edit_invoice, or record_payment.
     */
    public static function defaultSlugsForRole(string $role): array
    {
        return match ($role) {
            'Staff' => [
                'view_appointments',
                'view_medicine_inventory',
                'add_medicine',
                'edit_medicine',
                'view_billing',
                'record_payment',
                'view_payment_history',
                'view_billing_reports',
            ],
            'Doctor' => [
                'view_billing',
                'create_invoice',
                'edit_invoice',
                'view_payment_history',
            ],
            'Patient' => [
                'view_billing',
                'view_payment_history',
            ],
            default => [],
        };
    }

    /**
     * Assign the default permission set for a freshly created user.
     * Safe to call for any role — roles with no defaults simply get
     * nothing assigned. Admin never needs rows here (bypasses checks
     * entirely) and Patient must never receive Medicine permissions.
     */
    public static function assignDefaultsTo(User $user): void
    {
        $slugs = self::defaultSlugsForRole($user->role);

        if (empty($slugs)) {
            return;
        }

        $ids = self::whereIn('slug', $slugs)->pluck('id');

        if ($ids->isNotEmpty()) {
            $user->permissions()->syncWithoutDetaching($ids);
        }
    }

    // =========================================================
    //  RELATIONSHIPS
    // =========================================================

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')
                    ->withTimestamps();
    }
}