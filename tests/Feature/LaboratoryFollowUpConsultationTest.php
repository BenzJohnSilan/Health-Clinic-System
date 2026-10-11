<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorWeeklySchedule;
use App\Models\DoctorWeeklyScheduleSlot;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryRequestTest;
use App\Models\LaboratoryResult;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Laboratory Result Follow-up Consultation — scenarios 1-12 of the spec.
 */
class LaboratoryFollowUpConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    protected function makeUser(string $role, string $email, array $extra = []): User
    {
        $user = User::create(array_merge([
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
        ], $extra));

        // Not all of these are mass-assignable on User.
        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'Approved'])->save();

        return $user;
    }

    /** Registered patient with a complete profile (required for booking). */
    protected function makePatient(string $email): User
    {
        return $this->makeUser('Patient', $email, [
            'blood_type'               => 'O+',
            'allergies'                => 'None',
            'emergency_name'           => 'Kin Test',
            'relationship'             => 'Parent',
            'emergency_contact_number' => '09170000000',
            'emergency_address'        => 'Sample St',
        ]);
    }

    /** Doctor available every day 08:00–17:00. */
    protected function makeDoctor(string $email): User
    {
        $doctor = $this->makeUser('Doctor', $email);

        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $schedule = DoctorWeeklySchedule::create([
                'doctor_id'   => $doctor->id,
                'day_of_week' => $day,
                'status'      => 'Available',
            ]);
            DoctorWeeklyScheduleSlot::create([
                'doctor_weekly_schedule_id' => $schedule->id,
                'start_time'                => '08:00',
                'end_time'                  => '17:00',
            ]);
        }

        return $doctor;
    }

    protected function makeStaff(string $email, array $slugs): User
    {
        $staff = $this->makeUser('Staff', $email);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
            $staff->permissions()->attach($permission->id);
        }
        return $staff;
    }

    protected function makeWalkin(): Patient
    {
        return Patient::create([
            'user_id'        => null,
            'first_name'     => 'Walkin',
            'last_name'      => 'Person',
            'birthdate'      => '1985-05-05',
            'gender'         => 'Female',
            'civil_status'   => 'Single',
            'contact_number' => '0917' . random_int(1000000, 9999999),
            'address'        => 'Sample St',
        ]);
    }

    /**
     * Laboratory request in "Result Received" (one CBC file uploaded),
     * ready for the Doctor to review.
     */
    protected function makeReceivedLab(User $doctor, ?User $patient, ?Patient $walkin = null, array $tests = ['CBC']): LaboratoryRequest
    {
        $appointment = Appointment::create([
            'patient_id'        => $patient?->id,
            'walkin_patient_id' => $walkin?->id,
            'doctor_id'         => $doctor->id,
            'appointment_date'  => now()->subDays(3)->toDateString(),
            'appointment_time'  => '09:00',
            'status'            => 'Completed',
            'reason'            => 'Checkup',
        ]);

        $lab = LaboratoryRequest::create([
            'appointment_id'    => $appointment->id,
            'patient_id'        => $patient?->id,
            'walkin_patient_id' => $walkin?->id,
            'doctor_id'         => $doctor->id,
        ]);

        foreach ($tests as $t) {
            LaboratoryRequestTest::create(['laboratory_request_id' => $lab->id, 'test_name' => $t]);
            LaboratoryResult::create([
                'laboratory_request_id' => $lab->id,
                'test_name'             => $t,
                'file_path'             => 'laboratory_results/' . $t . '.pdf',
                'original_filename'     => $t . '.pdf',
                'file_type'             => 'pdf',
                'file_size'             => 1000,
                'uploaded_by'           => $patient?->id ?? $doctor->id,
                'uploaded_at'           => now(),
            ]);
        }

        $lab->recomputeStatus();

        return $lab->fresh();
    }

    protected function reviewWithFollowUp(User $doctor, LaboratoryRequest $lab): void
    {
        $this->actingAs($doctor)->post(route('doctor.laboratory-requests.review', $lab->id), [
            'review_notes'           => 'Please return for follow-up consultation to discuss the laboratory results.',
            'follow_up_recommended'  => '1',
            'follow_up_instructions' => 'Please schedule a follow-up consultation to discuss your laboratory results.',
        ])->assertSessionHasNoErrors();
    }

    protected function bookingPayload(User $doctor, array $extra = []): array
    {
        return array_merge([
            'appointment_date' => now()->addDay()->toDateString(),
            'appointment_time' => '10:00',
            'doctor_id'        => $doctor->id,
            'reason'           => 'Consultation',
        ], $extra);
    }

    // ------------------------------------------------------------------
    // TEST 1 — normal result, no follow-up
    // ------------------------------------------------------------------

    public function test_1_normal_review_has_no_follow_up_button(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);

        $this->actingAs($doctor)->post(route('doctor.laboratory-requests.review', $lab->id), [
            'review_notes'           => 'Laboratory results reviewed. No further action needed.',
            // Instructions typed but checkbox unchecked -> must be discarded.
            'follow_up_instructions' => 'should be ignored',
        ])->assertSessionHasNoErrors();

        $lab->refresh();
        $this->assertSame('Reviewed', $lab->status);
        $this->assertFalse($lab->follow_up_recommended);
        $this->assertNull($lab->follow_up_instructions);

        $this->actingAs($patient)->get(route('patient.laboratory-results.index'))
            ->assertOk()
            ->assertSee('No further action needed.')
            ->assertDontSee('Book Follow-up Consultation');
    }

    public function test_follow_up_requires_instructions(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);

        $this->actingAs($doctor)->post(route('doctor.laboratory-requests.review', $lab->id), [
            'review_notes'          => 'Return please.',
            'follow_up_recommended' => '1',
        ])->assertSessionHasErrors('follow_up_instructions');

        $this->assertSame('Result Received', $lab->fresh()->status);
    }

    // ------------------------------------------------------------------
    // TEST 2 — follow-up recommended
    // ------------------------------------------------------------------

    public function test_2_follow_up_recommendation_is_saved_and_shown_to_patient(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);

        $this->reviewWithFollowUp($doctor, $lab);

        $lab->refresh();
        $this->assertSame('Reviewed', $lab->status);
        $this->assertTrue($lab->follow_up_recommended);
        $this->assertTrue($lab->needsFollowUpBooking());

        $this->actingAs($patient)->get(route('patient.laboratory-results.index'))
            ->assertOk()
            ->assertSee('Follow-up Consultation Recommended')
            ->assertSee('Please schedule a follow-up consultation to discuss your laboratory results.')
            ->assertSee('Book Follow-up Consultation');

        // Existing notification feed now carries the recommendation.
        $feed = app(\App\Services\PatientNotificationService::class)->build($patient);
        $this->assertTrue($feed->contains(fn ($n) => $n['title'] === 'Follow-up Consultation Recommended'));
    }

    // ------------------------------------------------------------------
    // TEST 3 — patient books follow-up
    // ------------------------------------------------------------------

    public function test_3_patient_books_follow_up_as_pending_and_linked(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);
        $this->reviewWithFollowUp($doctor, $lab);

        // The existing booking page opens in follow-up mode.
        $this->actingAs($patient)
            ->get(route('patient.appointments.index', ['follow_up' => $lab->id]))
            ->assertOk()
            ->assertSee($lab->reference_no)
            ->assertSee('follow_up_laboratory_request_id', false);

        $this->actingAs($patient)
            ->post(route('patient.appointments.store'), $this->bookingPayload($doctor, [
                'follow_up_laboratory_request_id' => $lab->id,
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $appt = Appointment::where('patient_id', $patient->id)->latest('id')->first();
        $this->assertSame('Pending', $appt->status);
        $this->assertSame(Appointment::TYPE_FOLLOW_UP, $appt->appointment_type);
        $this->assertSame($lab->id, $appt->follow_up_laboratory_request_id);

        // Lab result stays Reviewed + recommended; patient sees the appointment status.
        $lab->refresh();
        $this->assertSame('Reviewed', $lab->status);
        $this->assertFalse($lab->needsFollowUpBooking());
        $this->actingAs($patient)->get(route('patient.laboratory-results.index'))
            ->assertSee('Follow-up Consultation Recommended')
            ->assertSee('Status: <strong>Pending</strong>', false)
            ->assertDontSee('Book Follow-up Consultation');

        // Staff approval still required, then shown as Approved.
        $staff = $this->makeStaff('s@t.com', ['view_appointments', 'approve_appointment']);
        $this->actingAs($staff)->post(route('staff.appointments.approve', $appt->id));
        $this->assertSame('Approved', $appt->fresh()->status);
        $this->actingAs($patient)->get(route('patient.laboratory-results.index'))
            ->assertSee('Status: <strong>Approved</strong>', false);

        // A second follow-up for the same result is rejected server-side.
        $this->actingAs($patient)
            ->post(route('patient.appointments.store'), $this->bookingPayload($doctor, [
                'appointment_time'                => '11:00',
                'follow_up_laboratory_request_id' => $lab->id,
            ]))
            ->assertSessionHas('error');
        $this->assertSame(1, Appointment::where('follow_up_laboratory_request_id', $lab->id)->count());
    }

    public function test_patient_cannot_book_follow_up_for_another_patients_lab(): void
    {
        $doctor = $this->makeDoctor('d@t.com');
        $owner  = $this->makePatient('owner@t.com');
        $other  = $this->makePatient('other@t.com');
        $lab    = $this->makeReceivedLab($doctor, $owner);
        $this->reviewWithFollowUp($doctor, $lab);

        $this->actingAs($other)
            ->post(route('patient.appointments.store'), $this->bookingPayload($doctor, [
                'follow_up_laboratory_request_id' => $lab->id,
            ]))
            ->assertSessionHas('error');

        $this->assertSame(0, Appointment::where('follow_up_laboratory_request_id', $lab->id)->count());
    }

    public function test_patient_cannot_book_follow_up_when_none_recommended(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);
        $this->actingAs($doctor)->post(route('doctor.laboratory-requests.review', $lab->id), ['review_notes' => 'Normal.']);

        $this->actingAs($patient)
            ->post(route('patient.appointments.store'), $this->bookingPayload($doctor, [
                'follow_up_laboratory_request_id' => $lab->id,
            ]))
            ->assertSessionHas('error');

        $this->assertSame(0, Appointment::count() - 1); // only the original consultation exists
    }

    public function test_follow_up_booking_still_enforces_doctor_availability(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);
        $this->reviewWithFollowUp($doctor, $lab);

        // 18:00 is outside the 08:00–17:00 schedule.
        $this->actingAs($patient)
            ->post(route('patient.appointments.store'), $this->bookingPayload($doctor, [
                'appointment_time'                => '18:00',
                'follow_up_laboratory_request_id' => $lab->id,
            ]))
            ->assertSessionHas('error');

        $this->assertSame(0, Appointment::where('follow_up_laboratory_request_id', $lab->id)->count());
    }

    public function test_follow_up_must_be_with_reviewing_doctor(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $doctor2 = $this->makeDoctor('d2@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);
        $this->reviewWithFollowUp($doctor, $lab);

        $this->actingAs($patient)
            ->post(route('patient.appointments.store'), $this->bookingPayload($doctor2, [
                'follow_up_laboratory_request_id' => $lab->id,
            ]))
            ->assertSessionHas('error');

        $this->assertSame(0, Appointment::where('follow_up_laboratory_request_id', $lab->id)->count());
    }

    // ------------------------------------------------------------------
    // TEST 4 — patient uses normal Book Appointment
    // ------------------------------------------------------------------

    public function test_4_normal_booking_is_regular_and_prompt_is_offered(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);
        $this->reviewWithFollowUp($doctor, $lab);

        // Booking page offers the choice.
        $this->actingAs($patient)->get(route('patient.appointments.index'))
            ->assertOk()
            ->assertSee('You have a follow-up consultation recommended by your doctor for a laboratory result.')
            ->assertSee('Continue as Regular Consultation');

        // Normal submit (no follow-up field) -> Regular, NOT linked.
        $this->actingAs($patient)
            ->post(route('patient.appointments.store'), $this->bookingPayload($doctor))
            ->assertSessionHasNoErrors();

        $appt = Appointment::where('patient_id', $patient->id)->latest('id')->first();
        $this->assertSame('Pending', $appt->status);
        $this->assertSame(Appointment::TYPE_REGULAR, $appt->appointment_type);
        $this->assertNull($appt->follow_up_laboratory_request_id);

        // The recommendation is still outstanding.
        $this->assertTrue($lab->fresh()->needsFollowUpBooking());
    }

    // ------------------------------------------------------------------
    // TEST 5 / 6 — walk-in follow-up via Staff, then check-in
    // ------------------------------------------------------------------

    public function test_5_and_6_staff_creates_walkin_follow_up_approved_and_checks_in(): void
    {
        $doctor = $this->makeDoctor('d@t.com');
        $walkin = $this->makeWalkin();
        $lab    = $this->makeReceivedLab($doctor, null, $walkin);
        $this->reviewWithFollowUp($doctor, $lab);

        $staff = $this->makeStaff('s@t.com', [
            'view_patients', 'schedule_patient_appointment', 'view_appointments',
            'check_in_appointment', 'view_laboratory_requests',
        ]);

        // Staff sees the recommendation on the Patients list and the Lab page.
        $this->actingAs($staff)->get(route('staff.patients.index'))
            ->assertOk()->assertSee('Follow-up recommended')->assertSee('Schedule Follow-up');
        $this->actingAs($staff)->get(route('staff.laboratory-requests.index'))
            ->assertOk()->assertSee('Follow-up recommended')->assertSee('Schedule Follow-up');
        $this->actingAs($staff)->get(route('staff.patients.index', ['follow_up' => $lab->id]))
            ->assertOk();

        $this->actingAs($staff)->post(route('staff.appointments.store'), [
            'patient_id'                      => $walkin->id,
            'patient_type'                    => 'patient',
            'doctor_id'                       => $doctor->id,
            'appointment_date'                => now()->toDateString(),
            'appointment_time'                => '16:30',
            'reason'                          => 'Follow-up',
            'follow_up_laboratory_request_id' => $lab->id,
        ])->assertSessionHasNoErrors();

        $appt = Appointment::where('follow_up_laboratory_request_id', $lab->id)->first();
        $this->assertNotNull($appt);
        $this->assertSame('Approved', $appt->status);
        $this->assertSame(Appointment::TYPE_FOLLOW_UP, $appt->appointment_type);
        $this->assertSame($walkin->id, $appt->walkin_patient_id);
        $this->assertNull($appt->patient_id);

        // TEST 6 — normal check-in.
        $this->actingAs($staff)->post(route('staff.appointments.checkIn', $appt->id));
        $this->assertSame('Checked In', $appt->fresh()->status);
    }

    public function test_staff_cannot_link_follow_up_to_a_different_patient(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $walkin  = $this->makeWalkin();
        $walkin2 = $this->makeWalkin();
        $lab     = $this->makeReceivedLab($doctor, null, $walkin);
        $this->reviewWithFollowUp($doctor, $lab);

        $staff = $this->makeStaff('s@t.com', ['schedule_patient_appointment']);

        $this->actingAs($staff)->post(route('staff.appointments.store'), [
            '_form'                           => 'appointment',
            'patient_id'                      => $walkin2->id,
            'patient_type'                    => 'patient',
            'doctor_id'                       => $doctor->id,
            'appointment_date'                => now()->toDateString(),
            'appointment_time'                => '16:30',
            'reason'                          => 'Follow-up',
            'follow_up_laboratory_request_id' => $lab->id,
        ])->assertSessionHasErrors('follow_up_laboratory_request_id');

        $this->assertSame(0, Appointment::where('follow_up_laboratory_request_id', $lab->id)->count());
    }

    public function test_staff_regular_appointment_unchanged(): void
    {
        $doctor = $this->makeDoctor('d@t.com');
        $walkin = $this->makeWalkin();
        $staff  = $this->makeStaff('s@t.com', ['schedule_patient_appointment']);

        $this->actingAs($staff)->post(route('staff.appointments.store'), [
            'patient_id'       => $walkin->id,
            'patient_type'     => 'patient',
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '16:30',
            'reason'           => 'Fever',
        ])->assertSessionHasNoErrors();

        $appt = Appointment::latest('id')->first();
        $this->assertSame('Approved', $appt->status);
        $this->assertSame(Appointment::TYPE_REGULAR, $appt->appointment_type);
        $this->assertNull($appt->follow_up_laboratory_request_id);
    }

    // ------------------------------------------------------------------
    // TEST 7 / 8 — doctor opens and completes follow-up
    // ------------------------------------------------------------------

    public function test_7_and_8_doctor_sees_follow_up_and_completes_normally(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);
        $this->reviewWithFollowUp($doctor, $lab);

        $appt = Appointment::create([
            'patient_id'                      => $patient->id,
            'doctor_id'                       => $doctor->id,
            'appointment_date'                => now()->toDateString(),
            'appointment_time'                => '15:00',
            'status'                          => 'Checked In',
            'reason'                          => 'Follow-up',
            'appointment_type'                => Appointment::TYPE_FOLLOW_UP,
            'follow_up_laboratory_request_id' => $lab->id,
        ]);
        $regular = Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '15:30',
            'status'           => 'Approved',
            'reason'           => 'Other concern',
        ]);

        // Appointment list distinguishes the two.
        $this->actingAs($doctor)->get(route('doctor.appointments.index'))
            ->assertOk()->assertSee('🔵 Follow-up')->assertSee('Regular Consultation');

        // Consultation page shows the indicator + related result link.
        $this->actingAs($doctor)->get(route('doctor.appointments.show', $appt->id))
            ->assertOk()
            ->assertSee('FOLLOW-UP CONSULTATION')
            ->assertSee($lab->reference_no)
            ->assertSee(route('doctor.laboratory-requests.show', $lab->id), false);

        $this->actingAs($doctor)->get(route('doctor.laboratory-requests.show', $lab->id))
            ->assertOk()->assertSee('Follow-up Appointments')->assertSee($appt->reference_no);

        // Existing consultation workflow.
        $this->actingAs($doctor)->post(route('doctor.appointments.startConsultation', $appt->id));
        $this->assertSame('In Progress', $appt->fresh()->status);

        $this->actingAs($doctor)->patch(route('doctor.appointments.completeConsultation', $appt->id), [
            'chief_complaint' => 'Discuss labs',
            'diagnosis'       => 'Stable',
            'treatment'       => 'Continue',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Completed', $appt->fresh()->status);

        // Original laboratory result stays in history, unchanged.
        $lab->refresh();
        $this->assertSame('Reviewed', $lab->status);
        $this->assertTrue($lab->follow_up_recommended);
        $this->assertFalse($lab->needsFollowUpBooking()); // completed follow-up satisfies it
        $this->actingAs($patient)->get(route('patient.laboratory-results.index'))
            ->assertSee($lab->reference_no)
            ->assertSee('Status: <strong>Completed</strong>', false);

        $this->assertSame('Approved', $regular->fresh()->status);
    }

    public function test_other_doctor_cannot_open_unrelated_lab_result(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $doctor2 = $this->makeDoctor('d2@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);

        $this->actingAs($doctor2)->get(route('doctor.laboratory-requests.show', $lab->id))->assertNotFound();
        $this->actingAs($doctor2)->post(route('doctor.laboratory-requests.review', $lab->id), ['review_notes' => 'x'])
            ->assertNotFound();
    }

    // ------------------------------------------------------------------
    // TEST 9-12 — existing laboratory rules still hold
    // ------------------------------------------------------------------

    public function test_9_patient_cannot_replace_after_review(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient);
        $this->reviewWithFollowUp($doctor, $lab);

        $result = $lab->results()->first();

        $this->actingAs($patient)->post(route('patient.laboratory-results.replace', [$lab->id, $result->id]), [
            'result_file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('error');

        $this->actingAs($patient)->post(route('patient.laboratory-results.upload', $lab->id), [
            'test_name'   => 'CBC',
            'result_file' => UploadedFile::fake()->create('new.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('error');

        $this->assertSame(1, $lab->results()->count());
        $this->actingAs($patient)->get(route('patient.laboratory-results.index'))
            ->assertDontSee('openReplaceModal(' . $lab->id, false);
    }

    public function test_10_upload_for_test_not_on_request_is_rejected(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient, null, ['CBC']);

        $this->actingAs($patient)->post(route('patient.laboratory-results.upload', $lab->id), [
            'test_name'   => 'FBS',
            'result_file' => UploadedFile::fake()->create('fbs.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('test_name');
    }

    public function test_11_and_12_cbc_correction_is_isolated_and_reason_hidden_from_patient(): void
    {
        $doctor  = $this->makeDoctor('d@t.com');
        $patient = $this->makePatient('p@t.com');
        $lab     = $this->makeReceivedLab($doctor, $patient, null, ['CBC', 'FBS']);

        $this->actingAs($doctor)->post(route('doctor.laboratory-requests.request-correction', $lab->id), [
            'test_name' => 'CBC', 'reason' => 'INTERNAL-REASON-CBC-blurry',
        ]);
        $this->actingAs($doctor)->post(route('doctor.laboratory-requests.request-correction', $lab->id), [
            'test_name' => 'FBS', 'reason' => 'INTERNAL-REASON-FBS',
        ]);

        $lab->refresh()->load(['tests', 'corrections']);
        $this->assertSame('Correction Required', $lab->status);
        $byTest = $lab->pendingCorrectionsByTest();
        $this->assertNotNull($byTest['CBC']);
        $this->assertNotNull($byTest['FBS']);

        // Patient sees "needs correction" but NOT the internal reason.
        $this->actingAs($patient)->get(route('patient.laboratory-results.index'))
            ->assertSee('This result needs correction')
            ->assertDontSee('INTERNAL-REASON-CBC-blurry');

        // Replace CBC only -> only CBC resolved.
        $cbc = $lab->results()->where('test_name', 'CBC')->first();
        $this->actingAs($patient)->post(route('patient.laboratory-results.replace', [$lab->id, $cbc->id]), [
            'result_file' => UploadedFile::fake()->create('cbc2.pdf', 100, 'application/pdf'),
        ]);

        $lab->refresh()->load(['tests', 'corrections']);
        $byTest = $lab->pendingCorrectionsByTest();
        $this->assertNull($byTest['CBC']);
        $this->assertNotNull($byTest['FBS']);
        $this->assertSame('Correction Required', $lab->status);

        // Replace FBS -> Result Received, doctor can review again.
        $fbs = $lab->results()->where('test_name', 'FBS')->first();
        $this->actingAs($patient)->post(route('patient.laboratory-results.replace', [$lab->id, $fbs->id]), [
            'result_file' => UploadedFile::fake()->create('fbs2.pdf', 100, 'application/pdf'),
        ]);
        $this->assertSame('Result Received', $lab->fresh()->status);

        $this->reviewWithFollowUp($doctor, $lab->fresh());
        $this->assertSame('Reviewed', $lab->fresh()->status);
    }
}
