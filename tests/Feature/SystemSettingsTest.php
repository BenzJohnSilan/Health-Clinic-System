<?php

namespace Tests\Feature;

use App\Mail\AppointmentStatusMail;
use App\Models\Appointment;
use App\Models\DoctorWeeklySchedule;
use App\Models\DoctorWeeklyScheduleSlot;
use App\Models\Patient;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserLog;
use App\Services\ClinicSchedule;
use App\Services\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin > System Settings: persistence, validation, authorization, logo
 * handling, and proof that the saved settings change real behavior
 * (booking validation, notifications, session timeout).
 */
class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        SystemSettings::forget();
    }

    // ------------------------------------------------------------------ fixtures

    private function makeUser(string $role, array $extra = []): User
    {
        $this->seq++;

        $user = User::create(array_merge([
            'first_name' => $role, 'last_name' => 'Test', 'birthdate' => '1990-01-01',
            'gender' => 'Male', 'civil_status' => 'Single', 'address' => 'Sample St',
            'contact_number' => '0917' . str_pad((string) (3000000 + $this->seq), 7, '0', STR_PAD_LEFT),
            'username' => strtolower($role) . 'ss' . $this->seq,
            'email' => strtolower($role) . 'ss' . $this->seq . '@t.test',
            'password' => bcrypt('password'), 'role' => $role,
        ], $extra));
        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'Approved', 'status' => 'Active'])->save();

        return $user;
    }

    private function admin(): User
    {
        return $this->makeUser('Admin');
    }

    private function patientUser(): User
    {
        return $this->makeUser('Patient', [
            'blood_type' => 'O+', 'allergies' => 'None', 'emergency_name' => 'Kin Test',
            'relationship' => 'Parent', 'emergency_contact_number' => '09170000000', 'emergency_address' => 'Sample St',
        ]);
    }

    private function doctor(): User
    {
        $doctor = $this->makeUser('Doctor');

        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $week = DoctorWeeklySchedule::create(['doctor_id' => $doctor->id, 'day_of_week' => $day, 'status' => 'Available']);
            DoctorWeeklyScheduleSlot::create([
                'doctor_weekly_schedule_id' => $week->id, 'start_time' => '06:00', 'end_time' => '22:00',
            ]);
        }

        return $doctor;
    }

    /** A full, valid form submission; override pieces per test. */
    private function payload(array $override = []): array
    {
        return array_merge([
            'clinic_name' => 'Sunrise Family Clinic',
            'clinic_address' => '12 Rizal St, Calauan, Laguna',
            'clinic_phone' => '+63 917 123 4567',
            'clinic_email' => 'hello@sunrise.test',
            'slot_minutes' => 30, 'opening_time' => '09:00', 'closing_time' => '17:00',
            'lunch_enabled' => '1', 'lunch_start' => '12:00', 'lunch_end' => '13:00',
            'booking_min_notice_hours' => 0, 'booking_max_advance_days' => 0,
            'notify_email_enabled' => '1', 'notify_sms_enabled' => '1',
            'notify_email_approved' => '1', 'notify_email_rejected' => '1',
            'notify_email_cancelled' => '1', 'notify_email_rescheduled' => '1',
            'notify_sms_approved' => '1', 'notify_sms_rejected' => '1',
            'notify_sms_cancelled' => '1', 'notify_sms_rescheduled' => '1',
            'notify_sms_lab_reviewed' => '1',
            'session_lifetime' => '',
        ], $override);
    }

    private function save(User $admin, array $override = [])
    {
        return $this->actingAs($admin)->put(route('admin.system-settings.update'), $this->payload($override));
    }

    /** Tiny real PNG (no GD needed, so tests also run on PHP builds without it). */
    private function png(string $name, int $padBytes = 0): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $bytes . str_repeat("\0", $padBytes));
    }

    private function nextWeekday(): string
    {
        return now()->addDays(3)->toDateString();
    }

    // ------------------------------------------------------------------ page, sidebar, defaults

    public function test_admin_sees_page_with_defaults_that_match_previous_behavior(): void
    {
        $this->actingAs($this->admin())->get(route('admin.system-settings.index'))
            ->assertOk()
            ->assertSee('General Settings')->assertSee('Appointment Settings')
            ->assertSee('Notification Settings')->assertSee('Security Settings')
            ->assertSee('9:00 AM – 9:30 AM')
            ->assertSee('12:00 PM – 1:00 PM (Lunch Break)')
            ->assertSee('4:30 PM – 5:00 PM');

        $this->assertSame(30, ClinicSchedule::slotMinutes());
        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'],
            ClinicSchedule::bookableTimes()
        );
        $this->assertNull(SystemSettings::sessionLifetime());
        $this->assertTrue(SystemSettings::notificationEnabled('email', 'approved'));
        $this->assertTrue(SystemSettings::notificationEnabled('sms', 'lab_reviewed'));
    }

    public function test_sidebar_has_direct_link_between_backup_and_account_sections_and_is_active(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.system-settings.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<span>System Settings</span>'), 'exactly one sidebar entry');
        $this->assertMatchesRegularExpression(
            '/Backup &amp; Recovery.*?System Settings.*?nav-section-label">Account<.*?Account Settings/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<a href="' . preg_quote(route('admin.system-settings.index'), '/') . '"\s+class="active">/',
            $html
        );
    }

    public function test_missing_table_falls_back_to_defaults_safely(): void
    {
        \Illuminate\Support\Facades\Schema::drop('system_settings');
        SystemSettings::forget();

        $this->assertSame('09:00', SystemSettings::get('opening_time'));
        $this->assertSame(30, ClinicSchedule::slotMinutes());
    }

    // ------------------------------------------------------------------ authorization

    public function test_guests_are_redirected_and_non_admins_get_403_on_page_and_update(): void
    {
        $this->get(route('admin.system-settings.index'))->assertRedirect('/login');
        $this->put(route('admin.system-settings.update'), $this->payload())->assertRedirect('/login');

        foreach (['Patient', 'Doctor', 'Staff'] as $role) {
            $user = $role === 'Patient' ? $this->patientUser() : $this->makeUser($role);

            $this->actingAs($user)->get(route('admin.system-settings.index'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.system-settings.update'), $this->payload(['clinic_name' => 'Hacked']))->assertForbidden();
        }

        $this->assertSame('Clinic RMS', SystemSettings::clinicName());
        $this->assertSame(0, SystemSetting::count());
    }

    public function test_form_carries_csrf_token_and_method_spoofing(): void
    {
        // Laravel skips CSRF verification while running tests, so assert the
        // form carries the token a real browser request needs.
        $this->actingAs($this->admin())->get(route('admin.system-settings.index'))
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    // ------------------------------------------------------------------ persistence + audit

    public function test_saved_values_persist_and_are_shown_when_page_is_reopened_and_logged(): void
    {
        $admin = $this->admin();

        $this->save($admin, ['slot_minutes' => 45, 'closing_time' => '16:30', 'booking_min_notice_hours' => 2])
            ->assertRedirect(route('admin.system-settings.index'))
            ->assertSessionHas('success');

        $this->assertSame('Sunrise Family Clinic', SystemSettings::clinicName());
        $this->assertSame('+63 917 123 4567', SystemSettings::get('clinic_phone'));
        $this->assertSame(45, ClinicSchedule::slotMinutes());

        $this->actingAs($admin)->get(route('admin.system-settings.index'))
            ->assertSee('value="Sunrise Family Clinic"', false)
            ->assertSee('value="hello@sunrise.test"', false)
            ->assertSee('value="16:30"', false);

        $log = UserLog::where('action', 'Updated System Settings')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('System Settings', $log->module);
        $this->assertStringContainsString('Clinic name: Clinic RMS → Sunrise Family Clinic', $log->details);
        $this->assertStringContainsString('Appointment duration (min): 30 → 45', $log->details);
    }

    public function test_saving_without_changes_creates_no_log(): void
    {
        $this->save($this->admin(), ['clinic_name' => 'Clinic RMS', 'clinic_address' => '', 'clinic_phone' => '', 'clinic_email' => '']);

        $this->assertSame(0, UserLog::where('action', 'Updated System Settings')->count());
    }

    public function test_does_not_touch_website_settings_or_user_profile(): void
    {
        $admin = $this->admin();
        \App\Models\WebsiteSetting::create(['key' => 'contact_phone', 'value' => '111']);

        $this->save($admin, ['clinic_phone' => '09171234567'])->assertSessionHasNoErrors();

        $this->assertSame('111', \App\Models\WebsiteSetting::where('key', 'contact_phone')->value('value'));
        $this->assertSame('Sample St', $admin->fresh()->address);
        $this->assertSame($admin->contact_number, $admin->fresh()->contact_number);
    }

    // ------------------------------------------------------------------ validation

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_and_nothing_is_saved(array $override, string $errorKey): void
    {
        $this->save($this->admin(), $override)->assertSessionHasErrors($errorKey);

        $this->assertSame(0, SystemSetting::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'empty clinic name'        => [['clinic_name' => ''], 'clinic_name'],
            'bad email'                => [['clinic_email' => 'not-an-email'], 'clinic_email'],
            'bad phone'                => [['clinic_phone' => 'call me <b>'], 'clinic_phone'],
            'unsupported duration'     => [['slot_minutes' => 25], 'slot_minutes'],
            'bad time format'          => [['opening_time' => '9am'], 'opening_time'],
            'closing before opening'   => [['opening_time' => '17:00', 'closing_time' => '09:00', 'lunch_enabled' => '0'], 'closing_time'],
            'hours shorter than slot'  => [['opening_time' => '09:00', 'closing_time' => '09:20', 'lunch_enabled' => '0', 'slot_minutes' => 30], 'closing_time'],
            'lunch ends before start'  => [['lunch_start' => '13:00', 'lunch_end' => '12:00'], 'lunch_end'],
            'lunch outside hours'      => [['lunch_start' => '07:00', 'lunch_end' => '08:00'], 'lunch_start'],
            'lunch swallows all slots' => [['opening_time' => '09:00', 'closing_time' => '12:00', 'lunch_start' => '09:00', 'lunch_end' => '12:00'], 'lunch_start'],
            'notice out of range'      => [['booking_min_notice_hours' => 999], 'booking_min_notice_hours'],
            'window out of range'      => [['booking_max_advance_days' => -1], 'booking_max_advance_days'],
            'session value not listed' => [['session_lifetime' => '5'], 'session_lifetime'],
        ];
    }

    // ------------------------------------------------------------------ logo

    public function test_logo_upload_validates_type_and_size(): void
    {
        $admin = $this->admin();

        $this->save($admin, ['clinic_logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('clinic_logo');
        $this->save($admin, ['clinic_logo' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1;')])
            ->assertSessionHasErrors('clinic_logo');
        $this->save($admin, ['clinic_logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('clinic_logo');
        $this->save($admin, ['clinic_logo' => $this->png('big.png', 3 * 1024 * 1024)])
            ->assertSessionHasErrors('clinic_logo');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame('', SystemSettings::get('clinic_logo'));
    }

    public function test_logo_is_stored_kept_when_not_reuploaded_replaced_safely_and_removable(): void
    {
        $admin = $this->admin();

        // upload
        $this->save($admin, ['clinic_logo' => $this->png('../../evil name.png')])
            ->assertSessionHasNoErrors();
        $first = SystemSettings::get('clinic_logo');
        $this->assertMatchesRegularExpression('#^clinic/[A-Za-z0-9_\-]+\.png$#', $first);
        Storage::disk('public')->assertExists($first);
        $this->assertNotNull(SystemSettings::logoUrl());

        // save again with no file: logo preserved
        $this->save($admin, ['clinic_name' => 'Renamed Clinic'])->assertSessionHasNoErrors();
        $this->assertSame($first, SystemSettings::get('clinic_logo'));
        Storage::disk('public')->assertExists($first);

        // replace: new file stored, old one deleted
        $this->save($admin, ['clinic_logo' => $this->png('new.png')])->assertSessionHasNoErrors();
        $second = SystemSettings::get('clinic_logo');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);

        // sidebar shows the logo for other roles too
        $this->actingAs($admin)->get(route('admin.system-settings.index'))->assertSee(basename($second), false);

        // remove
        $this->save($admin, ['remove_logo' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('', SystemSettings::get('clinic_logo'));
        Storage::disk('public')->assertMissing($second);
        $this->assertNull(SystemSettings::logoUrl());
    }

    public function test_tampered_stored_logo_path_is_never_served_or_deleted(): void
    {
        SystemSetting::create(['key' => 'clinic_logo', 'value' => '../../.env']);
        SystemSettings::forget();

        $this->assertNull(SystemSettings::logoUrl());
        SystemSettings::deleteLogo('../../.env'); // must be a no-op, not an error
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------ appointment settings drive real booking behavior

    public function test_saved_hours_duration_and_lunch_change_the_slot_dropdown_and_grid(): void
    {
        $this->save($this->admin(), [
            'slot_minutes' => 60, 'opening_time' => '08:00', 'closing_time' => '15:00',
            'lunch_start' => '11:00', 'lunch_end' => '12:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['08:00', '09:00', '10:00', '12:00', '13:00', '14:00'],
            ClinicSchedule::bookableTimes()
        );

        // The dropdown partial used by patient/staff/admin forms is generated from it.
        $html = view('partials.time-slot-options', ['selected' => '13:00'])->render();
        $this->assertStringContainsString('8:00 AM – 9:00 AM', $html);
        $this->assertStringContainsString('11:00 AM – 12:00 PM (Lunch Break)', $html);
        $this->assertStringNotContainsString('4:30 PM', $html);
        $this->assertStringContainsString('value="13:00" selected', $html);
    }

    public function test_lunch_can_be_disabled(): void
    {
        $this->save($this->admin(), ['lunch_enabled' => '0'])->assertSessionHasNoErrors();

        $this->assertContains('12:00', ClinicSchedule::bookableTimes());
        $this->assertContains('12:30', ClinicSchedule::bookableTimes());
        $this->assertSame('12:00', SystemSettings::get('lunch_start'), 'previous lunch times are kept');
    }

    public function test_patient_booking_follows_the_saved_schedule(): void
    {
        $doctor  = $this->doctor();
        $patient = $this->patientUser();
        $book = fn (string $time) => $this->actingAs($patient)->post(route('patient.appointments.store'), [
            'appointment_date' => $this->nextWeekday(), 'appointment_time' => $time,
            'doctor_id' => $doctor->id, 'reason' => 'Checkup',
        ]);

        // Defaults: lunch and after-hours rejected (server side, even with the dropdown bypassed).
        $book('12:00')->assertSessionHas('error', ClinicSchedule::INVALID_TIME_MESSAGE);
        $book('08:00')->assertSessionHas('error', ClinicSchedule::INVALID_TIME_MESSAGE);
        $book('09:10')->assertSessionHas('error', ClinicSchedule::INVALID_TIME_MESSAGE);
        $this->assertSame(0, Appointment::count());
        $book('10:00')->assertSessionMissing('error');
        $this->assertSame(1, Appointment::count());

        // Admin extends the day to 6 PM and shortens lunch: 17:00 is now bookable.
        $this->save($this->admin(), ['closing_time' => '18:00', 'lunch_start' => '12:00', 'lunch_end' => '12:30'])
            ->assertSessionHasNoErrors();
        $book('17:00')->assertSessionMissing('error');
        $book('12:30')->assertSessionMissing('error');
        $this->assertSame(3, Appointment::count());
        $book('12:00')->assertSessionHas('error');
    }

    public function test_patient_minimum_notice_and_maximum_window_are_enforced(): void
    {
        $doctor  = $this->doctor();
        $patient = $this->patientUser();
        $book = fn (string $date) => $this->actingAs($patient)->post(route('patient.appointments.store'), [
            'appointment_date' => $date, 'appointment_time' => '10:00',
            'doctor_id' => $doctor->id, 'reason' => 'Checkup',
        ]);

        $this->save($this->admin(), ['booking_min_notice_hours' => 72, 'booking_max_advance_days' => 10])
            ->assertSessionHasNoErrors();

        $book(now()->addDay()->toDateString())->assertSessionHas('error');                 // < 72 h
        $book(now()->addDays(30)->toDateString())->assertSessionHas('error');              // > 10 days
        $this->assertSame(0, Appointment::count());
        $book(now()->addDays(5)->toDateString())->assertSessionMissing('error');
        $this->assertSame(1, Appointment::count());
    }

    public function test_admin_scheduling_and_staff_reschedule_use_the_same_schedule(): void
    {
        $admin   = $this->admin();
        $doctor  = $this->doctor();
        $walkin  = Patient::create([
            'user_id' => null, 'first_name' => 'Juan', 'last_name' => 'Cruz', 'birthdate' => '1985-05-05',
            'gender' => 'Male', 'civil_status' => 'Single', 'contact_number' => '09181234567',
            'address' => 'Sample St', 'is_walk_in' => true,
        ]);
        $schedule = fn (string $time) => $this->actingAs($admin)->post(route('admin.patients.appointments.store'), [
            'patient_id' => $walkin->id, 'patient_type' => 'patient', 'doctor_id' => $doctor->id,
            'appointment_date' => $this->nextWeekday(), 'appointment_time' => $time, 'reason' => 'Checkup',
        ]);

        $schedule('12:00')->assertSessionHasErrors('appointment_time');
        $this->assertSame(0, Appointment::count());
        $schedule('09:00')->assertSessionHasNoErrors();
        $appt = Appointment::firstOrFail();

        // Reschedule (Admin inherits Staff logic) must also land on a valid slot.
        $reschedule = fn (string $time) => $this->actingAs($admin)->post(route('admin.appointments.reschedule', $appt->id), [
            'appointment_date' => $this->nextWeekday(), 'appointment_time' => $time, 'reschedule_reason' => 'Requested',
        ]);
        $reschedule('12:30')->assertSessionHas('error', ClinicSchedule::INVALID_TIME_MESSAGE);
        $this->assertSame('Approved', $appt->fresh()->status);
        $reschedule('14:00')->assertSessionHas('success');
        $this->assertSame('Rescheduled', $appt->fresh()->status);
    }

    public function test_no_show_waits_for_the_configured_appointment_length(): void
    {
        $admin  = $this->admin();
        $doctor = $this->doctor();
        $walkin = Patient::create([
            'user_id' => null, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'birthdate' => '1985-05-05',
            'gender' => 'Female', 'civil_status' => 'Single', 'contact_number' => '09181234568',
            'address' => 'Sample St', 'is_walk_in' => true,
        ]);
        // Appointment started 40 minutes ago.
        $start = now()->subMinutes(40);
        $appt = Appointment::create([
            'walkin_patient_id' => $walkin->id, 'doctor_id' => $doctor->id, 'status' => 'Approved', 'reason' => 'Checkup',
            'appointment_date' => $start->toDateString(), 'appointment_time' => $start->format('H:i'),
        ]);

        // 60-minute slots: appointment is still in progress -> No Show refused.
        SystemSettings::set(['slot_minutes' => 60]);
        $this->actingAs($admin)->post(route('admin.appointments.noShow', $appt->id))->assertSessionHas('error');
        $this->assertSame('Approved', $appt->fresh()->status);

        // 30-minute slots: it already ended -> allowed.
        SystemSettings::set(['slot_minutes' => 30]);
        $this->actingAs($admin)->post(route('admin.appointments.noShow', $appt->id))->assertSessionHas('success');
        $this->assertSame('No Show', $appt->fresh()->status);
    }

    // ------------------------------------------------------------------ notification settings drive real sending

    private function pendingAppointment(): Appointment
    {
        $patient = $this->patientUser();

        return Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $this->doctor()->id, 'status' => 'Pending',
            'reason' => 'Checkup', 'appointment_date' => $this->nextWeekday(), 'appointment_time' => '10:00',
        ]);
    }

    private function fakeSms(): void
    {
        config(['services.iprog_sms.url' => 'https://sms.test/send', 'services.iprog_sms.token' => 'secret-token']);
        Http::fake(['sms.test/*' => Http::response(['status' => 'ok'])]);
    }

    public function test_defaults_still_send_email_and_sms_on_approval_with_clinic_signature(): void
    {
        $this->fakeSms();
        $appt = $this->pendingAppointment();

        $this->actingAs($this->admin())->post(route('admin.appointments.approve', $appt->id))->assertSessionHas('success');

        Mail::assertSent(AppointmentStatusMail::class, 1);
        Http::assertSent(fn ($r) => str_ends_with($r['message'], '- Clinic RMS'));
    }

    public function test_sms_signature_uses_saved_clinic_name(): void
    {
        $this->fakeSms();
        $admin = $this->admin();
        $this->save($admin, ['clinic_name' => 'Sunrise Family Clinic'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.appointments.approve', $this->pendingAppointment()->id));

        Http::assertSent(fn ($r) => str_ends_with($r['message'], '- Sunrise Family Clinic'));
    }

    public function test_turning_email_off_stops_email_but_not_sms_and_never_blocks_the_workflow(): void
    {
        $this->fakeSms();
        $admin = $this->admin();
        $this->save($admin, ['notify_email_enabled' => '0'])->assertSessionHasNoErrors();
        $appt = $this->pendingAppointment();

        $this->actingAs($admin)->post(route('admin.appointments.approve', $appt->id))->assertSessionHas('success');

        $this->assertSame('Approved', $appt->fresh()->status);
        Mail::assertNothingSent();
        Http::assertSentCount(1);
    }

    public function test_turning_sms_off_stops_sms_but_not_email(): void
    {
        $this->fakeSms();
        $admin = $this->admin();
        $this->save($admin, ['notify_sms_enabled' => '0'])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.appointments.approve', $this->pendingAppointment()->id));

        Mail::assertSent(AppointmentStatusMail::class, 1);
        Http::assertNothingSent();
    }

    public function test_each_event_toggle_controls_only_its_own_event(): void
    {
        $this->fakeSms();
        $admin = $this->admin();
        // Silence only cancellation (both channels) and rejection SMS.
        $this->save($admin, [
            'notify_email_cancelled' => '0', 'notify_sms_cancelled' => '0', 'notify_sms_rejected' => '0',
        ])->assertSessionHasNoErrors();

        $cancel = $this->pendingAppointment();
        $this->actingAs($admin)->post(route('admin.appointments.cancel', $cancel->id))->assertSessionHas('success');
        Mail::assertNothingSent();
        Http::assertNothingSent();

        $reject = $this->pendingAppointment();
        $this->actingAs($admin)->post(route('admin.appointments.reject', $reject->id), ['reason' => 'Full'])->assertSessionHas('success');
        Mail::assertSent(AppointmentStatusMail::class, 1);   // rejection email still on
        Http::assertNothingSent();                            // rejection SMS off

        $approve = $this->pendingAppointment();
        $this->actingAs($admin)->post(route('admin.appointments.approve', $approve->id));
        Mail::assertSent(AppointmentStatusMail::class, 2);
        Http::assertSentCount(1);                             // approval SMS unaffected
    }

    public function test_notification_toggles_never_expose_secrets_in_the_page(): void
    {
        config(['services.iprog_sms.token' => 'super-secret-token']);

        $this->actingAs($this->admin())->get(route('admin.system-settings.index'))
            ->assertOk()->assertDontSee('super-secret-token')->assertDontSee(config('app.key'));
    }

    // ------------------------------------------------------------------ security (session timeout)

    public function test_session_timeout_is_saved_validated_and_applied_to_session_config(): void
    {
        $admin = $this->admin();
        $serverDefault = (int) config('session.lifetime');

        $this->save($admin, ['session_lifetime' => '30'])->assertSessionHasNoErrors();
        $this->assertSame(30, SystemSettings::sessionLifetime());

        // What AppServiceProvider::boot() runs at the start of each request,
        // before StartSession builds the session store / cookie.
        config(['session.lifetime' => $serverDefault]);
        SystemSettings::applySessionLifetime();
        $this->assertSame(30, (int) config('session.lifetime'));

        // Back to "server default": the .env value is left untouched.
        $this->save($admin, ['session_lifetime' => ''])->assertSessionHasNoErrors();
        config(['session.lifetime' => $serverDefault]);
        SystemSettings::applySessionLifetime();
        $this->assertSame($serverDefault, (int) config('session.lifetime'));
    }

    public function test_session_timeout_really_expires_idle_sessions_server_side(): void
    {
        // Use the real file session handler with the saved timeout.
        $dir = sys_get_temp_dir() . '/ss-sessions-' . uniqid();
        mkdir($dir);
        config(['session.driver' => 'file', 'session.files' => $dir]);

        SystemSettings::set(['session_lifetime' => 15]);
        SystemSettings::applySessionLifetime();
        $this->assertSame(15, (int) config('session.lifetime'));

        $handler = app('session')->driver('file')->getHandler();
        $handler->write('abc123', 'payload');
        $this->assertSame('payload', $handler->read('abc123'));

        touch($dir . '/abc123', time() - 16 * 60);   // idle 16 minutes > 15
        clearstatcache();
        $this->assertSame('', $handler->read('abc123'), 'expired session must not be readable');

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }
}