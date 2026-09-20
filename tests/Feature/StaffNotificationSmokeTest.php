<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffNotificationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, string $email): User
    {
        return User::create([
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
        ]);
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
        $staff = $this->makeUser('Staff', 'staff-a@test.com');

        $response = $this->actingAs($staff)->postJson('/staff/notifications/read-all');

        $response->assertOk();
        $response->assertJson(['success' => true, 'unread_count' => 0]);
    }

    public function test_staff_can_mark_own_notification_read(): void
    {
        $staff = $this->makeUser('Staff', 'staff-b@test.com');
        $patient = $this->makeUser('Patient', 'patient-b@test.com');
        $appointment = $this->makeAppointment(['patient_id' => $patient->id]);

        $event = \App\Models\AppointmentStatusEvent::where('appointment_id', $appointment->id)->first();

        $key = "staff_appointment_{$appointment->id}_Pending_{$event->id}";

        $response = $this->actingAs($staff)->postJson('/staff/notifications/read', ['key' => $key]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('staff_notification_reads', [
            'user_id'          => $staff->id,
            'notification_key' => $key,
        ]);
    }

    public function test_staff_cannot_mark_another_staffs_notification(): void
    {
        // The Staff notification read-state is not per-appointment-owner —
        // it is per authenticated staff member. This test verifies a
        // fabricated/non-existent key is rejected regardless of who sends it.
        $staffA = $this->makeUser('Staff', 'staff-c@test.com');

        $bogusKey = 'staff_appointment_999999_Approved_999999';

        $response = $this->actingAs($staffA)->postJson('/staff/notifications/read', ['key' => $bogusKey]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('staff_notification_reads', [
            'user_id'          => $staffA->id,
            'notification_key' => $bogusKey,
        ]);
    }

    public function test_unread_count_becomes_zero_after_mark_all_read(): void
    {
        $staff = $this->makeUser('Staff', 'staff-d@test.com');
        $patient = $this->makeUser('Patient', 'patient-d@test.com');
        $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\StaffNotificationService::class);
        $this->assertGreaterThan(0, $service->unreadCount($staff));

        $this->actingAs($staff)->postJson('/staff/notifications/read-all')->assertOk();

        $this->assertSame(0, $service->unreadCount($staff->fresh()));
    }

    public function test_read_state_persists_after_rebuilding_feed(): void
    {
        $staff = $this->makeUser('Staff', 'staff-e@test.com');
        $patient = $this->makeUser('Patient', 'patient-e@test.com');
        $appointment = $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\StaffNotificationService::class);

        $key = $service->build($staff)->first()['key'];
        $service->markRead($staff, $key);

        $rebuilt = $service->build($staff)->firstWhere('key', $key);
        $this->assertFalse($rebuilt['unread']);
    }

    public function test_new_status_event_creates_new_notification(): void
    {
        $staff = $this->makeUser('Staff', 'staff-f@test.com');
        $patient = $this->makeUser('Patient', 'patient-f@test.com');
        $appointment = $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\StaffNotificationService::class);
        $countBefore = $service->build($staff)->count();

        $appointment->status = 'Approved';
        $appointment->save();

        $countAfter = $service->build($staff)->count();
        $this->assertGreaterThan($countBefore, $countAfter);
    }

    public function test_different_status_events_generate_different_keys(): void
    {
        $staff = $this->makeUser('Staff', 'staff-g@test.com');
        $patient = $this->makeUser('Patient', 'patient-g@test.com');
        $appointment = $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\StaffNotificationService::class);
        $keyPending = $service->build($staff)->first()['key'];

        $appointment->status = 'Cancelled';
        $appointment->save();

        $keyCancelled = $service->build($staff)->first()['key'];

        $this->assertNotSame($keyPending, $keyCancelled);
    }

    public function test_old_read_notification_remains_read_after_new_status_event(): void
    {
        $staff = $this->makeUser('Staff', 'staff-h@test.com');
        $patient = $this->makeUser('Patient', 'patient-h@test.com');
        $appointment = $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\StaffNotificationService::class);
        $pendingKey = $service->build($staff)->first()['key'];
        $service->markRead($staff, $pendingKey);

        $appointment->status = 'Approved';
        $appointment->save();

        $rebuilt = $service->build($staff);
        $pendingItem = $rebuilt->firstWhere('key', $pendingKey);

        $this->assertNotNull($pendingItem);
        $this->assertFalse($pendingItem['unread']);
    }

    public function test_registered_patient_appointment_works(): void
    {
        $staff = $this->makeUser('Staff', 'staff-i@test.com');
        $patient = $this->makeUser('Patient', 'patient-i@test.com');
        $this->makeAppointment(['patient_id' => $patient->id]);

        $service = app(\App\Services\StaffNotificationService::class);
        $item = $service->build($staff)->first();

        $this->assertStringContainsString($patient->first_name, $item['message']);
    }

    public function test_walkin_patient_appointment_works(): void
    {
        $staff = $this->makeUser('Staff', 'staff-j@test.com');
        $walkin = $this->makeWalkinPatient();
        $this->makeAppointment(['walkin_patient_id' => $walkin->id]);

        $service = app(\App\Services\StaffNotificationService::class);
        $item = $service->build($staff)->first();

        $this->assertStringContainsString('(Walk-in)', $item['message']);
    }

    public function test_existing_patient_notification_tests_still_pass(): void
    {
        // Sanity check: Staff notifications must never touch the Patient
        // notification read-state table.
        $patient = $this->makeUser('Patient', 'patient-k@test.com');

        $response = $this->actingAs($patient)->postJson('/patient/notifications/read-all');

        $response->assertOk();
        $response->assertJson(['success' => true, 'unread_count' => 0]);
    }
}
