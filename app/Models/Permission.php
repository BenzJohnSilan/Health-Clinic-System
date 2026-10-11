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
                'record_vital_signs'       => 'Record Vital Signs',
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
                'release_medical_certificate' => 'Release Medical Certificate',
            ],
            'Laboratory' => [
                'view_laboratory_requests'  => 'View Laboratory Requests',
                'upload_laboratory_result'  => 'Upload Laboratory Result',
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
     * `view_appointments` is the one Appointment permission granted by
     * the STANDARD Staff default — it is the basic/default Staff
     * Appointment access (view-only). Staff accounts created with the
     * Cashier preset (see presets()) do NOT get it, and Admin can remove
     * it from any Staff account in Manage Permissions. Every other
     * Appointment permission
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
                'view_medical_certificates',
                'release_medical_certificate',
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
     * Billing-only permission set for a Staff member who works as a
     * Cashier. These slugs already exist in catalog() — a Cashier is NOT
     * a separate role: it is a normal Staff account (role = Staff) whose
     * permissions are limited to these. The "Position" text on the
     * account (e.g. "Cashier") is only a label; access is decided by the
     * permissions below, never by the position.
     */
    public const CASHIER_SLUGS = [
        'view_billing',
        'record_payment',
        'view_payment_history',
        'view_billing_reports',
    ];

    /**
     * Permission presets an Admin can pick for a Staff account — when
     * creating it (Admin → Manage Users → Add User) or as a quick-fill
     * in Manage Permissions. A preset is only a starting checklist of
     * permission slugs; Admin can still add/remove any permission
     * afterwards, and nothing here changes how access is enforced.
     *
     * @return array<string, array{label: string, description: string, slugs: array<int, string>}>
     */
    public static function presets(): array
    {
        return [
            'standard' => [
                'label'       => 'Standard Staff',
                'description' => 'The default Staff permission set (appointments, billing, medicine, medical certificates).',
                'slugs'       => self::defaultSlugsForRole('Staff'),
            ],
            'cashier' => [
                'label'       => 'Cashier (Billing & Payments only)',
                'description' => 'View billing, record payments, view payment history and billing reports only. No patients, appointments, laboratory, medical certificates or medicine access.',
                'slugs'       => self::CASHIER_SLUGS,
            ],
        ];
    }

    /**
     * Assign the default permission set for a freshly created user
     * (or, for a Staff account, the chosen preset — e.g. 'cashier').
     * Safe to call for any role — roles with no defaults simply get
     * nothing assigned. Admin never needs rows here (bypasses checks
     * entirely) and Patient must never receive Medicine permissions.
     */
    public static function assignDefaultsTo(User $user, ?string $preset = null): void
    {
        // A preset only applies to Staff accounts. Anything else (or an
        // unknown preset key) falls back to the role default, so existing
        // callers — Admin\UserController for Doctor/Patient and
        // Auth\RegisterController for Patient — behave exactly as before.
        $presets = self::presets();

        $slugs = ($user->role === 'Staff' && $preset !== null && isset($presets[$preset]))
            ? $presets[$preset]['slugs']
            : self::defaultSlugsForRole($user->role);

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