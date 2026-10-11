<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Vital Signs are recorded by STAFF on the appointment's existing
 * MedicalRecord row; the Doctor only views them.
 */
class StaffVitalSignsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // ---------------- fixtures ----------------

    protected function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'first_name' => $role, 'last_name' => 'Test', 'birthdate' => '1990-01-01',
            'gender' => 'Male', 'civil_status' => 'Single', 'address' => 'Sample St',
            'contact_number' => '0917' . random_int(1000000, 9999999),
            'username' => strtolower($role) . random_int(100000, 999999),
            'email' => $email, 'password' => bcrypt('password'), 'role' => $role,
        ]);
        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'Approved'])->save();
        return $user;
    }

    protected function staff(array $slugs = ['view_appointments', 'view_appointment_details', 'record_vital_signs', 'check_in_appointment']): User
    {
        $staff = $this->makeUser('Staff', 's' . random_int(1, 99999) . '@t.com');
        foreach ($slugs as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
            $staff->permissions()->attach($p->id);
        }
        return $staff;
    }

    protected function walkin(string $first): Patient
    {
        return Patient::create([
            'user_id' => null, 'first_name' => $first, 'last_name' => 'Person',
            'birthdate' => '1985-05-05', 'gender' => 'Female', 'civil_status' => 'Single',
            'contact_number' => '0917' . random_int(1000000, 9999999), 'address' => 'Sample St',
        ]);
    }

    protected function appt(User $doctor, Patient $p, string $time, string $status = 'Approved'): Appointment
    {
        return Appointment::create([
            'walkin_patient_id' => $p->id, 'doctor_id' => $doctor->id,
            'appointment_date' => now()->toDateString(), 'appointment_time' => $time,
            'status' => $status, 'reason' => 'Checkup',
            'reference_no' => 'APT-' . random_int(100000, 999999),
        ]);
    }

    protected function fullVitals(): array
    {
        return ['vital' => [
            'blood_pressure' => '120/80', 'temperature' => '36.7', 'pulse_rate' => '78',
            'respiratory_rate' => '18', 'oxygen_saturation' => '98', 'weight' => '65', 'height' => '170',
        ]];
    }

    protected function save(User $staff, Appointment $a, array $payload)
    {
        return $this->actingAs($staff)->post(route('staff.appointments.vitalSigns.save', $a->id), $payload);
    }

    // ---------------- STAFF ----------------

    public function test_staff_details_page_shows_vital_signs_form_with_fixed_units(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');

        $res = $this->actingAs($this->staff())->get(route('staff.appointments.show', $a->id));
        $res->assertOk()->assertSee('Vital Signs')->assertSee('Save Vital Signs')->assertSee('Check In Patient');
        foreach (['mmHg', '°C', 'bpm', 'breaths/min', 'SpO₂', 'kg', 'cm'] as $unit) {
            $res->assertSee($unit);
        }
        // Units are display-only: there is no unit input.
        $res->assertDontSee('name="unit', false);
    }

    public function test_saves_all_vitals_to_the_correct_appointment_only(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');
        $b = $this->appt($doctor, $this->walkin('Bob'), '09:30');
        $staff = $this->staff();

        $this->save($staff, $a, $this->fullVitals())->assertSessionHasNoErrors()->assertSessionHas('success');

        $rec = MedicalRecord::where('appointment_id', $a->id)->first();
        $this->assertSame('120/80', $rec->blood_pressure);
        $this->assertSame('78', $rec->pulse_rate);
        $this->assertSame('98', $rec->oxygen_saturation);
        $this->assertSame($staff->id, $rec->vitals_recorded_by);
        $this->assertSame($doctor->id, $rec->doctor_id);
        $this->assertSame($a->walkin_patient_id, $rec->walkin_patient_id);
        $this->assertNull(MedicalRecord::where('appointment_id', $b->id)->first(), 'Other appointment untouched');

        // Saving B with different values never touches A.
        $this->save($staff, $b, ['vital' => ['blood_pressure' => '110/70', 'weight' => '50']])->assertSessionHasNoErrors();
        $this->assertSame('120/80', MedicalRecord::where('appointment_id', $a->id)->first()->blood_pressure);
        $this->assertSame('110/70', MedicalRecord::where('appointment_id', $b->id)->first()->blood_pressure);

        // Re-saving A updates the same row (no duplicate records).
        $this->save($staff, $a, ['vital' => ['blood_pressure' => '118/76']])->assertSessionHasNoErrors();
        $this->assertSame(1, MedicalRecord::where('appointment_id', $a->id)->count());
        $this->assertSame('118/76', MedicalRecord::where('appointment_id', $a->id)->first()->blood_pressure);

        // Refresh: saved values are shown again in the form.
        $this->actingAs($staff)->get(route('staff.appointments.show', $a->id))->assertSee('118/76', false);
    }

    public function test_not_taken_stores_null_and_reason_never_a_fake_value(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');

        $payload = $this->fullVitals();
        unset($payload['vital']['pulse_rate']);
        $payload['not_taken'] = ['pulse_rate' => '1', 'oxygen_saturation' => '1'];
        $payload['reason'] = ['pulse_rate' => 'Equipment unavailable', 'oxygen_saturation' => 'Other'];
        $payload['reason_other'] = ['oxygen_saturation' => 'Nail polish'];
        $payload['vital']['oxygen_saturation'] = '99'; // must be ignored because marked not taken

        $this->save($this->staff(), $a, $payload)->assertSessionHasNoErrors();

        $rec = MedicalRecord::where('appointment_id', $a->id)->first();
        $this->assertNull($rec->pulse_rate);
        $this->assertNull($rec->oxygen_saturation);
        $this->assertSame('Equipment unavailable', $rec->vitals_not_taken['pulse_rate']['reason']);
        $this->assertSame('Nail polish', $rec->vitals_not_taken['oxygen_saturation']['note']);
        $this->assertSame('120/80', $rec->blood_pressure);

        // Vitals left blank (not marked) stay NULL — nothing invented.
        $b = $this->appt($doctor, $this->walkin('Bob'), '10:00');
        $this->save($this->staff(), $b, ['vital' => ['temperature' => '37']])->assertSessionHasNoErrors();
        $r2 = MedicalRecord::where('appointment_id', $b->id)->first();
        $this->assertNull($r2->pulse_rate);
        $this->assertNull($r2->weight);
        $this->assertNull($r2->vitals_not_taken);
    }

    public function test_validation_rules(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');
        $staff = $this->staff();

        $this->save($staff, $a, ['vital' => ['blood_pressure' => 'abc']])->assertSessionHasErrors('vital.blood_pressure');
        $this->save($staff, $a, ['vital' => ['temperature' => 'hot']])->assertSessionHasErrors('vital.temperature');
        $this->save($staff, $a, ['vital' => ['weight' => '-5']])->assertSessionHasErrors('vital.weight');
        $this->save($staff, $a, ['not_taken' => ['height' => '1']])->assertSessionHasErrors('reason.height');
        $this->save($staff, $a, ['not_taken' => ['height' => '1'], 'reason' => ['height' => 'Other']])
            ->assertSessionHasErrors('reason_other.height');
        $this->save($staff, $a, ['not_taken' => ['height' => '1'], 'reason' => ['height' => 'Made up']])
            ->assertSessionHasErrors('reason.height');
        $this->assertNull(MedicalRecord::where('appointment_id', $a->id)->first());
    }

    public function test_only_authorized_staff_can_record_and_only_before_consultation(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');

        // Staff without the permission
        $this->save($this->staff(['view_appointments', 'view_appointment_details']), $a, $this->fullVitals())->assertForbidden();
        // Doctor and Patient can not use the Staff route at all
        $this->actingAs($doctor)->post(route('staff.appointments.vitalSigns.save', $a->id), $this->fullVitals())->assertStatus(403);
        $patient = $this->makeUser('Patient', 'p@t.com');
        $this->actingAs($patient)->post(route('staff.appointments.vitalSigns.save', $a->id), $this->fullVitals())->assertStatus(403);
        $this->assertNull(MedicalRecord::where('appointment_id', $a->id)->first());

        // Not allowed once the consultation is under way / done
        foreach (['In Progress', 'Completed', 'Pending', 'Cancelled'] as $status) {
            $a->update(['status' => $status]);
            $this->save($this->staff(), $a, $this->fullVitals())->assertSessionHas('error');
        }
        $this->assertNull(MedicalRecord::where('appointment_id', $a->id)->first());
    }

    public function test_check_in_still_works_and_is_not_blocked_by_missing_vitals(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('staff.appointments.show', $a->id))
            ->assertSee(route('staff.appointments.checkIn', $a->id), false);

        // No vitals saved at all — check-in still succeeds.
        $this->actingAs($staff)->post(route('staff.appointments.checkIn', $a->id))->assertSessionHas('success');
        $this->assertSame('Checked In', $a->fresh()->status);

        // Vitals can still be recorded while Checked In.
        $this->save($staff, $a, $this->fullVitals())->assertSessionHasNoErrors();
        $this->assertSame('120/80', MedicalRecord::where('appointment_id', $a->id)->first()->blood_pressure);
    }

    // ---------------- DOCTOR ----------------

    public function test_doctor_sees_read_only_vitals_and_can_proceed_when_some_are_missing(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');
        $staff = $this->staff();

        $payload = $this->fullVitals();
        unset($payload['vital']['pulse_rate']);
        $payload['not_taken'] = ['pulse_rate' => '1'];
        $payload['reason'] = ['pulse_rate' => 'Patient declined'];
        $this->save($staff, $a, $payload)->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('staff.appointments.checkIn', $a->id));

        // Start consultation with a missing vital → allowed.
        $this->actingAs($doctor)->post(route('doctor.appointments.startConsultation', $a->id))->assertRedirect();
        $this->assertSame('In Progress', $a->fresh()->status);

        $res = $this->actingAs($doctor)->get(route('doctor.appointments.show', $a->id));
        $res->assertOk()->assertSee('120/80')->assertSee('36.7')->assertSee('breaths/min')->assertSee('mmHg')
            ->assertSee('Not taken')->assertSee('Patient declined')->assertSee('Read-only');
        foreach (['blood_pressure', 'temperature', 'pulse_rate', 'respiratory_rate', 'oxygen_saturation', 'weight', 'height'] as $f) {
            $res->assertDontSee('name="' . $f . '"', false);
        }
    }

    public function test_doctor_draft_and_complete_never_overwrite_staff_vitals(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');
        $staff = $this->staff();
        $this->save($staff, $a, $this->fullVitals())->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('staff.appointments.checkIn', $a->id));
        $this->actingAs($doctor)->post(route('doctor.appointments.startConsultation', $a->id));

        $clinical = ['chief_complaint' => 'Cough', 'diagnosis' => 'URTI', 'treatment' => 'Rest', 'notes' => 'n'];
        // A tampered request that tries to send vitals is ignored.
        $tamper = ['blood_pressure' => '999/999', 'temperature' => '1', 'weight' => '1', 'height' => '1'];

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveDraft', $a->id), $clinical + $tamper)
            ->assertSessionHasNoErrors();
        $rec = MedicalRecord::where('appointment_id', $a->id)->first();
        $this->assertSame('Cough', $rec->chief_complaint);
        $this->assertSame('120/80', $rec->blood_pressure);
        $this->assertSame('36.7', $rec->temperature);
        $this->assertSame('98', $rec->oxygen_saturation);

        $this->actingAs($doctor)->patch(route('doctor.appointments.completeConsultation', $a->id), $clinical + $tamper)
            ->assertSessionHasNoErrors();
        $this->assertSame('Completed', $a->fresh()->status);
        $rec = $rec->fresh();
        $this->assertSame('URTI', $rec->diagnosis);
        $this->assertSame('120/80', $rec->blood_pressure);
        $this->assertSame('65', $rec->weight);
        $this->assertSame(1, MedicalRecord::where('appointment_id', $a->id)->count());

        // Completed consultation still shows the vitals read-only.
        $this->actingAs($doctor)->get(route('doctor.appointments.show', $a->id))->assertOk()->assertSee('120/80');
        $this->actingAs($doctor)->get(route('doctor.medical-records.show', $a->id))->assertOk();
    }

    public function test_complete_without_any_vitals_still_works(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00', 'In Progress');
        $this->actingAs($doctor)->patch(route('doctor.appointments.completeConsultation', $a->id), [
            'chief_complaint' => 'Cough', 'diagnosis' => 'URTI', 'treatment' => 'Rest',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Completed', $a->fresh()->status);
    }

    public function test_vitals_only_record_is_not_listed_as_a_medical_record_until_consulted(): void
    {
        $doctor = $this->makeUser('Doctor', 'd@t.com');
        $a = $this->appt($doctor, $this->walkin('Ann'), '09:00');
        $this->save($this->staff(), $a, $this->fullVitals())->assertSessionHasNoErrors();

        // (Match on the record's own link — the patient's name also appears in the layout's notification list.)
        $link = route('doctor.medical-records.show', $a->id);
        $this->actingAs($doctor)->get(route('doctor.medical-records.index'))->assertOk()->assertDontSee($link, false);

        $a->update(['status' => 'In Progress']);
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveDraft', $a->id), ['chief_complaint' => 'Cough']);
        $this->actingAs($doctor)->get(route('doctor.medical-records.index'))->assertOk()->assertSee($link, false);
    }
}
