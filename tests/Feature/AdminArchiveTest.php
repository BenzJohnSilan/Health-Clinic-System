<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ArchiveEntry;
use App\Models\ContactMessage;
use App\Models\LandingItem;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\UserLog;
use App\Services\Archive\ArchiveDependencies;
use App\Services\LandingPageContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin > Archive: archive / restore / guarded permanent delete.
 * Runs on the in-memory SQLite database from phpunit.xml only.
 */
class AdminArchiveTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    // ------------------------------------------------------------------ fixtures

    private function makeUser(string $role, array $override = []): User
    {
        $this->seq++;

        $extra = $role === 'Patient' ? [
            'blood_type' => 'O+', 'allergies' => 'None', 'emergency_name' => 'Kin Test',
            'relationship' => 'Parent', 'emergency_contact_number' => '09170000000', 'emergency_address' => 'Sample St',
        ] : [];

        $user = User::create(array_merge([
            'first_name' => $role . $this->seq, 'last_name' => 'Tester', 'birthdate' => '1990-01-01',
            'gender' => 'Male', 'civil_status' => 'Single', 'address' => 'Sample St',
            'contact_number' => '0917' . str_pad((string) (5000000 + $this->seq), 7, '0', STR_PAD_LEFT),
            'username' => strtolower($role) . 'arc' . $this->seq,
            'email' => strtolower($role) . 'arc' . $this->seq . '@t.test',
            'password' => bcrypt('password'), 'role' => $role,
        ], $extra, $override));
        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'Approved', 'status' => 'Active'])->save();

        return $user;
    }

    private function admin(): User
    {
        return $this->makeUser('Admin');
    }

    private function walkin(string $first = 'Walk'): Patient
    {
        $this->seq++;

        return Patient::create([
            'first_name' => $first . $this->seq, 'last_name' => 'Person', 'birthdate' => '1985-05-05',
            'gender' => 'Female', 'civil_status' => 'Single', 'address' => 'Sample St',
            'contact_number' => '0918' . str_pad((string) (6000000 + $this->seq), 7, '0', STR_PAD_LEFT),
            'is_walk_in' => true,
        ]);
    }

    private function appt(User $doctor, $patient, string $status = 'Completed'): Appointment
    {
        $this->seq++;

        $row = [
            'doctor_id' => $doctor->id,
            'appointment_date' => now()->addDays($this->seq)->toDateString(),
            'appointment_time' => '09:00:00',
            'status' => $status, 'reason' => 'Checkup',
            'reference_no' => 'APT-ARC-' . str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
        ];
        $row[$patient instanceof Patient ? 'walkin_patient_id' : 'patient_id'] = $patient->id;

        return Appointment::create($row);
    }

    private function medicalRecord(Appointment $a): int
    {
        return DB::table('medical_records')->insertGetId([
            'doctor_id' => $a->doctor_id, 'appointment_id' => $a->id,
            'patient_id' => $a->patient_id, 'walkin_patient_id' => $a->walkin_patient_id,
            'diagnosis' => 'Test diagnosis', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function message(string $first = 'Maria', array $override = []): ContactMessage
    {
        $this->seq++;

        return ContactMessage::create(array_merge([
            'first_name' => $first, 'last_name' => 'Sender' . $this->seq,
            'email' => 'sender' . $this->seq . '@example.com', 'contact_number' => '09171234567',
            'message' => 'Hello, I would like to ask about your clinic hours.',
        ], $override));
    }

    private function service(string $name = 'Dental Cleaning', bool $active = true): Service
    {
        return Service::create(['name' => $name, 'description' => 'Test', 'price' => 500, 'is_active' => $active]);
    }

    private function medicine(string $name = 'Paracetamol'): Medicine
    {
        return Medicine::create([
            'medicine_name' => $name, 'brand' => 'Biogesic', 'category' => 'Analgesic', 'dosage' => '500mg',
            'quantity' => 10, 'unit' => 'tablet', 'price' => 5, 'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'Available',
        ]);
    }

    private function archiveAs(User $admin, string $type, int $id)
    {
        return $this->actingAs($admin)->post(route('admin.archive.store'), ['type' => $type, 'id' => $id]);
    }

    private function entry(string $type, int $id): ?ArchiveEntry
    {
        return ArchiveEntry::where('archivable_type', $type)->where('archivable_id', $id)->first();
    }

    // =====================================================================
    //  1-2. Access control
    // =====================================================================

    public function test_admin_can_open_the_archive_page_and_sees_the_empty_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.archive.index'))
            ->assertOk()
            ->assertSee('Archive')
            ->assertSee('Nothing archived yet')
            ->assertSee('Search name, reference number');
    }

    public function test_sidebar_has_an_archive_link(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.archive.index'), false);
    }

    public function test_staff_doctor_patient_and_guests_cannot_use_any_archive_action(): void
    {
        $admin   = $this->admin();
        $message = $this->message();
        $this->archiveAs($admin, 'contact_message', $message->id);
        $entry = $this->entry('contact_message', $message->id);
        $other = $this->message('Other');

        foreach (['Staff', 'Doctor', 'Patient'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->get(route('admin.archive.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.archive.store'), ['type' => 'contact_message', 'id' => $other->id])->assertForbidden();
            $this->actingAs($user)->post(route('admin.archive.restore', $entry))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.archive.destroy', $entry), ['confirmation' => 'DELETE'])->assertForbidden();
        }

        auth()->logout();
        $this->get(route('admin.archive.index'))->assertRedirect('/login');
        $this->post(route('admin.archive.store'), ['type' => 'contact_message', 'id' => $other->id])->assertRedirect('/login');

        // Nothing changed.
        $this->assertSame(1, ArchiveEntry::count());
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id]);
        $this->assertDatabaseHas('contact_messages', ['id' => $other->id]);
    }

    // =====================================================================
    //  3-5. Archive: eligible records, hidden from active lists, shown in Archive
    // =====================================================================

    public function test_a_staff_account_is_archived_by_deactivating_it_and_nothing_is_deleted(): void
    {
        $admin = $this->admin();
        $staff = $this->makeUser('Staff');

        $this->archiveAs($admin, 'user', $staff->id)->assertSessionHas('success');

        $staff->refresh();
        $this->assertSame('Inactive', $staff->status);
        $this->assertDatabaseHas('users', ['id' => $staff->id]);

        $entry = $this->entry('user', $staff->id);
        $this->assertNotNull($entry);
        $this->assertSame($admin->id, $entry->archived_by);
        $this->assertSame(['status' => 'Active'], $entry->state);
        $this->assertNotNull($entry->archived_at);
    }

    public function test_archived_records_leave_their_normal_lists_and_appear_on_the_archive_page(): void
    {
        $admin   = $this->admin();
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff', ['first_name' => 'Zelda']);
        $walkin  = $this->walkin('Wanda');
        $appt    = $this->appt($doctor, $walkin, 'Cancelled');
        $service = $this->service('Ear Wax Removal');
        $message = $this->message('Marites');
        $item    = LandingItem::create(['section' => 'faqs', 'title' => 'Parking available?', 'body' => 'Yes.', 'is_active' => true]);

        // Active lists show them first.
        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('Zelda');
        $this->actingAs($admin)->get(route('admin.patients.index'))->assertSee('Wanda');
        $this->actingAs($admin)->get(route('admin.appointments.index'))->assertSee($appt->reference_no);
        $this->actingAs($admin)->get(route('admin.services.index'))->assertSee('Ear Wax Removal');
        $this->actingAs($admin)->get(route('admin.contact-messages.index'))->assertSee('Marites');
        $this->actingAs($admin)->get(route('admin.website.index', ['tab' => 'faqs']))->assertSee('Parking available?');

        $this->archiveAs($admin, 'user', $staff->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'walkin_patient', $walkin->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'appointment', $appt->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'service', $service->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'contact_message', $message->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'landing_item', $item->id)->assertSessionHas('success');

        // Gone from the active lists...
        $this->actingAs($admin)->get(route('admin.users.index'))->assertDontSee('Zelda');
        $this->actingAs($admin)->get(route('admin.patients.index'))->assertDontSee('Wanda');
        $this->actingAs($admin)->get(route('admin.appointments.index'))->assertDontSee($appt->reference_no);
        $this->actingAs($admin)->get(route('admin.services.index'))->assertDontSee('Ear Wax Removal');
        $this->actingAs($admin)->get(route('admin.contact-messages.index'))->assertDontSee('Marites');
        $this->actingAs($admin)->get(route('admin.website.index', ['tab' => 'faqs']))->assertDontSee('Parking available?');

        // ...but every row is still in its table.
        foreach ([['users', $staff->id], ['patients', $walkin->id], ['appointments', $appt->id],
                  ['services', $service->id], ['contact_messages', $message->id], ['landing_items', $item->id]] as [$table, $id]) {
            $this->assertDatabaseHas($table, ['id' => $id]);
        }

        // ...and all six are on the Archive page.
        $this->actingAs($admin)->get(route('admin.archive.index'))
            ->assertOk()
            ->assertSee('Zelda')
            ->assertSee('Wanda')
            ->assertSee($appt->reference_no)
            ->assertSee('Ear Wax Removal')
            ->assertSee('Marites')
            ->assertSee('Parking available?')
            ->assertSee($admin->first_name);
    }

    public function test_archived_contact_message_leaves_the_unread_count_and_keeps_its_replies(): void
    {
        $admin   = $this->admin();
        $message = $this->message();
        $message->replies()->create(['user_id' => $admin->id, 'subject' => 'Re', 'body' => 'Thanks', 'sent_at' => now()]);

        $this->assertSame(1, ContactMessage::unread()->count());

        $this->archiveAs($admin, 'contact_message', $message->id);

        $this->assertSame(0, ContactMessage::unread()->count());
        $this->assertSame(1, DB::table('contact_message_replies')->where('contact_message_id', $message->id)->count());
        $this->actingAs($admin)->get(route('admin.contact-messages.show', $message->id))->assertNotFound();
    }

    public function test_archived_website_item_is_hidden_from_the_public_page_but_keeps_its_image_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('landing/promo.png', 'x');

        $admin = $this->admin();
        $item  = LandingItem::create([
            'section' => 'services', 'title' => 'Promo Checkup Package', 'body' => 'Great value.',
            'image_path' => 'landing/promo.png', 'is_active' => true,
        ]);
        LandingPageContent::forget();

        $this->get('/')->assertSee('Promo Checkup Package');

        $this->archiveAs($admin, 'landing_item', $item->id);

        $this->get('/')->assertDontSee('Promo Checkup Package');
        Storage::disk('public')->assertExists('landing/promo.png');
        $this->assertDatabaseHas('landing_items', ['id' => $item->id]);

        // Restore brings it back, same row.
        $this->actingAs($admin)->post(route('admin.archive.restore', $this->entry('landing_item', $item->id)));
        $this->get('/')->assertSee('Promo Checkup Package');
    }

    // =====================================================================
    //  Eligibility rules
    // =====================================================================

    public function test_accounts_that_must_not_be_archived_are_refused(): void
    {
        $admin  = $this->admin();
        $other  = $this->makeUser('Admin');
        $doctor = $this->makeUser('Doctor');
        $pending = $this->makeUser('Patient');
        $pending->forceFill(['approval_status' => 'Pending'])->save();

        $this->archiveAs($admin, 'user', $other->id)->assertSessionHas('error');
        $this->archiveAs($admin, 'user', $admin->id)->assertSessionHas('error');
        $this->archiveAs($admin, 'user', $pending->id)->assertSessionHas('error');

        $this->assertSame(0, ArchiveEntry::count());
        $this->assertSame('Active', $other->fresh()->status);
        $this->assertSame('Active', $admin->fresh()->status);
    }

    public function test_an_account_with_an_active_appointment_cannot_be_archived_until_it_is_finished(): void
    {
        $admin   = $this->admin();
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->appt($doctor, $patient, 'Approved');

        $this->archiveAs($admin, 'user', $patient->id)->assertSessionHas('error');
        $this->archiveAs($admin, 'user', $doctor->id)->assertSessionHas('error');
        $this->assertSame(0, ArchiveEntry::count());

        $appt->update(['status' => 'Completed']);

        $this->archiveAs($admin, 'user', $patient->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'user', $doctor->id)->assertSessionHas('success');
    }

    public function test_only_finished_appointments_can_be_archived(): void
    {
        $admin  = $this->admin();
        $doctor = $this->makeUser('Doctor');
        $walkin = $this->walkin();

        foreach (['Pending', 'Approved', 'Checked In', 'In Progress', 'Rescheduled', 'Waiting'] as $status) {
            $a = $this->appt($doctor, $walkin, $status);
            $this->archiveAs($admin, 'appointment', $a->id)->assertSessionHas('error');
            $this->assertNull($this->entry('appointment', $a->id), $status);
        }

        foreach (['Completed', 'Cancelled', 'Rejected', 'No Show'] as $status) {
            $a = $this->appt($doctor, $walkin, $status);
            $this->archiveAs($admin, 'appointment', $a->id)->assertSessionHas('success');
            $this->assertNotNull($this->entry('appointment', $a->id), $status);
        }
    }

    public function test_walk_in_patient_with_an_active_appointment_cannot_be_archived(): void
    {
        $admin  = $this->admin();
        $doctor = $this->makeUser('Doctor');
        $walkin = $this->walkin();
        $this->appt($doctor, $walkin, 'Pending');

        $this->archiveAs($admin, 'walkin_patient', $walkin->id)->assertSessionHas('error');
        $this->assertSame(0, ArchiveEntry::count());
    }

    public function test_services_used_automatically_by_billing_cannot_be_archived(): void
    {
        $admin = $this->admin();

        foreach ([Service::CONSULTATION, Service::MEDICAL_CERTIFICATE] as $name) {
            $s = $this->service($name);
            $this->archiveAs($admin, 'service', $s->id)->assertSessionHas('error');
            $this->assertTrue((bool) $s->fresh()->is_active);
        }

        $this->assertSame(0, ArchiveEntry::count());
    }

    // =====================================================================
    //  6-7. Restore keeps the same ID and relationships
    // =====================================================================

    public function test_restore_puts_the_account_back_as_it_was(): void
    {
        $admin = $this->admin();
        $staff = $this->makeUser('Staff');

        $this->archiveAs($admin, 'user', $staff->id);
        $this->assertSame('Inactive', $staff->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.archive.restore', $this->entry('user', $staff->id)))
            ->assertRedirect(route('admin.archive.index'))
            ->assertSessionHas('success');

        $this->assertSame('Active', $staff->fresh()->status);
        $this->assertNull($this->entry('user', $staff->id));
        $this->assertSame(1, User::where('username', $staff->username)->count()); // no duplicate
    }

    public function test_an_account_that_was_already_inactive_is_restored_as_inactive(): void
    {
        $admin = $this->admin();
        $staff = $this->makeUser('Staff');
        $staff->forceFill(['status' => 'Inactive'])->save();

        $this->archiveAs($admin, 'user', $staff->id);
        $this->actingAs($admin)->post(route('admin.archive.restore', $this->entry('user', $staff->id)));

        $this->assertSame('Inactive', $staff->fresh()->status);
    }

    public function test_restoring_an_appointment_and_a_service_keeps_ids_and_relationships(): void
    {
        $admin   = $this->admin();
        $doctor  = $this->makeUser('Doctor');
        $walkin  = $this->walkin();
        $appt    = $this->appt($doctor, $walkin, 'Completed');
        $recordId = $this->medicalRecord($appt);
        $service = $this->service('X-Ray');
        $invoiceId = DB::table('invoices')->insertGetId([
            'invoice_no' => 'INV-ARC-1', 'appointment_id' => $appt->id, 'walkin_patient_id' => $walkin->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoiceId, 'service_id' => $service->id, 'item_type' => 'service',
            'description' => 'X-Ray', 'quantity' => 1, 'unit_price' => 500, 'amount' => 500,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->archiveAs($admin, 'appointment', $appt->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'service', $service->id)->assertSessionHas('success');

        // While archived, history is untouched.
        $this->assertDatabaseHas('medical_records', ['id' => $recordId, 'appointment_id' => $appt->id]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoiceId, 'service_id' => $service->id]);
        $this->assertFalse((bool) $service->fresh()->is_active);

        $this->actingAs($admin)->post(route('admin.archive.restore', $this->entry('appointment', $appt->id)))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.archive.restore', $this->entry('service', $service->id)))->assertSessionHas('success');

        $this->assertSame($appt->id, Appointment::where('reference_no', $appt->reference_no)->value('id'));
        $this->assertDatabaseHas('medical_records', ['id' => $recordId, 'appointment_id' => $appt->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'appointment_id' => $appt->id]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoiceId, 'service_id' => $service->id]);
        $this->assertTrue((bool) $service->fresh()->is_active);
        $this->assertSame(0, ArchiveEntry::count());
        $this->actingAs($admin)->get(route('admin.appointments.index'))->assertSee($appt->reference_no);
    }

    public function test_restore_is_refused_when_it_would_create_a_duplicate_service(): void
    {
        $admin = $this->admin();
        $old   = $this->service('Nebulizer');

        $this->archiveAs($admin, 'service', $old->id);
        $this->service('Nebulizer'); // someone created a new active one meanwhile

        $this->actingAs($admin)->post(route('admin.archive.restore', $this->entry('service', $old->id)))
            ->assertSessionHas('error');

        $this->assertNotNull($this->entry('service', $old->id));
        $this->assertFalse((bool) $old->fresh()->is_active);
    }

    // =====================================================================
    //  8-9. Duplicates, invalid types, missing / forged records
    // =====================================================================

    public function test_archiving_the_same_record_twice_is_handled_safely(): void
    {
        $admin   = $this->admin();
        $message = $this->message();

        $this->archiveAs($admin, 'contact_message', $message->id)->assertSessionHas('success');
        $this->archiveAs($admin, 'contact_message', $message->id)->assertSessionHas('error');

        $this->assertSame(1, ArchiveEntry::count());
        $this->assertSame(1, UserLog::where('action', 'Archived Record')->count());
    }

    public function test_the_database_itself_refuses_a_duplicate_archive_entry(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        foreach ([1, 2] as $_) {
            ArchiveEntry::create(['archivable_type' => 'service', 'archivable_id' => 5, 'label' => 'dup', 'archived_at' => now()]);
        }
    }

    public function test_invalid_types_and_missing_records_are_rejected(): void
    {
        $admin = $this->admin();

        // Protected / unknown modules are not valid archive targets at all.
        foreach (['invoice', 'payment', 'medical_record', 'prescription', 'user_log', 'permission', 'backup', 'nonsense', ''] as $type) {
            $this->actingAs($admin)->post(route('admin.archive.store'), ['type' => $type, 'id' => 1])
                ->assertSessionHasErrors('type');
        }

        // Bad ids.
        foreach (['abc', '0', '-4', '1.5', ''] as $id) {
            $this->actingAs($admin)->post(route('admin.archive.store'), ['type' => 'service', 'id' => $id])
                ->assertSessionHasErrors('id');
        }

        // Valid type, record does not exist.
        $this->archiveAs($admin, 'user', 987654)->assertSessionHas('error');
        $this->archiveAs($admin, 'appointment', 987654)->assertSessionHas('error');

        // A "walk-in patient" id that is really a registered patient's profile row is not accepted.
        $user    = $this->makeUser('Patient');
        $profile = Patient::create(['user_id' => $user->id, 'first_name' => 'Reg', 'last_name' => 'User', 'is_walk_in' => false]);
        $this->archiveAs($admin, 'walkin_patient', $profile->id)->assertSessionHas('error');

        $this->assertSame(0, ArchiveEntry::count());
        $this->assertSame(0, UserLog::where('module', 'Archive')->count());
    }

    public function test_forged_archive_entry_ids_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/archive/99999/recover')->assertNotFound();
        $this->actingAs($admin)->delete('/admin/archive/99999', ['confirmation' => 'DELETE'])->assertNotFound();
        $this->actingAs($admin)->post('/admin/archive/abc/recover')->assertNotFound();
        $this->actingAs($admin)->delete('/admin/archive/1%20OR%201=1', ['confirmation' => 'DELETE'])->assertNotFound();

        // State-changing endpoints do not answer GET.
        $message = $this->message();
        $this->archiveAs($admin, 'contact_message', $message->id);
        $entry = $this->entry('contact_message', $message->id);
        $this->actingAs($admin)->get('/admin/archive/' . $entry->id . '/recover')->assertStatus(405);
        $this->actingAs($admin)->get('/admin/archive/' . $entry->id)->assertStatus(405);
        $this->assertNotNull($this->entry('contact_message', $message->id));
    }

    public function test_an_archive_entry_with_an_unknown_type_cannot_be_acted_on(): void
    {
        $admin = $this->admin();
        $entry = ArchiveEntry::create(['archivable_type' => 'invoice', 'archivable_id' => 1, 'label' => 'Forged', 'archived_at' => now()]);

        $this->actingAs($admin)->post(route('admin.archive.restore', $entry))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $entry), ['confirmation' => 'DELETE'])->assertSessionHas('error');

        $this->assertNotNull($entry->fresh());
        $this->actingAs($admin)->get(route('admin.archive.index'))->assertOk()->assertSee('Forged');
    }

    public function test_a_stale_entry_whose_record_is_gone_is_cleared_without_error_pages(): void
    {
        $admin = $this->admin();
        $stale = ArchiveEntry::create(['archivable_type' => 'service', 'archivable_id' => 424242, 'label' => 'Ghost', 'archived_at' => now()]);

        $this->actingAs($admin)->post(route('admin.archive.restore', $stale))->assertSessionHas('error');
        $this->assertNull($stale->fresh());

        $stale2 = ArchiveEntry::create(['archivable_type' => 'service', 'archivable_id' => 424243, 'label' => 'Ghost2', 'archived_at' => now()]);
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $stale2), ['confirmation' => 'DELETE'])->assertSessionHas('success');
        $this->assertNull($stale2->fresh());
    }

    // =====================================================================
    //  10-11. Permanent delete: confirmation + protected history
    // =====================================================================

    public function test_permanent_delete_needs_the_typed_confirmation(): void
    {
        $admin   = $this->admin();
        $message = $this->message();
        $this->archiveAs($admin, 'contact_message', $message->id);
        $entry = $this->entry('contact_message', $message->id);

        $this->actingAs($admin)->delete(route('admin.archive.destroy', $entry))->assertSessionHasErrors('confirmation');
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $entry), ['confirmation' => 'delete'])->assertSessionHasErrors('confirmation');
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $entry), ['confirmation' => 'yes'])->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('contact_messages', ['id' => $message->id]);
        $this->assertNotNull($entry->fresh());

        $this->actingAs($admin)->delete(route('admin.archive.destroy', $entry), ['confirmation' => 'DELETE'])
            ->assertRedirect(route('admin.archive.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
        $this->assertNull($entry->fresh());
    }

    public function test_the_confirmation_dialog_names_the_record_and_warns_it_cannot_be_undone(): void
    {
        $admin = $this->admin();
        $this->archiveAs($admin, 'contact_message', $this->message('Konfirm')->id);

        $this->actingAs($admin)->get(route('admin.archive.index'))
            ->assertSee('cannot be undone')
            ->assertSee('Type')
            ->assertSee('data-label="Konfirm', false);
    }

    public function test_permanently_deleting_a_contact_message_removes_its_replies_too(): void
    {
        $admin   = $this->admin();
        $message = $this->message();
        $message->replies()->create(['user_id' => $admin->id, 'subject' => 'Re', 'body' => 'Thanks', 'sent_at' => now()]);
        $this->archiveAs($admin, 'contact_message', $message->id);

        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('contact_message', $message->id)), ['confirmation' => 'DELETE']);

        $this->assertSame(0, DB::table('contact_message_replies')->where('contact_message_id', $message->id)->count());
    }

    public function test_permanently_deleting_a_website_item_removes_its_image_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('landing/old.png', 'x');

        $admin = $this->admin();
        $item  = LandingItem::create(['section' => 'features', 'title' => 'Old', 'image_path' => 'landing/old.png', 'is_active' => true]);

        $this->archiveAs($admin, 'landing_item', $item->id);
        Storage::disk('public')->assertExists('landing/old.png');

        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('landing_item', $item->id)), ['confirmation' => 'DELETE']);

        $this->assertDatabaseMissing('landing_items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing('landing/old.png');
    }

    public function test_protected_history_blocks_permanent_delete_of_an_appointment(): void
    {
        $admin  = $this->admin();
        $doctor = $this->makeUser('Doctor');
        $walkin = $this->walkin();
        $appt   = $this->appt($doctor, $walkin, 'Completed');
        $recordId = $this->medicalRecord($appt);
        DB::table('prescriptions')->insert([
            'appointment_id' => $appt->id, 'dosage' => '1 tab', 'frequency' => 'daily', 'duration' => '3 days',
            'quantity_prescribed' => 3, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('invoices')->insertGetId([
            'invoice_no' => 'INV-ARC-2', 'appointment_id' => $appt->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'invoice_id' => $invoiceId, 'amount' => 100, 'payment_method' => 'Cash', 'paid_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->archiveAs($admin, 'appointment', $appt->id)->assertSessionHas('success');
        $entry = $this->entry('appointment', $appt->id);

        $this->actingAs($admin)->delete(route('admin.archive.destroy', $entry), ['confirmation' => 'DELETE'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('appointments', ['id' => $appt->id]);
        $this->assertDatabaseHas('medical_records', ['id' => $recordId]);
        $this->assertDatabaseHas('prescriptions', ['appointment_id' => $appt->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoiceId]);
        $this->assertSame(1, DB::table('payments')->where('invoice_id', $invoiceId)->count());
        $this->assertNotNull($entry->fresh());

        // The page explains why and does not offer the delete button for it.
        $this->actingAs($admin)->get(route('admin.archive.index'))
            ->assertSee('Protected')
            ->assertSee('medical records')
            ->assertDontSee('data-action=', false);   // no Permanent Delete button on the row
    }

    public function test_an_appointment_with_no_linked_history_can_be_permanently_deleted(): void
    {
        $admin  = $this->admin();
        $doctor = $this->makeUser('Doctor');
        $walkin = $this->walkin();
        $appt   = $this->appt($doctor, $walkin, 'Cancelled');

        $this->archiveAs($admin, 'appointment', $appt->id);
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('appointment', $appt->id)), ['confirmation' => 'DELETE'])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('appointments', ['id' => $appt->id]);
        $this->assertDatabaseHas('patients', ['id' => $walkin->id]);
    }

    public function test_a_service_on_past_invoices_cannot_be_permanently_deleted(): void
    {
        $admin   = $this->admin();
        $service = $this->service('Suturing');
        $invoiceId = DB::table('invoices')->insertGetId(['invoice_no' => 'INV-ARC-3', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoiceId, 'service_id' => $service->id, 'item_type' => 'service',
            'description' => 'Suturing', 'quantity' => 1, 'unit_price' => 500, 'amount' => 500,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->archiveAs($admin, 'service', $service->id);
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('service', $service->id)), ['confirmation' => 'DELETE'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('services', ['id' => $service->id]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoiceId, 'service_id' => $service->id]);
    }

    public function test_a_service_that_was_never_used_can_be_permanently_deleted(): void
    {
        $admin   = $this->admin();
        $service = $this->service('Unused');

        $this->archiveAs($admin, 'service', $service->id);
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('service', $service->id)), ['confirmation' => 'DELETE'])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_walk_in_patient_with_history_cannot_be_permanently_deleted(): void
    {
        $admin  = $this->admin();
        $doctor = $this->makeUser('Doctor');
        $walkin = $this->walkin();
        $appt   = $this->appt($doctor, $walkin, 'Completed');

        $this->archiveAs($admin, 'walkin_patient', $walkin->id)->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('walkin_patient', $walkin->id)), ['confirmation' => 'DELETE'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('patients', ['id' => $walkin->id]);
        $this->assertSame($walkin->id, $appt->fresh()->walkin_patient_id);
    }

    // =====================================================================
    //  12. User deletion never cascades into appointment / medical history
    // =====================================================================

    public function test_deleting_a_user_model_with_history_is_refused_and_nothing_cascades(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->appt($doctor, $patient, 'Completed');
        $recordId = $this->medicalRecord($appt);

        foreach ([$patient, $doctor] as $user) {
            try {
                $user->delete();
                $this->fail('Deleting a user with history must be refused.');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('Archive', $e->getMessage());
            }
        }

        $this->assertDatabaseHas('users', ['id' => $patient->id]);
        $this->assertDatabaseHas('users', ['id' => $doctor->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appt->id]);
        $this->assertDatabaseHas('medical_records', ['id' => $recordId]);
    }

    public function test_the_manage_users_delete_button_archives_and_keeps_all_history(): void
    {
        $admin   = $this->admin();
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->appt($doctor, $patient, 'Completed');
        $recordId = $this->medicalRecord($appt);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $patient->id))->assertSessionHas('success');

        $this->assertSame('Inactive', $patient->fresh()->status);
        $this->assertDatabaseHas('appointments', ['id' => $appt->id, 'patient_id' => $patient->id]);
        $this->assertDatabaseHas('medical_records', ['id' => $recordId]);
        $this->assertNotNull($this->entry('user', $patient->id));
        $listed = $this->actingAs($admin)->get(route('admin.users.index'))->viewData('users')->pluck('id');
        $this->assertFalse($listed->contains($patient->id));
        $this->assertTrue($listed->contains($doctor->id));
    }

    public function test_an_archived_account_is_deactivated_and_its_sessions_are_removed(): void
    {
        $admin = $this->admin();
        $staff = $this->makeUser('Staff');

        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);

        $this->archiveAs($admin, 'user', $staff->id);

        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
        $this->assertSame('Inactive', $staff->fresh()->status);
    }

    public function test_an_account_with_history_or_logs_cannot_be_permanently_deleted(): void
    {
        $admin   = $this->admin();
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->appt($doctor, $patient, 'Completed');
        $quiet   = $this->makeUser('Staff');
        $logged  = $this->makeUser('Staff');
        UserLog::create(['user_id' => $logged->id, 'action' => 'Did something', 'module' => 'Appointments']);

        foreach ([$patient, $doctor, $logged, $quiet] as $u) {
            $this->archiveAs($admin, 'user', $u->id)->assertSessionHas('success');
        }

        foreach ([$patient, $doctor, $logged] as $u) {
            $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('user', $u->id)), ['confirmation' => 'DELETE'])
                ->assertSessionHas('error');
            $this->assertDatabaseHas('users', ['id' => $u->id]);
        }

        $this->assertSame(1, UserLog::where('user_id', $logged->id)->count()); // audit trail survived

        // A never-used account has nothing to protect.
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('user', $quiet->id)), ['confirmation' => 'DELETE'])
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $quiet->id]);
    }

    public function test_rejecting_a_new_registration_still_works(): void
    {
        $admin = $this->admin();
        $new   = $this->makeUser('Patient');
        $new->forceFill(['approval_status' => 'Pending'])->save();

        \Illuminate\Support\Facades\Mail::fake();

        $this->actingAs($admin)->post(route('admin.reject', $new->id), ['reason' => 'Blurry ID'])->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $new->id]);
    }

    // =====================================================================
    //  Existing Appointment / Service / Contact delete buttons now archive
    // =====================================================================

    public function test_admin_delete_appointment_button_archives_and_keeps_medical_history(): void
    {
        $admin  = $this->admin();
        $doctor = $this->makeUser('Doctor');
        $walkin = $this->walkin();
        $appt   = $this->appt($doctor, $walkin, 'Completed');
        $recordId = $this->medicalRecord($appt);

        $this->actingAs($admin)->delete(route('admin.appointments.destroy', $appt->id))->assertSessionHas('success');

        $this->assertDatabaseHas('appointments', ['id' => $appt->id]);
        $this->assertDatabaseHas('medical_records', ['id' => $recordId]);
        $this->assertNotNull($this->entry('appointment', $appt->id));

        $live = $this->appt($doctor, $walkin, 'Approved');
        $this->actingAs($admin)->delete(route('admin.appointments.destroy', $live->id))->assertSessionHas('error');
        $this->assertNull($this->entry('appointment', $live->id));
    }

    public function test_admin_delete_service_and_message_buttons_archive_instead_of_deleting(): void
    {
        $admin   = $this->admin();
        $service = $this->service('Wound Dressing');
        $message = $this->message();

        $this->actingAs($admin)->delete(route('admin.services.destroy', $service->id))->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('admin.contact-messages.destroy', $message->id))->assertSessionHas('success');

        $this->assertDatabaseHas('services', ['id' => $service->id]);
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id]);
        $this->assertNotNull($this->entry('service', $service->id));
        $this->assertNotNull($this->entry('contact_message', $message->id));

        // An archived service cannot be edited until it is restored.
        $this->actingAs($admin)->put(route('admin.services.update', $service->id), ['name' => 'Renamed', 'price' => 10])->assertNotFound();
    }

    // =====================================================================
    //  14. Audit trail
    // =====================================================================

    public function test_archive_restore_and_delete_are_written_to_the_audit_log(): void
    {
        $admin    = $this->admin();
        $service  = $this->service('Audit Me');
        $used     = $this->service('Used Service');
        $invoice  = DB::table('invoices')->insertGetId(['invoice_no' => 'INV-ARC-4', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoice, 'service_id' => $used->id, 'item_type' => 'service', 'description' => 'x',
            'quantity' => 1, 'unit_price' => 1, 'amount' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->archiveAs($admin, 'service', $service->id);
        $this->actingAs($admin)->post(route('admin.archive.restore', $this->entry('service', $service->id)));
        $this->archiveAs($admin, 'service', $service->id);
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('service', $service->id)), ['confirmation' => 'DELETE']);

        $this->archiveAs($admin, 'service', $used->id);
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('service', $used->id)), ['confirmation' => 'DELETE']);

        foreach (['Archived Record', 'Restored Record', 'Permanently Deleted Record', 'Permanent Delete Blocked'] as $action) {
            $log = UserLog::where('action', $action)->first();
            $this->assertNotNull($log, $action);
            $this->assertSame($admin->id, $log->user_id);
            $this->assertSame('Archive', $log->module);
            $this->assertStringContainsString('Service #', $log->details);
        }

        $this->assertSame(2, UserLog::where('action', 'Archived Record')->where('details', 'like', '%Audit Me%')->count());

        // Logs never carry credentials.
        $this->assertSame(0, UserLog::where('module', 'Archive')->where('details', 'like', '%password%')->count());

        // The audit trail is still there after the record is gone.
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertTrue(UserLog::where('action', 'Permanently Deleted Record')->where('details', 'like', '%Audit Me%')->exists());
    }

    // =====================================================================
    //  15. Medicines keep their existing soft-delete behaviour
    // =====================================================================

    public function test_medicine_inventory_delete_is_still_a_soft_delete_and_shows_in_the_archive(): void
    {
        $admin = $this->admin();
        $med   = $this->medicine('Amoxicillin');
        DB::table('medicine_stock_movements')->insert([
            'medicine_id' => $med->id, 'movement_type' => 'stock_in', 'quantity_change' => 10,
            'quantity_before' => 0, 'quantity_after' => 10, 'reason' => 'Initial', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('admin.medicines.destroy', $med->id));

        $this->assertSoftDeleted('medicines', ['id' => $med->id]);
        $this->assertSame(1, DB::table('medicine_stock_movements')->where('medicine_id', $med->id)->count());

        $entry = $this->entry('medicine', $med->id);
        $this->assertNotNull($entry);
        $this->assertSame($admin->id, $entry->archived_by);
        $this->actingAs($admin)->get(route('admin.archive.index'))->assertSee('Amoxicillin');

        // Stock history blocks a permanent delete.
        $this->actingAs($admin)->delete(route('admin.archive.destroy', $entry), ['confirmation' => 'DELETE'])->assertSessionHas('error');
        $this->assertSoftDeleted('medicines', ['id' => $med->id]);

        // Restore = the model's own restore().
        $this->actingAs($admin)->post(route('admin.archive.restore', $entry))->assertSessionHas('success');
        $this->assertNotSoftDeleted('medicines', ['id' => $med->id]);
        $this->assertNull($this->entry('medicine', $med->id));
        $this->assertSame(1, DB::table('medicine_stock_movements')->where('medicine_id', $med->id)->count());
    }

    public function test_a_medicine_with_no_history_can_be_permanently_deleted_and_a_duplicate_restore_is_refused(): void
    {
        $admin = $this->admin();
        $lone  = $this->medicine('Lonely Med');
        $this->archiveAs($admin, 'medicine', $lone->id)->assertSessionHas('success');
        $this->assertSoftDeleted('medicines', ['id' => $lone->id]);

        $this->actingAs($admin)->delete(route('admin.archive.destroy', $this->entry('medicine', $lone->id)), ['confirmation' => 'DELETE'])
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('medicines', ['id' => $lone->id]);

        $old = $this->medicine('Cetirizine');
        $this->archiveAs($admin, 'medicine', $old->id);
        $this->medicine('Cetirizine'); // same name/brand/dosage created meanwhile
        $this->actingAs($admin)->post(route('admin.archive.restore', $this->entry('medicine', $old->id)))->assertSessionHas('error');
        $this->assertSoftDeleted('medicines', ['id' => $old->id]);
    }

    // =====================================================================
    //  16. Search, filters, sorting, pagination
    // =====================================================================

    public function test_search_filter_sort_and_pagination_work(): void
    {
        $admin = $this->admin();

        for ($i = 1; $i <= 12; $i++) {
            $m = $this->message('Batch' . str_pad((string) $i, 2, '0', STR_PAD_LEFT));
            $this->archiveAs($admin, 'contact_message', $m->id);
            $this->entry('contact_message', $m->id)->update(['archived_at' => now()->subDays(20 - $i)]);
        }
        $service = $this->service('Special Rinse');
        $this->archiveAs($admin, 'service', $service->id);
        $this->entry('service', $service->id)->update(['archived_at' => now()->subDays(100)]);

        // Pagination: 13 entries, 10 per page.
        $page1 = $this->actingAs($admin)->get(route('admin.archive.index'));
        $page1->assertSee('Showing')->assertSee('of')->assertSee('13');
        $this->assertSame(10, $page1->viewData('entries')->count());
        $this->assertSame(3, $this->actingAs($admin)->get(route('admin.archive.index', ['page' => 2]))->viewData('entries')->count());

        // Newest first by default, oldest first on request.
        $this->assertSame('Batch12', explode(' ', $page1->viewData('entries')->first()->label)[0]);
        $oldest = $this->actingAs($admin)->get(route('admin.archive.index', ['sort' => 'oldest']))->viewData('entries');
        $this->assertSame('Special Rinse', $oldest->first()->label);

        // Module filter.
        $svc = $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'service']));
        $this->assertSame(1, $svc->viewData('entries')->total());
        $svc->assertSee('Special Rinse')->assertDontSee('Batch01');

        // Search by name / identifier / sub label / who archived.
        $this->assertSame(1, $this->actingAs($admin)->get(route('admin.archive.index', ['search' => 'Batch07']))->viewData('entries')->total());
        $this->assertSame(1, $this->actingAs($admin)->get(route('admin.archive.index', ['search' => 'Rinse']))->viewData('entries')->total());
        $this->assertSame(13, $this->actingAs($admin)->get(route('admin.archive.index', ['search' => $admin->first_name]))->viewData('entries')->total());
        $this->actingAs($admin)->get(route('admin.archive.index', ['search' => 'zzz-nothing']))->assertSee('No archived records match');

        // Search treats % and _ literally.
        $this->assertSame(0, $this->actingAs($admin)->get(route('admin.archive.index', ['search' => '%']))->viewData('entries')->total());

        // Date range.
        $range = $this->actingAs($admin)->get(route('admin.archive.index', [
            'from' => now()->subDays(10)->toDateString(), 'to' => now()->toDateString(),
        ]))->viewData('entries');
        $this->assertTrue($range->total() > 0 && $range->total() < 13);
        $this->assertSame(1, $this->actingAs($admin)->get(route('admin.archive.index', [
            'from' => now()->subDays(101)->toDateString(), 'to' => now()->subDays(99)->toDateString(),
        ]))->viewData('entries')->total());

        // Bad filter values are rejected, not run.
        $this->actingAs($admin)->get(route('admin.archive.index', ['type' => 'invoice']))->assertSessionHasErrors('type');
        $this->actingAs($admin)->get(route('admin.archive.index', ['sort' => 'DROP TABLE']))->assertSessionHasErrors('sort');
    }

    // =====================================================================
    //  Schema drift guard
    // =====================================================================

    public function test_every_foreign_key_into_an_archivable_table_is_accounted_for(): void
    {
        $known = [];   // "table.column" => true
        foreach (ArchiveDependencies::MAP as $type => $def) {
            foreach ($def['refs'] as [$table, $column]) {
                $known["{$def['table']}<-{$table}.{$column}"] = true;
            }
            foreach ($def['owned'] as $table) {
                $known["{$def['table']}<-{$table}.*"] = true;
            }
        }

        $targets = array_column(ArchiveDependencies::MAP, 'table');
        $missing = [];

        foreach (Schema::getTableListing() as $table) {
            $table = preg_replace('/^[^.]+\./', '', $table);

            foreach (Schema::getForeignKeys($table) as $fk) {
                if (! in_array($fk['foreign_table'], $targets, true)) {
                    continue;
                }
                foreach ($fk['columns'] as $column) {
                    $key = "{$fk['foreign_table']}<-{$table}.{$column}";
                    if (! isset($known[$key]) && ! isset($known["{$fk['foreign_table']}<-{$table}.*"])) {
                        $missing[] = $key;
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Add these foreign keys to ArchiveDependencies::MAP so permanent delete stays safe.');
    }
}
