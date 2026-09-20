<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, string $email, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name'        => 'Test',
            'last_name'         => ucfirst(strtolower($role)),
            'birthdate'         => '1995-01-01',
            'gender'            => 'Male',
            'civil_status'      => 'Single',
            'address'           => 'Sample St',
            'contact_number'    => '0917' . random_int(1000000, 9999999),
            'username'          => 'user' . random_int(100000, 999999),
            'email'             => $email,
            'password'          => bcrypt('password'),
            'role'              => $role,
            'approval_status'   => 'Approved',
            'email_verified_at' => now(),
        ], $overrides));
    }

    protected function makeWalkinPatient(): Patient
    {
        return Patient::create([
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'birthdate'      => '1990-01-01',
            'gender'         => 'Male',
            'civil_status'   => 'Single',
            'contact_number' => '0917' . random_int(1000000, 9999999),
            'address'        => 'Sample St',
            'is_walk_in'     => true,
        ]);
    }

    protected function makeAppointment(array $overrides = []): Appointment
    {
        $doctor = $this->makeUser('Doctor', 'doc' . random_int(1000, 9999) . '@test.com');

        return Appointment::create(array_merge([
            'doctor_id'         => $doctor->id,
            'appointment_date'  => now()->addDays(1)->toDateString(),
            'appointment_time'  => '09:00:00',
            'status'            => 'Pending',
            'reason'            => 'Checkup',
        ], $overrides));
    }

    public function test_mark_all_read_endpoint_works(): void
    {
        $admin = $this->makeUser('Admin', 'admin-a@test.com');

        $response = $this->actingAs($admin)->postJson('/admin/notifications/read-all');

        $response->assertOk();
        $response->assertJson(['success' => true, 'unread_count' => 0]);
    }

    public function test_admin_can_mark_own_pending_appointment_notification_read(): void
    {
        $admin = $this->makeUser('Admin', 'admin-b@test.com');
        $patient = $this->makeUser('Patient', 'patient-b@test.com');
        $appointment = $this->makeAppointment(['patient_id' => $patient->id]);

        $event = \App\Models\AppointmentStatusEvent::where('appointment_id', $appointment->id)->first();

        $key = "admin_appointment_{$appointment->id}_Pending_{$event->id}";

        $response = $this->actingAs($admin)->postJson('/admin/notifications/read', ['key' => $key]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id'          => $admin->id,
            'notification_key' => $key,
        ]);
    }

    public function test_admin_can_mark_own_pending_account_notification_read(): void
    {
        $admin = $this->makeUser('Admin', 'admin-c@test.com');
        $pendingPatient = $this->makeUser('Patient', 'patient-c@test.com', ['approval_status' => 'Pending']);

        $key = "admin_account_{$pendingPatient->id}";

        $response = $this->actingAs($admin)->postJson('/admin/notifications/read', ['key' => $key]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('admin_notification_reads', [
            'user_id'          => $admin->id,
            'notification_key' => $key,
        ]);
    }

    public function test_admin_cannot_mark_bogus_notification_read(): void
    {
        $admin = $this->makeUser('Admin', 'admin-d@test.com');

        $bogusKey = 'admin_appointment_999999_Pending_999999';

        $response = $this->actingAs($admin)->postJson('/admin/notifications/read', ['key' => $bogusKey]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('admin_notification_reads', [
            'user_id'          => $admin->id,
            'notification_key' => $bogusKey,
        ]);
    }

    public function test_unread_count_becomes_zero_after_mark_all_read(): void
    {
        $admin = $this->makeUser('Admin', 'admin-e@test.com');
        $patient = $this->makeUser('Patient', 'patient-e@test.com');
        $this->makeAppointment(['patient_id' => $patient->id]);
        $this->makeUser('Patient', 'patient-e2@test.com', ['approval_status' => 'Pending']);

        $service = app(\App\Services\AdminNotificationService::class);
        $this->assertGreaterThan(0, $service->unreadCount($admin));

        $this->actingAs($admin)->postJson('/admin/notifications/read-all')->assertOk();

        $this->assertSame(0, $service->unreadCount($admin->fresh()));
    }

    public function test_read_state_persists_after_rebuilding_feed(): void
    {
        $admin = $this->makeUser('Admin', 'admin-f@test.com');
        $patient = $this->makeUser('Patient', 'patient-f@test.com');
        $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\AdminNotificationService::class);

        $key = $service->build($admin)->first()['key'];
        $service->markRead($admin, $key);

        $rebuilt = $service->build($admin)->firstWhere('key', $key);
        $this->assertFalse($rebuilt['unread']);
    }

    public function test_approving_account_removes_its_notification(): void
    {
        $admin = $this->makeUser('Admin', 'admin-g@test.com');
        $pendingPatient = $this->makeUser('Patient', 'patient-g@test.com', ['approval_status' => 'Pending']);

        $service = app(\App\Services\AdminNotificationService::class);
        $this->assertTrue($service->build($admin)->contains('key', "admin_account_{$pendingPatient->id}"));

        $pendingPatient->approval_status = 'Approved';
        $pendingPatient->save();

        $this->assertFalse($service->build($admin)->contains('key', "admin_account_{$pendingPatient->id}"));
    }

    public function test_admin_accounts_are_never_shown_as_pending_account_notifications(): void
    {
        // Mirrors AdminController::pendingAccounts() / the Dashboard's
        // pending count, which both exclude role = Admin — the
        // notification feed must stay consistent with those pages.
        $admin = $this->makeUser('Admin', 'admin-h@test.com');
        $this->makeUser('Admin', 'admin-i@test.com', ['approval_status' => 'Pending']);

        $service = app(\App\Services\AdminNotificationService::class);
        $items = $service->build($admin)->where('category', 'accounts');

        $this->assertCount(0, $items);
    }

    public function test_new_pending_appointment_creates_notification(): void
    {
        $admin = $this->makeUser('Admin', 'admin-j@test.com');
        $patient = $this->makeUser('Patient', 'patient-j@test.com');

        $service = app(\App\Services\AdminNotificationService::class);
        $countBefore = $service->build($admin)->count();

        $this->makeAppointment(['patient_id' => $patient->id]);

        $countAfter = $service->build($admin)->count();
        $this->assertGreaterThan($countBefore, $countAfter);
    }

    public function test_approving_appointment_removes_its_pending_notification(): void
    {
        $admin = $this->makeUser('Admin', 'admin-k@test.com');
        $patient = $this->makeUser('Patient', 'patient-k@test.com');
        $appointment = $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\AdminNotificationService::class);
        $this->assertGreaterThan(0, $service->build($admin)->where('category', 'appointments')->count());

        $appointment->status = 'Approved';
        $appointment->save();

        $this->assertCount(0, $service->build($admin)->where('category', 'appointments'));
    }

    public function test_unread_count_matches_dashboard_pending_counts(): void
    {
        $admin = $this->makeUser('Admin', 'admin-l@test.com');
        $patient = $this->makeUser('Patient', 'patient-l@test.com');
        $this->makeAppointment(['patient_id' => $patient->id]);
        $this->makeAppointment(['patient_id' => $patient->id]);
        $this->makeUser('Patient', 'patient-l2@test.com', ['approval_status' => 'Pending']);

        $expectedPendingAccounts = User::where('approval_status', 'Pending')
            ->where('role', '!=', 'Admin')
            ->count();
        $expectedPendingAppointments = Appointment::where('status', 'Pending')->count();

        $service = app(\App\Services\AdminNotificationService::class);

        $this->assertSame(
            $expectedPendingAccounts + $expectedPendingAppointments,
            $service->unreadCount($admin)
        );
    }

    public function test_walkin_patient_appointment_works(): void
    {
        $admin = $this->makeUser('Admin', 'admin-m@test.com');
        $walkin = $this->makeWalkinPatient();
        $this->makeAppointment(['walkin_patient_id' => $walkin->id]);

        $service = app(\App\Services\AdminNotificationService::class);
        $item = $service->build($admin)->firstWhere('category', 'appointments');

        $this->assertStringContainsString('Dela Cruz', $item['title']);
    }

    public function test_existing_staff_notification_tests_still_pass(): void
    {
        // Sanity check: Admin notifications must never touch the Staff
        // notification read-state table.
        $staff = $this->makeUser('Staff', 'staff-z@test.com');

        $response = $this->actingAs($staff)->postJson('/staff/notifications/read-all');

        $response->assertOk();
        $response->assertJson(['success' => true, 'unread_count' => 0]);
    }
}
