<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorWeeklySchedule;
use App\Models\DoctorWeeklyScheduleSlot;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Walk-in Queue on top of the existing appointment workflow:
 * Scheduled = reserved time; Walk-in = queue number + real timestamps, NULL time.
 */
class WalkInQueueTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // ---------------- fixtures ----------------

    private function makeUser(string $role, string $status = 'Active'): User
    {
        $this->seq++;

        $user = User::create([
            'first_name' => $role . $this->seq, 'last_name' => 'Test', 'birthdate' => '1990-01-01',
            'gender' => 'Male', 'civil_status' => 'Single', 'address' => 'Sample St',
            'contact_number' => '0917' . str_pad((string) (2000000 + $this->seq), 7, '0', STR_PAD_LEFT),
            'username' => strtolower($role) . 'wq' . $this->seq,
            'email' => strtolower($role) . 'wq' . $this->seq . '@t.test',
            'password' => bcrypt('password'), 'role' => $role,
        ]);
        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'Approved', 'status' => $status])->save();

        return $user;
    }

    private function staff(array $slugs = [
        'view_appointments', 'view_appointment_details', 'check_in_appointment',
        'record_vital_signs', 'view_patients', 'add_walk_in_patient',
    ]): User
    {
        $staff = $this->makeUser('Staff');
        foreach ($slugs as $slug) {
            $p = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'Test']);
            $staff->permissions()->attach($p->id);
        }

        return $staff;
    }

    /** Doctor who works today (weekly schedule row for today's weekday). */
    private function doctor(bool $withSchedule = true, string $status = 'Active'): User
    {
        $doctor = $this->makeUser('Doctor', $status);

        if ($withSchedule) {
            $week = DoctorWeeklySchedule::create([
                'doctor_id' => $doctor->id, 'day_of_week' => now()->format('l'), 'status' => 'Available',
            ]);
            DoctorWeeklyScheduleSlot::create([
                'doctor_weekly_schedule_id' => $week->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            ]);
        }

        return $doctor;
    }

    private function walkinPatient(string $first): Patient
    {
        return Patient::create([
            'user_id' => null, 'first_name' => $first, 'last_name' => 'Person',
            'birthdate' => '1985-05-05', 'gender' => 'Female', 'civil_status' => 'Single',
            'contact_number' => '0918' . random_int(1000000, 9999999), 'address' => 'Sample St',
            'is_walk_in' => true,
        ]);
    }

    private function scheduled(User $doctor, Patient $p, string $time = '14:00:00', string $status = 'Approved'): Appointment
    {
        return Appointment::create([
            'walkin_patient_id' => $p->id, 'doctor_id' => $doctor->id,
            'appointment_date' => now()->toDateString(), 'appointment_time' => $time,
            'status' => $status, 'reason' => 'Checkup',
        ]);
    }

    private function queue(User $staff, Patient $p, User $doctor, string $type = 'patient')
    {
        return $this->actingAs($staff)->post(route('staff.walk-in-queue.store'), [
            'patient_id' => $p->id, 'patient_type' => $type, 'doctor_id' => $doctor->id,
        ]);
    }

    private function vitals(): array
    {
        return ['vital' => ['blood_pressure' => '120/80', 'temperature' => '36.7', 'pulse_rate' => '78']];
    }

    // ---------------- Add to Queue ----------------

    public function test_add_to_queue_creates_a_waiting_walkin_visit_without_a_fake_time_or_new_patient(): void
    {
        $doctor = $this->doctor();
        $juan   = $this->walkinPatient('Juan');
        $staff  = $this->staff();
        $patientsBefore = Patient::count();

        $this->queue($staff, $juan, $doctor)->assertSessionHasNoErrors()->assertSessionHas('success');

        $visit = Appointment::where('appointment_type', 'Walk-in')->firstOrFail();

        $this->assertSame('Waiting', $visit->status);          // NOT Approved
        $this->assertSame('Q001', $visit->queue_number);
        $this->assertNull($visit->appointment_time);           // no fake slot
        $this->assertTrue($visit->appointment_date->isToday());
        $this->assertNotNull($visit->arrived_at);
        $this->assertSame($juan->id, $visit->walkin_patient_id);
        $this->assertNull($visit->patient_id);
        $this->assertSame('WALK-' . now()->format('Ymd') . '-001', $visit->reference_no);
        $this->assertSame($patientsBefore, Patient::count());  // no duplicate patient record
    }

    public function test_registered_user_can_be_queued_without_creating_another_record(): void
    {
        $doctor  = $this->doctor();
        $patient = $this->makeUser('Patient');
        $staff   = $this->staff();
        $usersBefore = User::count();

        $this->actingAs($staff)->post(route('staff.walk-in-queue.store'), [
            'patient_id' => $patient->id, 'patient_type' => 'user', 'doctor_id' => $doctor->id,
        ])->assertSessionHasNoErrors();

        $visit = Appointment::where('appointment_type', 'Walk-in')->firstOrFail();
        $this->assertSame($patient->id, $visit->patient_id);
        $this->assertNull($visit->walkin_patient_id);
        $this->assertSame($usersBefore, User::count());
    }

    public function test_queue_numbers_increment_per_day_and_a_patient_cannot_be_queued_twice(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff();
        $a = $this->walkinPatient('Ann');
        $b = $this->walkinPatient('Ben');

        $this->queue($staff, $a, $doctor);
        $this->queue($staff, $b, $doctor);
        $this->assertSame(['Q001', 'Q002'], Appointment::orderBy('id')->pluck('queue_number')->all());

        // Same patient again while still active -> rejected, no new row / number.
        $this->queue($staff, $a, $doctor)->assertSessionHasErrors('patient_id');
        $this->assertSame(2, Appointment::count());
    }

    public function test_only_active_available_doctors_can_receive_a_walkin(): void
    {
        $staff = $this->staff();
        $p     = $this->walkinPatient('Cara');

        $noSchedule = $this->doctor(false);
        $inactive   = $this->doctor(true, 'Inactive');

        $this->queue($staff, $p, $noSchedule)->assertSessionHasErrors('doctor_id');
        $this->queue($staff, $p, $inactive)->assertSessionHasErrors('doctor_id');
        $this->assertSame(0, Appointment::count());

        // The Add to Queue modal only offers the available doctor (the
        // separate Schedule Appointment modal lists every doctor, so only
        // the queue modal's own markup is inspected).
        $ok  = $this->doctor();
        $html = $this->actingAs($staff)->get(route('staff.patients.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="queueModal"');
        $this->assertNotFalse($start);
        $modal = substr($html, $start, strpos($html, '<!-- ====', $start) - $start);

        $this->assertStringContainsString('value="' . $ok->id . '"', $modal);
        $this->assertStringNotContainsString('value="' . $noSchedule->id . '"', $modal);
        $this->assertStringNotContainsString('value="' . $inactive->id . '"', $modal);
    }

    public function test_add_to_queue_requires_the_walk_in_permission(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff(['view_appointments']);

        $this->queue($staff, $this->walkinPatient('Dan'), $doctor)->assertForbidden();
        $this->assertSame(0, Appointment::count());
    }

    // ---------------- Scheduled vs Walk-in ----------------

    public function test_scheduled_slot_stays_reserved_and_unique_while_walkins_never_collide(): void
    {
        $doctor = $this->doctor();
        $a = $this->scheduled($doctor, $this->walkinPatient('Alice'));        // 2:00 PM Approved

        // The doctor/date/time uniqueness for scheduled appointments still holds...
        $this->expectException(QueryException::class);
        try {
            $this->scheduled($doctor, $this->walkinPatient('Clone'));
        } finally {
            // ...and walk-ins (NULL time) never take or conflict with that slot.
            $staff = $this->staff();
            $this->queue($staff, $this->walkinPatient('Bob'), $doctor);
            $this->queue($staff, $this->walkinPatient('Bea'), $doctor);

            $this->assertSame(2, Appointment::where('appointment_type', 'Walk-in')->count());
            $this->assertSame('14:00:00', $a->fresh()->appointment_time);
            $this->assertSame('Approved', $a->fresh()->status);
        }
    }

    // ---------------- Full flow (the test scenario) ----------------

    public function test_full_walkin_flow_alongside_a_scheduled_appointment(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff();
        $juan   = $this->walkinPatient('Juan');
        $a      = $this->scheduled($doctor, $this->walkinPatient('Alice'));    // Patient A, 2:00 PM, Approved

        // 1-3. Staff adds Patient B -> Q001, no scheduled time.
        $this->queue($staff, $juan, $doctor);
        $b = Appointment::where('appointment_type', 'Walk-in')->firstOrFail();
        $this->assertSame('Q001', $b->queue_number);
        $this->assertNull($b->appointment_time);

        // Appointments page: compact link with the count, and Waiting shows in the modal.
        $this->actingAs($staff)->get(route('staff.appointments.index'))
            ->assertOk()->assertSee('Walk-in Queue (1)')->assertSee('Q001')->assertSee('Waiting');

        // Vitals are not allowed before Check In.
        $this->actingAs($staff)->post(route('staff.appointments.vitalSigns.save', $b->id), $this->vitals())
            ->assertSessionHas('error');

        // 4-5. Check In updates the SAME record; visible in the Checked In tab.
        $count = Appointment::count();
        $this->actingAs($staff)->post(route('staff.appointments.checkIn', $b->id), ['from_queue' => 1])
            ->assertRedirect(route('staff.appointments.index', ['queue' => 1]));
        $this->assertSame($count, Appointment::count());
        $this->assertSame('Checked In', $b->fresh()->status);
        $this->actingAs($staff)->get(route('staff.appointments.index', ['status' => 'Checked In']))
            ->assertOk()->assertSee('Walk-in Q001');

        // 6-7. Vitals recorded -> "Vitals Taken", still Checked In (waiting for Doctor).
        $this->actingAs($staff)->post(route('staff.appointments.vitalSigns.save', $b->id), $this->vitals())
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Vitals Taken', $b->fresh()->load('medicalRecord')->queueStage());
        $this->assertNotNull(MedicalRecord::where('appointment_id', $b->id)->value('vitals_recorded_at'));

        // 8-9. Doctor starts the walk-in's consultation BEFORE Patient A is due.
        $this->actingAs($doctor)->post(route('doctor.appointments.startConsultation', $b->id));
        $this->assertSame('In Progress', $b->fresh()->status);
        $this->assertSame(1, Appointment::activeWalkInQueue()->count());   // still active while in consultation

        // 10-11. Patient A's reserved 2:00 PM appointment is untouched.
        $this->assertSame('14:00:00', $a->fresh()->appointment_time);
        $this->assertSame('Approved', $a->fresh()->status);

        // 12. Doctor completes the walk-in.
        $this->actingAs($doctor)->patch(route('doctor.appointments.completeConsultation', $b->id), [
            'chief_complaint' => 'Cough', 'diagnosis' => 'Cold', 'treatment' => 'Rest',
        ])->assertSessionHas('success');
        $this->assertSame('Completed', $b->fresh()->status);

        // 13-15. Gone from the active queue; in Completed + All; same permanent patient; no fake time.
        $this->assertSame(0, Appointment::activeWalkInQueue()->count());
        $this->actingAs($staff)->get(route('staff.appointments.index', ['status' => 'Completed']))
            ->assertOk()->assertSee('Walk-in Q001')->assertSee('Walk-in Queue (0)');
        $this->actingAs($staff)->get(route('staff.appointments.index'))
            ->assertOk()->assertSee('WALK-' . now()->format('Ymd') . '-001');
        $this->assertSame($juan->id, $b->fresh()->walkin_patient_id);
        $this->assertNull($b->fresh()->appointment_time);

        // Details page works for a walk-in without printing a time.
        $this->actingAs($staff)->get(route('staff.appointments.show', $b->id))
            ->assertOk()->assertSee('Visit Type')->assertSee('Q001');
    }

    public function test_scheduled_checkin_still_requires_approved_or_rescheduled_today_and_walkin_requires_waiting(): void
    {
        $doctor = $this->doctor();
        $a = $this->scheduled($doctor, $this->walkinPatient('Alice'));
        $this->assertTrue($a->canBeCheckedIn());

        $this->queue($this->staff(), $this->walkinPatient('Wally'), $doctor);
        $w = Appointment::where('appointment_type', 'Walk-in')->firstOrFail();
        $this->assertTrue($w->canBeCheckedIn());

        $w->update(['status' => 'Checked In']);
        $this->assertFalse($w->fresh()->canBeCheckedIn());

        // A Waiting walk-in is never treated as an Approved scheduled appointment.
        $this->assertSame(1, Appointment::where('status', 'Approved')->count());
    }

    public function test_patients_page_offers_add_to_queue_and_marks_already_queued_patients(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff();
        $p      = $this->walkinPatient('Zed');

        $this->actingAs($staff)->get(route('staff.patients.index'))
            ->assertOk()->assertSee('Add to Queue');

        $this->queue($staff, $p, $doctor);

        $this->actingAs($staff)->get(route('staff.patients.index'))
            ->assertOk()->assertSee('In Queue (Q001)');
    }

    // ---------------- Remove from Queue ----------------

    private function waitingWalkIn(User $staff, User $doctor, string $name = 'Remy'): Appointment
    {
        $this->queue($staff, $this->walkinPatient($name), $doctor);

        return Appointment::where('appointment_type', 'Walk-in')->latest('id')->firstOrFail();
    }

    public function test_staff_can_remove_a_waiting_walkin_silently_and_it_leaves_the_active_queue(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff();
        $w = $this->waitingWalkIn($staff, $doctor);
        $this->assertSame(1, Appointment::activeWalkInQueue()->count());
        $count = Appointment::count();

        $this->actingAs($staff)->post(route('staff.walk-in-queue.remove', $w->id))
            ->assertRedirect(route('staff.appointments.index', ['queue' => 1]))
            ->assertSessionHas('success');

        // Same record kept (status only), nothing deleted, out of the active queue.
        $this->assertSame($count, Appointment::count());
        $this->assertSame('Cancelled', $w->fresh()->status);
        $this->assertSame('Q001', $w->fresh()->queue_number);
        $this->assertSame(0, Appointment::activeWalkInQueue()->count());

        // Silent: no patient email, unlike the regular Cancel action.
        Mail::assertNothingSent();
        $this->assertDatabaseHas('user_logs', ['action' => 'Removed from Walk-in Queue']);
    }

    public function test_only_a_waiting_walkin_can_be_removed(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff();

        // Checked In walk-in: refused, status unchanged.
        $checkedIn = $this->waitingWalkIn($staff, $doctor, 'Cora');
        $this->actingAs($staff)->post(route('staff.appointments.checkIn', $checkedIn->id));
        $this->actingAs($staff)->post(route('staff.walk-in-queue.remove', $checkedIn->id))
            ->assertSessionHas('error');
        $this->assertSame('Checked In', $checkedIn->fresh()->status);

        // A scheduled appointment can never be removed through this route.
        $scheduled = $this->scheduled($doctor, $this->walkinPatient('Sam'));
        $this->actingAs($staff)->post(route('staff.walk-in-queue.remove', $scheduled->id))
            ->assertSessionHas('error');
        $this->assertSame('Approved', $scheduled->fresh()->status);
        $this->assertSame('14:00:00', $scheduled->fresh()->appointment_time);

        // Already removed: second attempt is refused too.
        $w = $this->waitingWalkIn($staff, $doctor, 'Dina');
        $this->actingAs($staff)->post(route('staff.walk-in-queue.remove', $w->id));
        $this->actingAs($staff)->post(route('staff.walk-in-queue.remove', $w->id))->assertSessionHas('error');
    }

    public function test_remove_requires_the_same_permission_as_adding_to_the_queue(): void
    {
        $doctor = $this->doctor();
        $w = $this->waitingWalkIn($this->staff(), $doctor);

        $this->actingAs($this->staff(['view_appointments']))
            ->post(route('staff.walk-in-queue.remove', $w->id))->assertForbidden();
        $this->assertSame('Waiting', $w->fresh()->status);

        $this->actingAs($this->staff(['view_appointments', 'add_walk_in_patient']))
            ->post(route('staff.walk-in-queue.remove', $w->id))->assertSessionHas('success');
        $this->assertSame('Cancelled', $w->fresh()->status);
    }

    public function test_a_removed_patient_can_be_queued_again_with_a_new_number(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff();
        $patient = $this->walkinPatient('Eli');

        $this->queue($staff, $patient, $doctor);
        $first = Appointment::firstOrFail();
        $this->actingAs($staff)->post(route('staff.walk-in-queue.remove', $first->id));

        $this->queue($staff, $patient, $doctor)->assertSessionHasNoErrors();

        // Q001 is never reused on the same day.
        $this->assertSame(['Q001', 'Q002'], Appointment::orderBy('id')->pluck('queue_number')->all());
        $this->assertSame(1, Appointment::activeWalkInQueue()->count());
    }

    public function test_queue_modal_shows_remove_for_waiting_only(): void
    {
        $doctor = $this->doctor();
        $staff  = $this->staff();
        $waiting = $this->waitingWalkIn($staff, $doctor, 'Fay');

        $this->actingAs($staff)->get(route('staff.appointments.index'))
            ->assertOk()->assertSee('walkin-queue-btn--remove', false)
            ->assertSee(route('staff.walk-in-queue.remove', $waiting->id), false);

        $this->actingAs($staff)->post(route('staff.appointments.checkIn', $waiting->id));

        $this->actingAs($staff)->get(route('staff.appointments.index'))
            ->assertOk()->assertDontSee(route('staff.walk-in-queue.remove', $waiting->id), false);
    }

    public function test_removed_walkin_does_not_tell_a_registered_patient_their_appointment_was_cancelled(): void
    {
        $doctor  = $this->doctor();
        $staff   = $this->staff();
        $patient = $this->makeUser('Patient');

        $this->actingAs($staff)->post(route('staff.walk-in-queue.store'), [
            'patient_id' => $patient->id, 'patient_type' => 'user', 'doctor_id' => $doctor->id,
        ]);
        $w = Appointment::where('appointment_type', 'Walk-in')->firstOrFail();
        $this->actingAs($staff)->post(route('staff.walk-in-queue.remove', $w->id));

        $feed = app(\App\Services\PatientNotificationService::class)->build($patient);
        $this->assertSame(0, collect($feed)->filter(fn ($n) => ($n['label'] ?? '') === 'Cancelled')->count());
    }

    public function test_time_label_never_invents_a_time_for_walkins(): void
    {
        $walkIn = new Appointment(['appointment_type' => 'Walk-in', 'appointment_time' => null]);
        $this->assertSame('Walk-in', $walkIn->timeLabel());
        $this->assertSame('Walk-in', $walkIn->formatted_time);

        $scheduled = new Appointment(['appointment_type' => 'Regular', 'appointment_time' => '14:00:00']);
        $this->assertSame('02:00 PM', $scheduled->timeLabel());
    }
}
