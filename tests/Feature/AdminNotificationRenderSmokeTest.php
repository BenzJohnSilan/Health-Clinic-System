<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationRenderSmokeTest extends TestCase
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

    public function test_admin_dashboard_renders_with_notifications(): void
    {
        $admin = $this->makeUser('Admin', 'render-admin@test.com');
        $patient = $this->makeUser('Patient', 'render-patient@test.com');
        $this->makeUser('Patient', 'render-patient2@test.com', ['approval_status' => 'Pending']);

        $doctor = $this->makeUser('Doctor', 'render-doc@test.com');
        Appointment::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $doctor->id,
            'appointment_date'  => now()->addDay()->toDateString(),
            'appointment_time'  => '09:00:00',
            'status'            => 'Pending',
            'reason'            => 'Checkup',
        ]);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('notif-tabs', false);
        $response->assertSee('notif-mark-all-btn', false);
        $response->assertSee('Pending Appointment');
        $response->assertSee('Pending Account');
        // Badge count must equal pendingAccounts(1) + pendingAppointments(1) = 2
        $response->assertSee('>2<', false);
    }

    public function test_admin_pending_accounts_page_renders(): void
    {
        $admin = $this->makeUser('Admin', 'render-admin2@test.com');
        $this->makeUser('Patient', 'render-patient3@test.com', ['approval_status' => 'Pending']);

        $response = $this->actingAs($admin)->get('/admin/pending-accounts');

        $response->assertOk();
    }

    public function test_admin_appointments_page_renders(): void
    {
        $admin = $this->makeUser('Admin', 'render-admin3@test.com');

        $response = $this->actingAs($admin)->get('/admin/appointments');

        $response->assertOk();
    }

    public function test_notification_click_marks_read_and_badge_drops_on_reload(): void
    {
        $admin = $this->makeUser('Admin', 'render-admin4@test.com');
        $patient = $this->makeUser('Patient', 'render-patient4@test.com');
        $doctor = $this->makeUser('Doctor', 'render-doc4@test.com');
        $appointment = Appointment::create([
            'patient_id'        => $patient->id,
            'doctor_id'         => $doctor->id,
            'appointment_date'  => now()->addDay()->toDateString(),
            'appointment_time'  => '09:00:00',
            'status'            => 'Pending',
            'reason'            => 'Checkup',
        ]);

        $event = \App\Models\AppointmentStatusEvent::where('appointment_id', $appointment->id)->first();
        $key = "admin_appointment_{$appointment->id}_Pending_{$event->id}";

        $this->actingAs($admin)->postJson('/admin/notifications/read', ['key' => $key])->assertOk();

        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertOk();
        // Only the account-less scenario: badge should now reflect 0 unread.
        $response->assertDontSee('notif-badge" id="notifBadge">1<', false);
    }

    public function test_staff_and_patient_layouts_unaffected(): void
    {
        $staff = $this->makeUser('Staff', 'render-staff@test.com');
        $patient = $this->makeUser('Patient', 'render-patient5@test.com');

        $this->actingAs($staff)->get('/staff/dashboard')->assertOk();
        $this->actingAs($patient)->get('/patient/dashboard')->assertOk();
    }
}
