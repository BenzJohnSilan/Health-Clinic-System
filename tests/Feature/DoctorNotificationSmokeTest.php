<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentStatusEvent;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryResult;
use App\Models\Patient;
use App\Models\User;
use App\Services\DoctorNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DoctorNotificationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, string $email, array $overrides = []): User
    {
        return User::forceCreate(array_merge([
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

    /** appointments has a unique (doctor, date, time) slot — give each a new one. */
    protected int $slot = 0;

    protected function makeAppointment(User $doctor, User $patient, array $overrides = []): Appointment
    {
        $this->slot++;

        return Appointment::create(array_merge([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => sprintf('%02d:%02d:00', 8 + intdiv($this->slot, 2), ($this->slot % 2) * 30),
            'status'           => 'Pending',
            'reason'           => 'Checkup',
        ], $overrides));
    }

    protected function makeLab(User $doctor, User $patient, array $overrides = []): LaboratoryRequest
    {
        $appointment = $this->makeAppointment($doctor, $patient, ['status' => 'In Progress']);

        return LaboratoryRequest::create(array_merge([
            'appointment_id'     => $appointment->id,
            'patient_id'         => $patient->id,
            'doctor_id'          => $doctor->id,
            'diagnosis'          => 'Routine',
            'status'             => 'Result Received',
            'result_received_at' => now(),
        ], $overrides));
    }

    protected function service(): DoctorNotificationService
    {
        return app(DoctorNotificationService::class);
    }

    // ------------------------------------------------------------------
    // Server-side filtering (security)
    // ------------------------------------------------------------------

    public function test_doctor_only_sees_own_appointment_notifications(): void
    {
        $docA = $this->makeUser('Doctor', 'doc-a@test.com', ['first_name' => 'Alice']);
        $docB = $this->makeUser('Doctor', 'doc-b@test.com', ['first_name' => 'Bob']);
        $patA = $this->makeUser('Patient', 'pat-a@test.com', ['first_name' => 'PatientOfA']);
        $patB = $this->makeUser('Patient', 'pat-b@test.com', ['first_name' => 'PatientOfB']);

        $apptA = $this->makeAppointment($docA, $patA);
        $apptB = $this->makeAppointment($docB, $patB);

        $feedA = $this->service()->build($docA);
        $feedB = $this->service()->build($docB);

        $this->assertCount(1, $feedA);
        $this->assertStringContainsString('PatientOfA', $feedA->first()['message']);
        $this->assertStringContainsString("doctor_appointment_{$apptA->id}_", $feedA->first()['key']);

        $this->assertCount(1, $feedB);
        $this->assertStringContainsString('PatientOfB', $feedB->first()['message']);
        $this->assertStringContainsString("doctor_appointment_{$apptB->id}_", $feedB->first()['key']);
    }

    public function test_doctor_only_sees_own_laboratory_notifications(): void
    {
        $docA = $this->makeUser('Doctor', 'doc-a2@test.com');
        $docB = $this->makeUser('Doctor', 'doc-b2@test.com');
        $patA = $this->makeUser('Patient', 'pat-a2@test.com');
        $patB = $this->makeUser('Patient', 'pat-b2@test.com');

        $labA = $this->makeLab($docA, $patA);
        $labB = $this->makeLab($docB, $patB);

        $labsA = $this->service()->build($docA)->where('category', 'laboratory');
        $labsB = $this->service()->build($docB)->where('category', 'laboratory');

        $this->assertCount(1, $labsA);
        $this->assertStringContainsString("doctor_lab_{$labA->id}_", $labsA->first()['key']);
        $this->assertCount(1, $labsB);
        $this->assertStringContainsString("doctor_lab_{$labB->id}_", $labsB->first()['key']);
        $this->assertSame('Laboratory result is ready for review.', $labsA->first()['message']);
    }

    public function test_lab_awaiting_result_or_reviewed_does_not_notify(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-lab@test.com');
        $pat = $this->makeUser('Patient', 'pat-lab@test.com');

        $lab = $this->makeLab($doc, $pat, ['status' => 'Awaiting Result', 'result_received_at' => null]);
        $this->assertCount(0, $this->service()->build($doc)->where('category', 'laboratory'));

        $lab->update(['status' => 'Result Received', 'result_received_at' => now()]);
        $this->assertCount(1, $this->service()->build($doc)->where('category', 'laboratory'));

        $lab->update(['status' => 'Reviewed', 'reviewed_at' => now()]);
        $this->assertCount(0, $this->service()->build($doc)->where('category', 'laboratory'));
    }

    public function test_doctor_cannot_mark_another_doctors_notification_read(): void
    {
        $docA = $this->makeUser('Doctor', 'doc-a3@test.com');
        $docB = $this->makeUser('Doctor', 'doc-b3@test.com');
        $patB = $this->makeUser('Patient', 'pat-b3@test.com');

        $this->makeAppointment($docB, $patB);
        $keyOfB = $this->service()->build($docB)->first()['key'];

        $this->actingAs($docA)
            ->postJson(route('doctor.notifications.read'), ['key' => $keyOfB])
            ->assertStatus(422);

        $this->assertDatabaseMissing('doctor_notification_reads', ['notification_key' => $keyOfB]);
        $this->assertSame(1, $this->service()->unreadCount($docB));
    }

    public function test_bogus_key_is_rejected(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-bogus@test.com');

        $this->actingAs($doc)
            ->postJson(route('doctor.notifications.read'), ['key' => 'doctor_appointment_999999_Approved_999999'])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Appointment lifecycle coverage
    // ------------------------------------------------------------------

    public function test_all_required_appointment_statuses_generate_notifications(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-status@test.com');
        $pat = $this->makeUser('Patient', 'pat-status@test.com');
        $appt = $this->makeAppointment($doc, $pat); // Pending

        foreach (['Approved', 'Rejected', 'Cancelled', 'Rescheduled', 'Checked In', 'In Progress', 'Completed'] as $i => $status) {
            $appt->status = $status;
            $appt->save();
        }

        $labels = $this->service()->build($doc)->pluck('label')->all();

        foreach (['New', 'Approved', 'Rejected', 'Cancelled', 'Rescheduled', 'Checked In', 'In Progress', 'Completed'] as $expected) {
            $this->assertContains($expected, $labels, "Missing '{$expected}' notification");
        }
    }

    public function test_walkin_patient_appointment_notification_works(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-walkin@test.com');
        $walkin = Patient::create([
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'birthdate' => '1990-01-01',
            'gender' => 'Male', 'civil_status' => 'Single', 'contact_number' => '09171234567',
            'address' => 'Sample St', 'is_walk_in' => true,
        ]);

        Appointment::create([
            'walkin_patient_id' => $walkin->id, 'doctor_id' => $doc->id,
            'appointment_date' => now()->addDay()->toDateString(), 'appointment_time' => '10:00:00',
            'status' => 'Pending', 'reason' => 'Checkup',
        ]);

        $this->assertStringContainsString('(Walk-in)', $this->service()->build($doc)->first()['message']);
    }

    // ------------------------------------------------------------------
    // Read / unread behaviour
    // ------------------------------------------------------------------

    public function test_mark_single_read_persists_and_updates_count(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-read@test.com');
        $pat = $this->makeUser('Patient', 'pat-read@test.com');
        $this->makeAppointment($doc, $pat);
        $this->makeAppointment($doc, $pat);

        $key = $this->service()->build($doc)->first()['key'];

        $this->actingAs($doc)
            ->postJson(route('doctor.notifications.read'), ['key' => $key])
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 1]);

        $this->assertDatabaseHas('doctor_notification_reads', ['user_id' => $doc->id, 'notification_key' => $key]);

        // "Refresh" / "logout + login": state is rebuilt from the DB only.
        $this->app['auth']->forgetGuards();
        $rebuilt = $this->service()->build($doc->fresh());
        $this->assertFalse($rebuilt->firstWhere('key', $key)['unread']);
        $this->assertSame(1, $this->service()->unreadCount($doc->fresh()));
    }

    public function test_mark_all_read_works_and_persists(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-all@test.com');
        $pat = $this->makeUser('Patient', 'pat-all@test.com');
        $this->makeAppointment($doc, $pat);
        $this->makeLab($doc, $pat); // creates its own In Progress appointment too

        // 1 Pending event + 1 In Progress event (lab's appointment) + 1 lab result.
        $this->assertSame(3, $this->service()->unreadCount($doc));

        $this->actingAs($doc)
            ->postJson(route('doctor.notifications.read-all'))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);

        $this->app['auth']->forgetGuards();
        $this->assertSame(0, $this->service()->unreadCount($doc->fresh()));
        $this->assertSame(3, DB::table('doctor_notification_reads')->where('user_id', $doc->id)->count());
    }

    public function test_new_status_event_creates_new_unread_and_keeps_old_read(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-new@test.com');
        $pat = $this->makeUser('Patient', 'pat-new@test.com');
        $appt = $this->makeAppointment($doc, $pat);

        $pendingKey = $this->service()->build($doc)->first()['key'];
        $this->service()->markRead($doc, $pendingKey);
        $this->assertSame(0, $this->service()->unreadCount($doc));

        $appt->status = 'Approved';
        $appt->save();

        $feed = $this->service()->build($doc);
        $this->assertSame(1, $this->service()->unreadCount($doc));
        $this->assertFalse($feed->firstWhere('key', $pendingKey)['unread']);
        $this->assertNotSame($pendingKey, $feed->first()['key']);
    }

    public function test_corrected_lab_reupload_creates_fresh_unread_notification(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-corr@test.com');
        $pat = $this->makeUser('Patient', 'pat-corr@test.com');
        $lab = $this->makeLab($doc, $pat);

        $result = LaboratoryResult::create([
            'laboratory_request_id' => $lab->id, 'test_name' => null, 'file_path' => 'a.pdf',
            'original_filename' => 'a.pdf', 'file_type' => 'pdf', 'file_size' => 10,
        ]);

        $firstKey = $this->service()->build($doc)->firstWhere('category', 'laboratory')['key'];
        $this->service()->markRead($doc, $firstKey);

        // Replacement upload supersedes (soft-deletes) the old file.
        $new = LaboratoryResult::create([
            'laboratory_request_id' => $lab->id, 'test_name' => null, 'file_path' => 'b.pdf',
            'original_filename' => 'b.pdf', 'file_type' => 'pdf', 'file_size' => 10,
        ]);
        LaboratoryResult::supersede($lab->id, null, $new->id);

        $second = $this->service()->build($doc)->firstWhere('category', 'laboratory');
        $this->assertNotSame($firstKey, $second['key']);
        $this->assertTrue($second['unread']);
    }

    // ------------------------------------------------------------------
    // Isolation from the other roles' read state
    // ------------------------------------------------------------------

    public function test_doctor_read_state_never_touches_other_roles_tables(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-iso@test.com');
        $pat = $this->makeUser('Patient', 'pat-iso@test.com');
        $this->makeAppointment($doc, $pat);

        $this->actingAs($doc)->postJson(route('doctor.notifications.read-all'))->assertOk();

        $this->assertSame(0, DB::table('admin_notification_reads')->count());
        $this->assertSame(0, DB::table('staff_notification_reads')->count());
        $this->assertSame(0, DB::table('patient_notification_reads')->count());
        $this->assertGreaterThan(0, DB::table('doctor_notification_reads')->count());
    }

    public function test_non_doctor_roles_cannot_use_doctor_endpoints(): void
    {
        $staff = $this->makeUser('Staff', 'staff-x@test.com');
        $patient = $this->makeUser('Patient', 'patient-x@test.com');

        $this->actingAs($staff)->postJson(route('doctor.notifications.read-all'))->assertStatus(403);
        $this->actingAs($patient)->postJson(route('doctor.notifications.read-all'))->assertStatus(403);
    }

    public function test_other_roles_notification_endpoints_still_work(): void
    {
        $this->actingAs($this->makeUser('Admin', 'admin-y@test.com'))
            ->postJson('/admin/notifications/read-all')->assertOk()->assertJson(['success' => true]);
        $this->actingAs($this->makeUser('Staff', 'staff-y@test.com'))
            ->postJson('/staff/notifications/read-all')->assertOk()->assertJson(['success' => true]);
        $this->actingAs($this->makeUser('Patient', 'patient-y@test.com'))
            ->postJson('/patient/notifications/read-all')->assertOk()->assertJson(['success' => true]);
    }

    // ------------------------------------------------------------------
    // Links + rendering
    // ------------------------------------------------------------------

    public function test_notification_links_point_to_correct_pages(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-links@test.com');
        $pat = $this->makeUser('Patient', 'pat-links@test.com');

        $approved = $this->makeAppointment($doc, $pat, ['status' => 'Approved']);
        $checkedIn = $this->makeAppointment($doc, $pat, ['status' => 'Checked In']);
        $pending = $this->makeAppointment($doc, $pat, ['status' => 'Pending']);
        $lab = $this->makeLab($doc, $pat);

        $urls = $this->service()->build($doc)->pluck('url', 'key');

        $findUrl = fn (string $prefix) => $urls->first(fn ($u, $k) => str_starts_with($k, $prefix));

        $this->assertSame(
            route('doctor.appointments.index', ['search' => $approved->reference_no]),
            $findUrl("doctor_appointment_{$approved->id}_Approved_")
        );
        $this->assertSame(route('doctor.appointments.show', $checkedIn->id), $findUrl("doctor_appointment_{$checkedIn->id}_Checked In_"));
        $this->assertSame(route('doctor.appointments.index'), $findUrl("doctor_appointment_{$pending->id}_Pending_"));
        $this->assertSame(route('doctor.laboratory-requests.show', $lab->id), $findUrl("doctor_lab_{$lab->id}_"));
    }

    public function test_doctor_pages_render_bell_with_only_own_data(): void
    {
        $docA = $this->makeUser('Doctor', 'doc-r-a@test.com');
        $docB = $this->makeUser('Doctor', 'doc-r-b@test.com');
        $patA = $this->makeUser('Patient', 'pat-r-a@test.com', ['first_name' => 'Alpha', 'last_name' => 'Visible']);
        $patB = $this->makeUser('Patient', 'pat-r-b@test.com', ['first_name' => 'Bravo', 'last_name' => 'Hidden']);

        $this->makeAppointment($docA, $patA);
        $this->makeAppointment($docB, $patB);
        $this->makeLab($docB, $patB);

        foreach ([route('doctor.dashboard'), route('doctor.appointments.index'), route('doctor.laboratory-requests.index')] as $url) {
            $response = $this->actingAs($docA)->get($url);

            $response->assertOk();
            $response->assertSee('notifMarkAllBtn', false);
            $response->assertSee('data-filter="laboratory"', false);
            $response->assertSee('class="notif-item notif-item--unread"', false);
            $response->assertSee('Alpha Visible');
            $response->assertDontSee('Bravo Hidden');
            $response->assertSee('<span class="notif-badge" id="notifBadge">1</span>', false);
        }
    }

    public function test_badge_disappears_after_mark_all_read_and_reload(): void
    {
        $doc = $this->makeUser('Doctor', 'doc-badge@test.com');
        $pat = $this->makeUser('Patient', 'pat-badge@test.com');
        $this->makeAppointment($doc, $pat);

        $this->actingAs($doc)->get(route('doctor.dashboard'))->assertSee('id="notifBadge"', false);

        $this->actingAs($doc)->postJson(route('doctor.notifications.read-all'))->assertOk()->assertJson(['unread_count' => 0]);

        $this->actingAs($doc)->get(route('doctor.dashboard'))
            ->assertOk()
            ->assertDontSee('id="notifBadge"', false)
            ->assertDontSee('class="notif-item notif-item--unread"', false);
    }
}
