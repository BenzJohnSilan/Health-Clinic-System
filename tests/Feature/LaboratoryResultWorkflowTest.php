<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryRequestTest;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultCorrection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaboratoryResultWorkflowTest extends TestCase
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

    protected function makeLabRequest(User $doctor, User $patient, array $tests): LaboratoryRequest
    {
        $appointment = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00',
            'status'           => 'In Progress',
            'reason'           => 'Checkup',
        ]);

        $labRequest = LaboratoryRequest::create([
            'appointment_id' => $appointment->id,
            'patient_id'     => $patient->id,
            'doctor_id'      => $doctor->id,
            'diagnosis'      => 'Routine',
        ]);

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

    /** TEST 1: dropdown / upload only accepts tests belonging to this request */
    public function test_upload_rejects_test_not_on_the_request(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc1@test.com');
        $patient = $this->makeUser('Patient', 'pat1@test.com');
        $labRequest = $this->makeLabRequest($doctor, $patient, ['CBC', 'Urinalysis']);

        $response = $this->actingAs($patient)->post(
            "/patient/laboratory-results/{$labRequest->id}/upload",
            [
                'test_name'   => 'FBS', // not requested
                'result_file' => UploadedFile::fake()->image('cbc.jpg'),
            ]
        );

        $response->assertSessionHasErrors('test_name');
        $this->assertDatabaseCount('laboratory_results', 0);
    }

    /** TEST 2/3: upload CBC then Urinalysis -> 2 active files, correct statuses */
    public function test_patient_can_upload_results_for_each_requested_test(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc2@test.com');
        $patient = $this->makeUser('Patient', 'pat2@test.com');
        $labRequest = $this->makeLabRequest($doctor, $patient, ['CBC', 'Urinalysis', 'FBS']);

        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name'   => 'CBC',
            'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ])->assertRedirect();

        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name'   => 'Urinalysis',
            'result_file' => UploadedFile::fake()->image('urinalysis.jpg'),
        ])->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Result Received', $labRequest->status);
        $this->assertEquals(2, $labRequest->results()->count());

        $byTest = $labRequest->currentResultsByTest();
        $this->assertEquals('cbc.jpg', $byTest['CBC']->original_filename);
        $this->assertEquals('urinalysis.jpg', $byTest['Urinalysis']->original_filename);
        $this->assertNull($byTest['FBS']);
    }

    /** TEST 4: wrong CBC file -> Replace -> only the correct file is "current" */
    public function test_patient_can_replace_a_wrong_result_and_old_one_is_hidden(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc3@test.com');
        $patient = $this->makeUser('Patient', 'pat3@test.com');
        $labRequest = $this->makeLabRequest($doctor, $patient, ['CBC']);

        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name'   => 'CBC',
            'result_file' => UploadedFile::fake()->image('wrong-cbc.jpg'),
        ]);

        $wrong = LaboratoryResult::first();
        $this->assertNotNull($wrong);

        $this->actingAs($patient)->post(
            "/patient/laboratory-results/{$labRequest->id}/results/{$wrong->id}/replace",
            ['result_file' => UploadedFile::fake()->image('correct-cbc.jpg')]
        )->assertRedirect();

        $labRequest->refresh();

        // Exactly ONE active file remains for CBC, and it's the correct one.
        $this->assertEquals(1, $labRequest->results()->count());
        $current = $labRequest->currentResultsByTest()['CBC'];
        $this->assertEquals('correct-cbc.jpg', $current->original_filename);

        // The wrong file was soft-deleted (kept for audit), not hard-deleted.
        $this->assertSoftDeleted('laboratory_results', ['id' => $wrong->id]);
    }

    /** TEST 5/6: doctor requests correction for CBC specifically; patient sees it + replaces */
    public function test_doctor_correction_request_targets_specific_test_and_patient_can_resolve_it(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc4@test.com');
        $patient = $this->makeUser('Patient', 'pat4@test.com');
        $labRequest = $this->makeLabRequest($doctor, $patient, ['CBC', 'FBS']);

        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);
        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name' => 'FBS', 'result_file' => UploadedFile::fake()->image('fbs.jpg'),
        ]);

        // Doctor: correction WITHOUT a test -> rejected (now required).
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'Bad file']
        )->assertSessionHasErrors('test_name');

        // Doctor: correction for a test NOT on the request -> rejected.
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'Bad file', 'test_name' => 'Urinalysis']
        )->assertSessionHasErrors('test_name');

        // Doctor: correction for CBC -> accepted.
        $this->actingAs($doctor)->post(
            "/doctor/laboratory-requests/{$labRequest->id}/request-correction",
            ['reason' => 'Mali po yung CBC result.', 'test_name' => 'CBC']
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Correction Required', $labRequest->status);

        $pending = $labRequest->pendingCorrection;
        $this->assertNotNull($pending);
        $this->assertEquals('CBC', $pending->test_name);

        // Patient replaces the FBS file (the WRONG one, not what's flagged)
        // -> must NOT resolve the correction or flip status back.
        $fbsResult = $labRequest->currentResultsByTest()['FBS'];
        $this->actingAs($patient)->post(
            "/patient/laboratory-results/{$labRequest->id}/results/{$fbsResult->id}/replace",
            ['result_file' => UploadedFile::fake()->image('fbs-2.jpg')]
        );
        $labRequest->refresh();
        $this->assertEquals('Correction Required', $labRequest->status, 'Replacing an unrelated test must not clear the correction.');

        // Now patient replaces the actual flagged CBC file -> resolves it.
        $cbcResult = $labRequest->currentResultsByTest()['CBC'];
        $this->actingAs($patient)->post(
            "/patient/laboratory-results/{$labRequest->id}/results/{$cbcResult->id}/replace",
            ['result_file' => UploadedFile::fake()->image('correct-cbc.jpg')]
        )->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Result Received', $labRequest->status);
        $this->assertTrue($labRequest->corrections()->first()->isResolved());
    }

    /** TEST 7: once Reviewed, patient cannot replace via UI OR direct POST */
    public function test_reviewed_result_is_locked_against_replace_at_the_backend(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc5@test.com');
        $patient = $this->makeUser('Patient', 'pat5@test.com');
        $labRequest = $this->makeLabRequest($doctor, $patient, ['CBC']);

        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);

        $this->actingAs($doctor)->post("/doctor/laboratory-requests/{$labRequest->id}/review", [])
            ->assertRedirect();

        $labRequest->refresh();
        $this->assertEquals('Reviewed', $labRequest->status);

        $current = $labRequest->currentResultsByTest()['CBC'];

        $response = $this->actingAs($patient)->post(
            "/patient/laboratory-results/{$labRequest->id}/results/{$current->id}/replace",
            ['result_file' => UploadedFile::fake()->image('sneaky.jpg')]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(1, $labRequest->results()->count(), 'No new file should have been created.');
        $this->assertEquals('cbc.jpg', $labRequest->currentResultsByTest()['CBC']->original_filename);
    }

    /** TEST 8: another patient cannot replace / access this request or result */
    public function test_patient_cannot_replace_another_patients_result(): void
    {
        $doctor   = $this->makeUser('Doctor', 'doc6@test.com');
        $patientA = $this->makeUser('Patient', 'pat6a@test.com');
        $patientB = $this->makeUser('Patient', 'pat6b@test.com');
        $labRequest = $this->makeLabRequest($doctor, $patientA, ['CBC']);

        $this->actingAs($patientA)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);

        $result = $labRequest->currentResultsByTest()['CBC'];

        // Patient B tries to replace Patient A's result directly.
        $response = $this->actingAs($patientB)->post(
            "/patient/laboratory-results/{$labRequest->id}/results/{$result->id}/replace",
            ['result_file' => UploadedFile::fake()->image('hijack.jpg')]
        );
        $response->assertStatus(404);

        // Patient B tries to upload against Patient A's request entirely.
        $this->actingAs($patientB)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('hijack2.jpg'),
        ])->assertStatus(404);
    }

    /** Doctor review page and print still work (Section 10/11 regression check) */
    public function test_doctor_review_page_and_print_still_render(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc7@test.com');
        $patient = $this->makeUser('Patient', 'pat7@test.com');
        $labRequest = $this->makeLabRequest($doctor, $patient, ['CBC', 'Urinalysis']);

        $this->actingAs($patient)->post("/patient/laboratory-results/{$labRequest->id}/upload", [
            'test_name' => 'CBC', 'result_file' => UploadedFile::fake()->image('cbc.jpg'),
        ]);

        $this->actingAs($doctor)->get("/doctor/laboratory-requests/{$labRequest->id}")->assertOk();
        $this->actingAs($doctor)->get("/doctor/laboratory-requests/{$labRequest->id}/print")->assertOk();
        $this->actingAs($patient)->get('/patient/laboratory-results')->assertOk();
    }
}
