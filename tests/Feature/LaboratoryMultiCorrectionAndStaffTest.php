<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryRequestTest;
use App\Models\LaboratoryResult;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaboratoryMultiCorrectionAndStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, string $email): User
    {
        return User::forceCreate([
            'first_name'         => $role,
            'last_name'          => 'Test',
            'birthdate'          => '1990-01-01',
            'gender'             => 'Male',
            'civil_status'       => 'Single',
            'address'            => 'Sample St',
            'contact_number'     => '0917' . random_int(1000000, 9999999),
            'username'           => strtolower($role) . random_int(100000, 999999),
            'email'              => $email,
            'password'           => bcrypt('password'),
            'role'               => $role,
            'approval_status'    => 'Approved',
            'email_verified_at'  => now(),
        ]);
    }

    /** A Staff user granted the two laboratory permissions, as Admin would via Manage Permissions. */
    protected function makeAuthorizedStaff(string $email): User
    {
        $staff = $this->makeUser('Staff', $email);

        foreach (['view_laboratory_requests', 'upload_laboratory_result'] as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'module' => 'Laboratory']
            );
            $staff->permissions()->attach($permission->id);
        }

        return $staff;
    }

    protected function makeWalkinPatient(): Patient
    {
        return Patient::create([
            'user_id'         => null,
            'first_name'      => 'Walkin',
            'last_name'       => 'Person',
            'birthdate'       => '1985-05-05',
            'gender'          => 'Female',
            'civil_status'    => 'Single',
            'contact_number'  => '0917' . random_int(1000000, 9999999),
            'address'         => 'Sample St',
        ]);
    }

    protected function makeLabRequestForRegistered(User $doctor, User $patient, array $tests): LaboratoryRequest
    {
        $appointment = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00',
            'status'           => 'In Progress',
            'reason'           => 'Checkup',
        ]);

        return $this->attachTests(LaboratoryRequest::create([
            'appointment_id' => $appointment->id,
            'patient_id'     => $patient->id,
            'doctor_id'      => $doctor->id,
            'diagnosis'      => 'Routine',
        ]), $tests);
    }

    protected function makeLabRequestForWalkin(User $doctor, Patient $walkin, array $tests): LaboratoryRequest
    {
        $appointment = Appointment::create([
            'walkin_patient_id' => $walkin->id,
            'doctor_id'         => $doctor->id,
            'appointment_date'  => now()->toDateString(),
            'appointment_time'  => '09:00',
            'status'            => 'In Progress',
            'reason'            => 'Checkup',
        ]);

        return $this->attachTests(LaboratoryRequest::create([
            'appointment_id'    => $appointment->id,
            'walkin_patient_id' => $walkin->id,
            'doctor_id'         => $doctor->id,
            'diagnosis'         => 'Routine',
        ]), $tests);
    }

    protected function attachTests(LaboratoryRequest $labRequest, array $tests): LaboratoryRequest
    {
        foreach ($tests as $t) {
            LaboratoryRequestTest::create([
                'laboratory_request_id' => $labRequest->id,
                'test_name'             => $t,
            ]);
        }

        return $labRequest->fresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ==================================================================
    // MULTI-TEST CORRECTION (Section 8/14 of the spec)
    // ==================================================================

    /** TEST 6/8/10/11: CBC and FBS can be independently flagged, and independently resolved. */
    public function test_two_tests_can_have_independent_pending_corrections(): void
    {
        $doctor  = $this->makeUser('Doctor', 'd1@test.com');
        $patient = $this->makeUser('Patient', 'p1@test.com');
        $labRequest = $this->makeLabRequestForRegistered($doctor, $patient, ['CBC', 'FBS', 'Urinalysis']);

        foreach (['CBC' => 'cbc.jpg', 'FBS' => 'fbs.jpg', 'Urinalysis' => 'ua.jpg'] as $test => $file) {
            $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
                'test_name' => $test, 'result_file' => UploadedFile::fake()->image($file),
            ]);
        }

        // Doctor flags CBC.
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'Mali po yung CBC result.', 'test_name' => 'CBC']
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Correction Required', $labRequest->status);

        // Doctor ALSO flags FBS while CBC is still pending — must be allowed.
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'Please upload the correct FBS result.', 'test_name' => 'FBS']
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Correction Required', $labRequest->status);

        $pendingByTest = $labRequest->pendingCorrectionsByTest();
        $this->assertNotNull($pendingByTest['CBC']);
        $this->assertNotNull($pendingByTest['FBS']);
        $this->assertNull($pendingByTest['Urinalysis']);

        // Doctor CANNOT flag CBC again while it's already pending (duplicate).
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'Still wrong.', 'test_name' => 'CBC']
        )->assertSessionHas('error');
        $this->assertEquals(2, $labRequest->corrections()->where('status', 'pending')->count());

        // Patient replaces FBS -> only FBS resolves; CBC remains pending, status stays Correction Required.
        $fbsResult = $labRequest->currentResultsByTest()['FBS'];
        $this->actingAs($patient)->post(
            "/patient/laboratory-results/{$labRequest->id}/results/{$fbsResult->id}/replace",
            ['result_file' => UploadedFile::fake()->image('fbs-fixed.jpg')]
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Correction Required', $labRequest->status, 'CBC is still pending, so overall status must stay Correction Required.');
        $pendingByTest = $labRequest->pendingCorrectionsByTest();
        $this->assertNull($pendingByTest['FBS'], 'FBS correction should now be resolved.');
        $this->assertNotNull($pendingByTest['CBC'], 'CBC correction must remain untouched.');

        // Doctor still cannot review yet (CBC still pending).
        $this->actingAs($doctor)->post("/doctor/laboratory-requests/{$labRequest->id}/review", [])
            ->assertSessionHas('error');

        // Now replace CBC too -> fully resolved, status flips to Result Received.
        $cbcResult = $labRequest->currentResultsByTest()['CBC'];
        $this->actingAs($patient)->post(
            "/patient/laboratory-results/{$labRequest->id}/results/{$cbcResult->id}/replace",
            ['result_file' => UploadedFile::fake()->image('cbc-fixed.jpg')]
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Result Received', $labRequest->status);
        $this->assertEquals(0, $labRequest->corrections()->where('status', 'pending')->count());

        // Doctor can review now.
        $this->actingAs($doctor)->post("/doctor/laboratory-requests/{$labRequest->id}/review", [])
            ->assertRedirect();
        $this->assertEquals('Reviewed', $labRequest->fresh()->status);
    }

    /** Doctor cannot request a correction before anything is uploaded, or after Reviewed. */
    public function test_correction_request_blocked_outside_result_received_or_correction_required(): void
    {
        $doctor  = $this->makeUser('Doctor', 'd2@test.com');
        $patient = $this->makeUser('Patient', 'p2@test.com');
        $labRequest = $this->makeLabRequestForRegistered($doctor, $patient, ['CBC']);

        // Awaiting Result — nothing uploaded yet.
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'x', 'test_name' => 'CBC']
        )->assertSessionHas('error');

        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);
        $this->actingAs($doctor)->post("/doctor/laboratory-requests/{$labRequest->id}/review", []);

        // Reviewed — locked.
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'x', 'test_name' => 'CBC']
        )->assertSessionHas('error');
    }

    // ==================================================================
    // STAFF / WALK-IN PATIENT WORKFLOW (Sections 2/3/9/11 of the spec)
    // ==================================================================

    /** TEST 2: Staff upload dropdown only accepts this request's own tests. */
    public function test_staff_upload_rejects_test_not_on_the_request(): void
    {
        $doctor = $this->makeUser('Doctor', 'd3@test.com');
        $staff  = $this->makeAuthorizedStaff('s1@test.com');
        $walkin = $this->makeWalkinPatient();
        $labRequest = $this->makeLabRequestForWalkin($doctor, $walkin, ['CBC', 'Urinalysis']);

        $response = $this->actingAs($staff)->post(
            "/staff/laboratory-requests/{$labRequest->id}/upload",
            ['test_name' => 'FBS', 'result_file' => UploadedFile::fake()->image('x.jpg')]
        );

        $response->assertSessionHasErrors('test_name');
        $this->assertDatabaseCount('laboratory_results', 0);
    }

    /** TEST 4: Staff can upload a result for a Walk-in Patient's specific requested test. */
    public function test_staff_can_upload_result_for_walkin_patient(): void
    {
        $doctor = $this->makeUser('Doctor', 'd4@test.com');
        $staff  = $this->makeAuthorizedStaff('s2@test.com');
        $walkin = $this->makeWalkinPatient();
        $labRequest = $this->makeLabRequestForWalkin($doctor, $walkin, ['CBC', 'Urinalysis']);

        $this->actingAs($staff)->post("/staff/laboratory-requests/{$labRequest->id}/upload", [
            'test_name' => 'Urinalysis', 'result_file' => UploadedFile::fake()->image('ua.jpg'),
        ])->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Result Received', $labRequest->status);
        $current = $labRequest->currentResultsByTest()['Urinalysis'];
        $this->assertEquals('ua.jpg', $current->original_filename);
        $this->assertEquals($staff->id, $current->uploaded_by);
    }

    /** Staff without the permission is rejected (permission-gated, no unlimited Staff access). */
    public function test_staff_without_permission_cannot_upload(): void
    {
        $doctor = $this->makeUser('Doctor', 'd5@test.com');
        $staff  = $this->makeUser('Staff', 's3@test.com'); // no permissions granted
        $walkin = $this->makeWalkinPatient();
        $labRequest = $this->makeLabRequestForWalkin($doctor, $walkin, ['CBC']);

        $this->actingAs($staff)->post("/staff/laboratory-requests/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('x.jpg'),
        ])->assertStatus(403);
    }

    /** TEST 8: Staff can replace a Walk-in Patient's CBC result, same behavior as Patient replacement. */
    public function test_staff_can_replace_walkin_patients_result(): void
    {
        $doctor = $this->makeUser('Doctor', 'd6@test.com');
        $staff  = $this->makeAuthorizedStaff('s4@test.com');
        $walkin = $this->makeWalkinPatient();
        $labRequest = $this->makeLabRequestForWalkin($doctor, $walkin, ['CBC']);

        $this->actingAs($staff)->post("/staff/laboratory-requests/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('wrong-cbc.jpg'),
        ]);

        $wrong = LaboratoryResult::first();

        $this->actingAs($staff)->post(
            "/staff/laboratory-requests/{$labRequest->id}/results/{$wrong->id}/replace",
            ['result_file' => UploadedFile::fake()->image('correct-cbc.jpg')]
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals(1, $labRequest->results()->count());
        $this->assertEquals('correct-cbc.jpg', $labRequest->currentResultsByTest()['CBC']->original_filename);
        $this->assertSoftDeleted('laboratory_results', ['id' => $wrong->id]);
    }

    /**
     * Staff replacement does NOT depend on who originally uploaded the
     * file (Section 11) — a different authorized Staff member can
     * replace a file recorded by a previous Staff member.
     */
    public function test_staff_can_replace_a_result_uploaded_by_a_different_staff_member(): void
    {
        $doctor  = $this->makeUser('Doctor', 'd7@test.com');
        $staffA  = $this->makeAuthorizedStaff('s5@test.com');
        $staffB  = $this->makeAuthorizedStaff('s6@test.com');
        $walkin  = $this->makeWalkinPatient();
        $labRequest = $this->makeLabRequestForWalkin($doctor, $walkin, ['CBC']);

        $this->actingAs($staffA)->post("/staff/laboratory-requests/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);
        $original = LaboratoryResult::first();

        $this->actingAs($staffB)->post(
            "/staff/laboratory-requests/{$labRequest->id}/results/{$original->id}/replace",
            ['result_file' => UploadedFile::fake()->image('cbc-2.jpg')]
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('cbc-2.jpg', $labRequest->currentResultsByTest()['CBC']->original_filename);
    }

    /** TEST 9 (Staff half): once Reviewed, Staff cannot replace — backend rejects it directly. */
    public function test_staff_cannot_replace_reviewed_result(): void
    {
        $doctor = $this->makeUser('Doctor', 'd8@test.com');
        $staff  = $this->makeAuthorizedStaff('s7@test.com');
        $walkin = $this->makeWalkinPatient();
        $labRequest = $this->makeLabRequestForWalkin($doctor, $walkin, ['CBC']);

        $this->actingAs($staff)->post("/staff/laboratory-requests/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);
        $this->actingAs($doctor)->post("/doctor/laboratory-requests/{$labRequest->id}/review", []);

        $current = $labRequest->fresh()->currentResultsByTest()['CBC'];

        $response = $this->actingAs($staff)->post(
            "/staff/laboratory-requests/{$labRequest->id}/results/{$current->id}/replace",
            ['result_file' => UploadedFile::fake()->image('sneaky.jpg')]
        );
        $response->assertSessionHas('error');
        $this->assertEquals(1, $labRequest->results()->count());
    }

    /** TEST 14/15/16/17 regression: staff list page still renders, doctor/patient flows unaffected. */
    public function test_staff_laboratory_list_page_renders_with_correction_highlighted(): void
    {
        $doctor = $this->makeUser('Doctor', 'd9@test.com');
        $staff  = $this->makeAuthorizedStaff('s8@test.com');
        $walkin = $this->makeWalkinPatient();
        $labRequest = $this->makeLabRequestForWalkin($doctor, $walkin, ['CBC', 'Urinalysis']);

        $this->actingAs($staff)->post("/staff/laboratory-requests/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);
        $this->actingAs($staff)->post("/staff/laboratory-requests/{$labRequest->id}/upload", [
            'test_name' => 'Urinalysis', 'result_file' => UploadedFile::fake()->image('ua.jpg'),
        ]);
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'Mali po yung CBC result.', 'test_name' => 'CBC']
        );

        $response = $this->actingAs($staff)->get('/staff/laboratory-requests');
        $response->assertOk();
        $response->assertSee('Correction Needed');
        $response->assertSee('CBC');
    }
}
