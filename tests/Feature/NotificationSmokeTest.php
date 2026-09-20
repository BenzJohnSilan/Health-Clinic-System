<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function makePatient(string $email): User
    {
        return User::create([
            'first_name'      => 'Test',
            'last_name'       => 'Patient',
            'birthdate'       => '1995-01-01',
            'gender'          => 'Male',
            'civil_status'    => 'Single',
            'address'         => 'Sample St',
            'contact_number'  => '0917' . random_int(1000000, 9999999),
            'username'        => 'user' . random_int(100000, 999999),
            'email'           => $email,
            'password'        => bcrypt('password'),
            'role'            => 'Patient',
            'approval_status' => 'Approved',
            'email_verified_at' => now(),
        ]);
    }

    public function test_mark_all_read_endpoint_works(): void
    {
        $patient = $this->makePatient('a@test.com');

        $response = $this->actingAs($patient)->postJson('/patient/notifications/read-all');

        $response->assertOk();
        $response->assertJson(['success' => true, 'unread_count' => 0]);
    }

    public function test_patient_cannot_mark_another_patients_notification(): void
    {
        $patientA = $this->makePatient('b@test.com');
        $patientB = $this->makePatient('c@test.com');

        $key = "account_{$patientA->id}_approved";

        $response = $this->actingAs($patientB)->postJson('/patient/notifications/read', ['key' => $key]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('patient_notification_reads', [
            'user_id'           => $patientB->id,
            'notification_key'  => $key,
        ]);
    }

    public function test_patient_can_mark_own_notification_read(): void
    {
        $patient = $this->makePatient('d@test.com');

        $key = "account_{$patient->id}_approved";

        $response = $this->actingAs($patient)->postJson('/patient/notifications/read', ['key' => $key]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'unread_count' => 0]);
        $this->assertDatabaseHas('patient_notification_reads', [
            'user_id'           => $patient->id,
            'notification_key'  => $key,
        ]);
    }
}
