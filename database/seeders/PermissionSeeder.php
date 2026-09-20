<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the permission catalog. Safe to run multiple times —
     * updateOrCreate() by slug means no duplicate rows are ever created,
     * and re-running after adding a new permission to
     * Permission::catalog() will simply insert the new one.
     */
    public function run(): void
    {
        // One-time rename: the old 'add_patient' slug (never wired to any
        // route/UI) is being replaced by 'add_walk_in_patient' as part of
        // the Staff Patient Management permission rollout. Renaming the
        // existing row in place — instead of leaving it orphaned and
        // creating a brand-new one — keeps the `permissions.id` stable, so
        // any Staff account an Admin had already checked "Add Patient" for
        // carries straight over to "Add Walk-in Patient" via the existing
        // user_permissions pivot rows (which reference permission_id, not
        // the slug). Safe to run every time: it only fires once, the first
        // time this seeder runs after the rename.
        if (
            Permission::where('slug', 'add_patient')->exists() &&
            !Permission::where('slug', 'add_walk_in_patient')->exists()
        ) {
            Permission::where('slug', 'add_patient')->update(['slug' => 'add_walk_in_patient']);
        }

        // One-time cleanup: 'create_appointment' and 'edit_appointment'
        // are being replaced by the granular Staff Appointment
        // permission set (view_appointment_details / approve_appointment
        // / reject_appointment, alongside the existing view_appointments
        // and cancel_appointment). A project-wide search confirms
        // neither slug is referenced by any route, middleware, or Blade
        // view, so nothing currently depends on them — safe to delete
        // outright rather than leave them as stale, unused entries that
        // would otherwise keep showing up on the Admin Manage
        // Permissions page. Deleting the row also clears any
        // user_permissions rows pointing at it.
        Permission::whereIn('slug', ['create_appointment', 'edit_appointment'])->delete();

        // One-time cleanup: 'manage_payments' is being replaced by the
        // granular Billing & Payments permission set (view_billing,
        // create_invoice, edit_invoice, record_payment,
        // view_payment_history, view_billing_reports) as part of the
        // Doctor/Staff Billing workflow split. A project-wide search
        // confirms 'manage_payments' was never wired to any route,
        // middleware, or Blade view, so nothing currently depends on
        // it — safe to delete outright rather than leave it as a
        // stale, unused entry on the Manage Permissions page.
        Permission::where('slug', 'manage_payments')->delete();

        foreach (Permission::catalog() as $module => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name'   => $name,
                        'module' => $module,
                    ]
                );
            }
        }

        // Backfill: every EXISTING Staff account must also end up with
        // view_appointments, the same way a brand-new Staff account
        // gets it automatically via Permission::assignDefaultsTo() in
        // Admin\UserController::store(). syncWithoutDetaching() only
        // adds the missing permission — it never touches any other
        // permission a Staff member already has — so this is safe to
        // run every time the seeder runs.
        $viewAppointmentsId = Permission::where('slug', 'view_appointments')->value('id');

        if ($viewAppointmentsId) {
            User::where('role', 'Staff')->get()->each(function (User $user) use ($viewAppointmentsId) {
                $user->permissions()->syncWithoutDetaching([$viewAppointmentsId]);
            });
        }

        // Backfill: every EXISTING Staff/Doctor/Patient account also gets
        // the new Billing & Payments defaults for their role, the same
        // way a brand-new account gets them via Permission::
        // assignDefaultsTo() in Admin\UserController::store() (Staff/
        // Doctor) and Auth\RegisterController::register() (Patient).
        // syncWithoutDetaching() only adds the missing permissions — it
        // never touches any permission a user already has (including
        // any billing permission an Admin already customized) — so this
        // is safe to run every time the seeder runs.
        foreach (['Staff', 'Doctor', 'Patient'] as $role) {
            $slugs = Permission::defaultSlugsForRole($role);
            $ids   = Permission::whereIn('slug', $slugs)->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            User::where('role', $role)->get()->each(function (User $user) use ($ids) {
                $user->permissions()->syncWithoutDetaching($ids);
            });
        }
    }
}