<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\LaboratoryRequest;
use App\Models\LaboratoryRequestTest;
use App\Models\LaboratoryResult;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patient flow: Appointment History -> View -> Consultation Summary
 *               -> View Prescription (standalone page, new tab).
 *
 * Covers ownership, the Completed-only rule, the prescription link, the
 * legacy Medical Report URL redirect, and what the patient must NOT see.
 */
class PatientConsultationSummaryTest extends TestCase
{
    use RefreshDatabase;

    // Text the Patient must never see (Doctor/Staff clinical record only).
    private const HPI_TEXT = 'HPI-SECRET-three-days-of-cough';
    private const PE_TEXT  = 'PE-SECRET-clear-breath-sounds';

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    protected function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'first_name'        => $role,
            'last_name'         => 'Test',
            'birthdate'         => '1990-01-01',
            'gender'            => 'Male',
            'civil_status'      => 'Single',
            'address'           => 'Sample St',
            'contact_number'    => '0917' . random_int(1000000, 9999999),
            'username'          => strtolower($role) . random_int(100000, 999999),
            'email'             => $email,
            'password'          => bcrypt('password'),
            'role'              => $role,
        ]);

        // Not all of these are mass-assignable on User.
        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'Approved'])->save();

        return $user;
    }

    /** Appointments are unique per (doctor, date, time), so every fixture gets its own slot. */
    private int $slot = 0;

    protected function makeAppointment(User $patient, User $doctor, string $status = 'Completed'): Appointment
    {
        return Appointment::create([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->subDays(2)->toDateString(),
            'appointment_time' => now()->startOfDay()->setTime(8, 0)->addMinutes(30 * $this->slot++)->format('H:i'),
            'status'           => $status,
            'reason'           => 'Persistent cough',
        ]);
    }

    protected function addMedicalRecord(Appointment $appointment, array $extra = []): MedicalRecord
    {
        return MedicalRecord::create(array_merge([
            'patient_id'                 => $appointment->patient_id,
            'doctor_id'                  => $appointment->doctor_id,
            'appointment_id'             => $appointment->id,
            'chief_complaint'            => 'Cough for three days',
            'history_of_present_illness' => self::HPI_TEXT,
            'physical_examination'       => self::PE_TEXT,
            'diagnosis'                  => 'Acute bronchitis',
            'treatment'                  => 'Rest and plenty of fluids',
            'notes'                      => 'Avoid cold drinks',
            'blood_pressure'             => '120/80',
            'temperature'                => '36.7',
        ], $extra));
    }

    protected function addPrescription(Appointment $appointment, string $name = 'Paracetamol'): Prescription
    {
        return Prescription::create([
            'appointment_id'       => $appointment->id,
            'manual_medicine_name' => $name,
            'dosage'               => '500mg',
            'frequency'            => 'Every 6 hours',
            'duration'             => '5 days',
            'quantity_prescribed'  => 20,
        ]);
    }

    protected function addLab(Appointment $appointment, array $extra = [], bool $withResult = true): LaboratoryRequest
    {
        $lab = LaboratoryRequest::create(array_merge([
            'appointment_id' => $appointment->id,
            'patient_id'     => $appointment->patient_id,
            'doctor_id'      => $appointment->doctor_id,
        ], $extra));

        LaboratoryRequestTest::create(['laboratory_request_id' => $lab->id, 'test_name' => 'CBC']);

        if ($withResult) {
            LaboratoryResult::create([
                'laboratory_request_id' => $lab->id,
                'test_name'             => 'CBC',
                'file_path'             => 'laboratory_results/cbc-result.pdf',
                'original_filename'     => 'cbc-result.pdf',
                'file_type'             => 'pdf',
                'file_size'             => 1000,
                'uploaded_by'           => $appointment->patient_id,
                'uploaded_at'           => now(),
            ]);
        }

        return $lab->fresh();
    }

    protected function summaryUrl(Appointment $appointment): string
    {
        return route('patient.appointment.show', $appointment->id);
    }

    // ------------------------------------------------------------------
    // Consultation Summary — own Completed appointment
    // ------------------------------------------------------------------

    public function test_patient_can_view_own_completed_appointment_summary(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $this->addMedicalRecord($appointment);
        $this->addPrescription($appointment);
        Review::create([
            'appointment_id'   => $appointment->id,
            'next_review_date' => now()->addWeek()->toDateString(),
            'message'          => 'Return if fever persists',
        ]);

        $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertOk()
            ->assertSee('Consultation Summary')
            ->assertSee($appointment->reference_no)
            ->assertSee('Dr. Doctor Test')
            ->assertSee('Completed')
            ->assertSee('Persistent cough')          // reason for visit
            ->assertSee('Cough for three days')      // chief complaint
            ->assertSee('Acute bronchitis')          // diagnosis
            ->assertSee('Rest and plenty of fluids') // treatment
            ->assertSee('Avoid cold drinks')         // notes
            ->assertSee('120/80 mmHg')               // vitals carry their unit
            ->assertSee('36.7 °C')
            ->assertSee('Return if fever persists')  // follow-up instructions
            ->assertSee('Back to Appointment History')
            ->assertSee(route('patient.appointment.history'), false);
    }

    public function test_summary_never_shows_history_of_present_illness_or_physical_exam(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $this->addMedicalRecord($appointment);

        $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertOk()
            ->assertDontSee(self::HPI_TEXT)
            ->assertDontSee(self::PE_TEXT);
    }

    public function test_summary_omits_sections_that_have_no_data(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);   // no medical record at all

        $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertOk()
            ->assertSee('Appointment Details')
            ->assertDontSee('Chief Complaint')
            ->assertDontSee('Vital Signs')
            ->assertDontSee('Assessment / Diagnosis')
            ->assertDontSee('Treatment / Plan')
            ->assertDontSee('View Prescription')
            // "Laboratory" alone also matches the sidebar link, so check the section's own content.
            ->assertDontSee('Requested Test')
            ->assertDontSee('Open Laboratory Results')
            ->assertDontSee('Follow-up Instructions');
    }

    // ------------------------------------------------------------------
    // Security — ownership + Completed-only
    // ------------------------------------------------------------------

    public function test_patient_cannot_view_another_patients_appointment(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patientA    = $this->makeUser('Patient', 'a@t.com');
        $patientB    = $this->makeUser('Patient', 'b@t.com');
        $appointment = $this->makeAppointment($patientA, $doctor);
        $this->addMedicalRecord($appointment, ['diagnosis' => 'PATIENT-A-PRIVATE-DIAGNOSIS']);

        $this->actingAs($patientB)->get($this->summaryUrl($appointment))
            ->assertNotFound()
            ->assertDontSee('PATIENT-A-PRIVATE-DIAGNOSIS');
    }

    public function test_patient_cannot_view_an_in_progress_appointment_summary(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor, 'In Progress');
        $this->addMedicalRecord($appointment, ['diagnosis' => 'DRAFT-DIAGNOSIS-NOT-FINAL']);

        $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertNotFound()
            ->assertDontSee('DRAFT-DIAGNOSIS-NOT-FINAL');
    }

    public function test_only_completed_status_is_viewable(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc@t.com');
        $patient = $this->makeUser('Patient', 'pat@t.com');

        foreach (['Pending', 'Approved', 'Rescheduled', 'Checked In', 'In Progress', 'Cancelled', 'Rejected', 'No Show'] as $status) {
            $appointment = $this->makeAppointment($patient, $doctor, $status);

            $this->actingAs($patient)->get($this->summaryUrl($appointment))
                ->assertNotFound("Status '{$status}' must not open a Consultation Summary.");
        }
    }

    public function test_guest_and_non_patient_roles_cannot_open_the_summary(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);

        $this->get($this->summaryUrl($appointment))->assertRedirect(route('login'));

        $this->actingAs($doctor)->get($this->summaryUrl($appointment))->assertForbidden();
    }

    public function test_non_numeric_id_does_not_match_the_summary_route(): void
    {
        $patient = $this->makeUser('Patient', 'pat@t.com');

        $this->actingAs($patient)->get('/patient/appointment-history/abc')->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Prescription link (Consultation Summary) + standalone page
    // ------------------------------------------------------------------

    public function test_prescription_link_is_shown_only_when_the_appointment_has_a_prescription(): void
    {
        $doctor  = $this->makeUser('Doctor', 'doc@t.com');
        $patient = $this->makeUser('Patient', 'pat@t.com');

        $with    = $this->makeAppointment($patient, $doctor);
        $without = $this->makeAppointment($patient, $doctor);
        $this->addPrescription($with);

        $rxUrl = fn (Appointment $a) => route('patient.prescription.show', $a->id);

        // With a prescription: button exists and opens in a NEW TAB, safely.
        $this->actingAs($patient)->get($this->summaryUrl($with))
            ->assertOk()
            ->assertSee('View Prescription')
            ->assertSee('href="' . $rxUrl($with) . '"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);

        // Without one: no Prescription section and no link at all.
        $this->actingAs($patient)->get($this->summaryUrl($without))
            ->assertOk()
            ->assertDontSee('View Prescription')
            ->assertDontSee($rxUrl($without), false);
    }

    public function test_prescription_page_is_standalone_with_close_and_print_buttons(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $this->addPrescription($appointment, 'Amoxicillin');

        $response = $this->actingAs($patient)
            ->get(route('patient.prescription.show', $appointment->id))
            ->assertOk()
            ->assertSee('Amoxicillin')                 // existing prescription data preserved
            ->assertSee('Print Prescription')
            ->assertSee('Close')
            ->assertDontSee('>Back<', false)           // old Back button is gone
            // No Patient sidebar / header / dashboard layout:
            ->assertDontSee('Patient Panel')
            ->assertDontSee('id="sidebar"', false)
            ->assertDontSee('admin-header', false)
            ->assertDontSee('patient-layout.css', false);

        $this->assertStringContainsString('<!DOCTYPE html>', $response->getContent());
    }

    public function test_prescription_page_autoprint_behaviour_is_preserved(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $this->addPrescription($appointment);

        $this->actingAs($patient)->get(route('patient.prescription.show', $appointment->id))
            ->assertOk()->assertDontSee('window.print();', false);

        $this->actingAs($patient)->get(route('patient.prescription.show', ['id' => $appointment->id, 'autoprint' => 1]))
            ->assertOk()->assertSee('window.print();', false);
    }

    public function test_prescription_page_is_blocked_for_in_progress_and_for_other_patients(): void
    {
        $doctor   = $this->makeUser('Doctor', 'doc@t.com');
        $patientA = $this->makeUser('Patient', 'a@t.com');
        $patientB = $this->makeUser('Patient', 'b@t.com');

        $draft     = $this->makeAppointment($patientA, $doctor, 'In Progress');
        $completed = $this->makeAppointment($patientA, $doctor);
        $this->addPrescription($draft, 'DRAFT-MEDICINE');
        $this->addPrescription($completed, 'FINAL-MEDICINE');

        // Own appointment, but still In Progress -> draft prescription hidden.
        $this->actingAs($patientA)->get(route('patient.prescription.show', $draft->id))
            ->assertNotFound()
            ->assertDontSee('DRAFT-MEDICINE');

        // Someone else's Completed prescription.
        $this->actingAs($patientB)->get(route('patient.prescription.show', $completed->id))
            ->assertNotFound()
            ->assertDontSee('FINAL-MEDICINE');

        // The rightful owner still gets it.
        $this->actingAs($patientA)->get(route('patient.prescription.show', $completed->id))
            ->assertOk()->assertSee('FINAL-MEDICINE');
    }

    // ------------------------------------------------------------------
    // Old Medical Report URL
    // ------------------------------------------------------------------

    public function test_old_medical_report_url_redirects_to_the_consultation_summary(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $this->addMedicalRecord($appointment);

        $this->actingAs($patient)->get(route('patient.medical-report.show', $appointment->id))
            ->assertRedirect($this->summaryUrl($appointment));

        // ...and the redirect lands on the real summary.
        $this->actingAs($patient)->followingRedirects()
            ->get(route('patient.medical-report.show', $appointment->id))
            ->assertOk()
            ->assertSee('Consultation Summary');
    }

    public function test_old_medical_report_url_does_not_leak_other_patients_data(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patientA    = $this->makeUser('Patient', 'a@t.com');
        $patientB    = $this->makeUser('Patient', 'b@t.com');
        $appointment = $this->makeAppointment($patientA, $doctor);
        $this->addMedicalRecord($appointment, ['diagnosis' => 'PATIENT-A-PRIVATE-DIAGNOSIS']);

        $this->actingAs($patientB)->followingRedirects()
            ->get(route('patient.medical-report.show', $appointment->id))
            ->assertNotFound()
            ->assertDontSee('PATIENT-A-PRIVATE-DIAGNOSIS');
    }

    // ------------------------------------------------------------------
    // Printable report that is still kept (Print Summary button)
    // ------------------------------------------------------------------

    public function test_print_summary_is_linked_and_hides_hpi_and_physical_exam(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $this->addMedicalRecord($appointment);

        $printUrl = route('patient.medical-report.print', ['id' => $appointment->id, 'autoprint' => 1]);

        $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertOk()->assertSee('Print Summary')->assertSee(e($printUrl), false);

        $this->actingAs($patient)->get(route('patient.medical-report.print', $appointment->id))
            ->assertOk()
            ->assertSee('Acute bronchitis')
            ->assertSee('Avoid cold drinks')
            ->assertDontSee(self::HPI_TEXT)
            ->assertDontSee(self::PE_TEXT);
    }

    public function test_print_report_is_blocked_for_in_progress_and_for_other_patients(): void
    {
        $doctor   = $this->makeUser('Doctor', 'doc@t.com');
        $patientA = $this->makeUser('Patient', 'a@t.com');
        $patientB = $this->makeUser('Patient', 'b@t.com');

        $draft     = $this->makeAppointment($patientA, $doctor, 'In Progress');
        $completed = $this->makeAppointment($patientA, $doctor);

        $this->actingAs($patientA)->get(route('patient.medical-report.print', $draft->id))->assertNotFound();
        $this->actingAs($patientB)->get(route('patient.medical-report.print', $completed->id))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Laboratory section
    // ------------------------------------------------------------------

    public function test_laboratory_section_shows_tests_status_and_result_link(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $lab         = $this->addLab($appointment);
        $lab->recomputeStatus();   // a result is on file -> Result Received

        $resultUrl = LaboratoryResult::where('laboratory_request_id', $lab->id)->first()->url;

        $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertOk()
            ->assertSee('Laboratory')
            ->assertSee($lab->reference_no)
            ->assertSee('CBC')
            ->assertSee('Result Received')
            ->assertSee('href="' . $resultUrl . '"', false)
            ->assertSee('View result');
    }

    public function test_laboratory_awaiting_result_shows_status_and_no_fake_result(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);
        $this->addLab($appointment, [], false);

        $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertOk()
            ->assertSee('Awaiting Result')
            ->assertSee('No result uploaded yet')
            ->assertDontSee('View result');
    }

    public function test_laboratory_review_notes_and_follow_up_only_appear_once_reviewed(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $notReviewed = $this->makeAppointment($patient, $doctor);
        $reviewed    = $this->makeAppointment($patient, $doctor);

        $fields = [
            'review_notes'           => 'REVIEW-NOTE-TEXT',
            'follow_up_recommended'  => true,
            'follow_up_instructions' => 'FOLLOW-UP-INSTRUCTION-TEXT',
        ];

        // Same stored text, but the request is NOT Reviewed yet -> hidden.
        $labA = $this->addLab($notReviewed, $fields);
        $labA->recomputeStatus();
        $this->assertNotSame('Reviewed', $labA->fresh()->status);

        $this->actingAs($patient)->get($this->summaryUrl($notReviewed))
            ->assertOk()
            ->assertDontSee('REVIEW-NOTE-TEXT')
            ->assertDontSee('FOLLOW-UP-INSTRUCTION-TEXT');

        // Reviewed -> visible.
        $this->addLab($reviewed, $fields + [
            'status'      => 'Reviewed',
            'reviewed_by' => $doctor->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($patient)->get($this->summaryUrl($reviewed))
            ->assertOk()
            ->assertSee('Reviewed')
            ->assertSee('REVIEW-NOTE-TEXT')
            ->assertSee('FOLLOW-UP-INSTRUCTION-TEXT');
    }

    // ------------------------------------------------------------------
    // Appointment History + sidebar
    // ------------------------------------------------------------------

    public function test_appointment_history_shows_view_button_pointing_to_the_summary(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);

        $this->actingAs($patient)->get(route('patient.appointment.history'))
            ->assertOk()
            ->assertSee('href="' . $this->summaryUrl($appointment) . '"', false)
            ->assertSee('View')
            ->assertDontSee('Report')
            ->assertDontSee(route('patient.medical-report.show', $appointment->id), false);
    }

    public function test_appointment_history_offers_no_view_for_non_completed_rows(): void
    {
        $doctor    = $this->makeUser('Doctor', 'doc@t.com');
        $patient   = $this->makeUser('Patient', 'pat@t.com');
        $cancelled = $this->makeAppointment($patient, $doctor, 'Cancelled');

        $this->actingAs($patient)->get(route('patient.appointment.history'))
            ->assertOk()
            ->assertSee('Not Available')
            ->assertDontSee($this->summaryUrl($cancelled), false);
    }

    public function test_sidebar_has_no_medical_report_and_keeps_appointment_history_active(): void
    {
        $doctor      = $this->makeUser('Doctor', 'doc@t.com');
        $patient     = $this->makeUser('Patient', 'pat@t.com');
        $appointment = $this->makeAppointment($patient, $doctor);

        $response = $this->actingAs($patient)->get($this->summaryUrl($appointment))
            ->assertOk()
            ->assertSee('Appointment History')
            ->assertDontSee('Medical Report');

        // "Appointment History" link carries the `active` class on the summary page.
        $this->assertMatchesRegularExpression(
            '~href="[^"]*/patient/appointment-history"\s+class="active"~',
            $response->getContent()
        );
    }
}
