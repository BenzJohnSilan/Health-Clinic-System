<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Mail\ContactMessageReply;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Landing page Contact Us form -> database + email notification, and the
 * Admin > Contact Messages pages (access control, read/unread, resend).
 */
class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.contact_notification_email' => 'clinic@inbox.test']);
    }

    // ------------------------------------------------------------------ fixtures

    private function makeUser(string $role): User
    {
        $this->seq++;

        $extra = $role === 'Patient' ? [
            'blood_type' => 'O+', 'allergies' => 'None', 'emergency_name' => 'Kin Test',
            'relationship' => 'Parent', 'emergency_contact_number' => '09170000000', 'emergency_address' => 'Sample St',
        ] : [];

        $user = User::create(array_merge([
            'first_name' => $role, 'last_name' => 'Test', 'birthdate' => '1990-01-01',
            'gender' => 'Male', 'civil_status' => 'Single', 'address' => 'Sample St',
            'contact_number' => '0917' . str_pad((string) (4000000 + $this->seq), 7, '0', STR_PAD_LEFT),
            'username' => strtolower($role) . 'cm' . $this->seq,
            'email' => strtolower($role) . 'cm' . $this->seq . '@t.test',
            'password' => bcrypt('password'), 'role' => $role,
        ], $extra));
        $user->forceFill(['email_verified_at' => now(), 'approval_status' => 'Approved', 'status' => 'Active'])->save();

        return $user;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'first_name'     => 'Maria',
            'last_name'      => 'Dela Cruz',
            'email'          => 'maria@example.com',
            'contact_number' => '0917 123 4567',
            'message'        => 'Hello, what are your clinic hours on Saturdays?',
        ], $override);
    }

    private function inquiry(array $override = []): ContactMessage
    {
        return ContactMessage::create(array_merge([
            'first_name' => 'Juan', 'last_name' => 'Santos', 'email' => 'juan@example.com',
            'contact_number' => '09171234567', 'message' => 'Do you accept walk-ins?',
            'status' => 'unread',
        ], $override));
    }

    // ------------------------------------------------------------------ 1. saved

    public function test_valid_submission_is_saved_and_shows_success_message(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), $this->payload());

        $response->assertRedirect('/#contact');
        $response->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_messages', [
            'first_name' => 'Maria', 'last_name' => 'Dela Cruz', 'email' => 'maria@example.com',
            'contact_number' => '0917 123 4567', 'status' => 'unread',
        ]);
        $this->assertSame(1, ContactMessage::count());
    }

    // ------------------------------------------------------------------ 2. invalid

    public function test_invalid_submission_is_rejected_with_field_errors_and_keeps_input(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'first_name' => '', 'last_name' => 'Cruz1', 'email' => 'not-an-email',
            'contact_number' => 'abc', 'message' => 'short',
        ]);

        $response->assertRedirect('/#contact');
        $response->assertSessionHasErrors(['first_name', 'last_name', 'email', 'contact_number', 'message']);
        $response->assertSessionHasInput('email', 'not-an-email');
        $response->assertSessionHasInput('message', 'short');

        $this->assertSame(0, ContactMessage::count());
        Mail::assertNothingSent();
    }

    public function test_contact_number_must_have_a_realistic_number_of_digits(): void
    {
        $this->post(route('contact.store'), $this->payload(['contact_number' => '12345']))
            ->assertSessionHasErrors('contact_number');

        $this->post(route('contact.store'), $this->payload(['contact_number' => '+63 (917) 123-4567']))
            ->assertSessionDoesntHaveErrors('contact_number');
    }

    public function test_errors_and_old_input_are_rendered_beside_the_fields_on_the_landing_page(): void
    {
        $this->from('/')->post(route('contact.store'), $this->payload(['email' => 'bad', 'first_name' => 'Maria']));

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Please enter a valid email address.', $html);
        $this->assertStringContainsString('value="Maria"', $html);
    }

    // ------------------------------------------------------------------ 3. contact number, not subject

    public function test_form_uses_contact_number_instead_of_subject(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('name="contact_number"', $html);
        $this->assertStringContainsString('Contact Number', $html);
        $this->assertStringNotContainsString('name="subject"', $html);
        $this->assertStringNotContainsString('How can we help you?', $html);
        $this->assertStringContainsString('name="_token"', $html); // CSRF
    }

    public function test_table_has_no_subject_column_and_a_posted_subject_is_ignored(): void
    {
        Mail::fake();

        $this->assertFalse(Schema::hasColumn('contact_messages', 'subject'));
        $this->assertTrue(Schema::hasColumn('contact_messages', 'contact_number'));

        $this->post(route('contact.store'), $this->payload(['subject' => 'Ignore me']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ContactMessage::count());
        $this->assertArrayNotHasKey('subject', ContactMessage::first()->getAttributes());
    }

    public function test_migration_keeps_old_inquiries_when_a_legacy_subject_table_exists(): void
    {
        Schema::drop('contact_messages');
        Schema::create('contact_messages', function ($table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->timestamps();
        });
        \DB::table('contact_messages')->insert([
            'first_name' => 'Old', 'last_name' => 'Sender', 'email' => 'old@example.com',
            'subject' => 'Old subject', 'message' => 'Old message', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Find the migration by name pattern so the test works whatever
        // timestamp the file ended up with in this project.
        $files = glob(database_path('migrations/*_create_contact_messages_table.php'));
        $this->assertNotEmpty($files, 'The create_contact_messages_table migration file was not found.');

        $migration = require $files[0];
        $migration->up();

        $this->assertSame(1, \DB::table('contact_messages')->count());
        $this->assertSame('Old subject', \DB::table('contact_messages')->value('subject'));
        $this->assertTrue(Schema::hasColumn('contact_messages', 'contact_number'));
        $this->assertTrue(Schema::hasColumn('contact_messages', 'status'));

        // A new inquiry without a subject is now accepted.
        ContactMessage::create([
            'first_name' => 'New', 'last_name' => 'Sender', 'email' => 'new@example.com',
            'contact_number' => '09171234567', 'message' => 'No subject here.', 'status' => 'unread',
        ]);
        $this->assertSame(2, \DB::table('contact_messages')->count());
    }

    // ------------------------------------------------------------------ 4. email

    public function test_email_notification_is_sent_to_the_clinic_with_sender_as_reply_to(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload());

        Mail::assertSent(ContactMessageReceived::class, function (ContactMessageReceived $mail) {
            return $mail->hasTo('clinic@inbox.test')
                && $mail->hasReplyTo('maria@example.com')
                && $mail->envelope()->from === null        // sender is never set as From; the app's MAIL_FROM_* is used
                && str_contains($mail->render(), 'Maria Dela Cruz')
                && str_contains($mail->render(), '0917 123 4567')
                && str_contains($mail->render(), 'what are your clinic hours');
        });

        $this->assertNotNull(ContactMessage::first()->notified_at);
        $this->assertNull(ContactMessage::first()->notification_error);
    }

    public function test_notification_email_escapes_user_content(): void
    {
        $contact = $this->inquiry(['message' => '<script>alert(1)</script>']);

        $html = (new ContactMessageReceived($contact))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ------------------------------------------------------------------ 5. failures keep the inquiry

    public function test_email_failure_keeps_the_saved_inquiry_and_hides_details_from_the_visitor(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP password rejected by smtp.secret-host.test'));

        $response = $this->post(route('contact.store'), $this->payload());

        $response->assertRedirect('/#contact');
        $response->assertSessionHas('contact_success');
        $this->assertStringNotContainsString('secret-host', json_encode(session()->all()));

        $saved = ContactMessage::first();
        $this->assertNotNull($saved);
        $this->assertNull($saved->notified_at);
        $this->assertSame('The email notification could not be sent.', $saved->notification_error);
        $this->assertStringNotContainsString('secret-host', (string) $saved->notification_error);
    }

    public function test_admin_can_resend_a_notification_that_failed(): void
    {
        $admin = $this->makeUser('Admin');
        $contact = $this->inquiry(['notification_error' => 'The email notification could not be sent.']);
        Mail::fake();

        $this->actingAs($admin)
            ->post(route('admin.contact-messages.resend', $contact))
            ->assertSessionHas('success');

        Mail::assertSent(ContactMessageReceived::class, 1);
        $this->assertNotNull($contact->fresh()->notified_at);
        $this->assertNull($contact->fresh()->notification_error);
    }

    public function test_missing_recipient_is_recorded_without_losing_the_inquiry(): void
    {
        Mail::fake();
        config(['mail.contact_notification_email' => null]);

        $this->post(route('contact.store'), $this->payload())->assertSessionHas('contact_success');

        Mail::assertNothingSent();
        $this->assertSame(1, ContactMessage::count());
        $this->assertNotNull(ContactMessage::first()->notification_error);
    }

    public function test_recipient_falls_back_to_the_system_settings_clinic_email(): void
    {
        Mail::fake();
        config(['mail.contact_notification_email' => null]);
        \App\Models\SystemSetting::create(['key' => 'clinic_email', 'value' => 'official@clinic.test']);

        $this->post(route('contact.store'), $this->payload());

        Mail::assertSent(ContactMessageReceived::class, fn ($m) => $m->hasTo('official@clinic.test'));
    }

    // ------------------------------------------------------------------ 6 & 7. access control

    public function test_guests_cannot_open_admin_contact_messages(): void
    {
        $contact = $this->inquiry();

        $this->get(route('admin.contact-messages.index'))->assertRedirect('/login');
        $this->get(route('admin.contact-messages.show', $contact))->assertRedirect('/login');
        $this->patch(route('admin.contact-messages.read', $contact))->assertRedirect('/login');
        $this->patch(route('admin.contact-messages.unread', $contact))->assertRedirect('/login');
        $this->post(route('admin.contact-messages.resend', $contact))->assertRedirect('/login');
    }

    public function test_patient_staff_and_doctor_accounts_are_forbidden(): void
    {
        $contact = $this->inquiry();

        foreach (['Patient', 'Staff', 'Doctor'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->get(route('admin.contact-messages.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.contact-messages.show', $contact))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.contact-messages.read', $contact))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.contact-messages.unread', $contact))->assertForbidden();
            $this->actingAs($user)->post(route('admin.contact-messages.resend', $contact))->assertForbidden();
        }

        $this->assertTrue($contact->fresh()->isUnread());
    }

    // ------------------------------------------------------------------ 8. admin management

    public function test_admin_sees_the_list_with_unread_state_and_contact_number(): void
    {
        $admin = $this->makeUser('Admin');
        $this->inquiry(['first_name' => 'Unread', 'last_name' => 'Person', 'contact_number' => '09998887777']);
        $this->inquiry(['first_name' => 'Seen', 'last_name' => 'Person', 'status' => 'read', 'read_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.contact-messages.index'));

        $response->assertOk()
            ->assertSee('Contact Messages')
            ->assertSee('Unread Person')
            ->assertSee('Seen Person')
            ->assertSee('09998887777')
            ->assertSee('cm-row-unread', false);

        $this->actingAs($admin)->get(route('admin.contact-messages.index', ['status' => 'read']))
            ->assertSee('Seen Person')->assertDontSee('Unread Person');
    }

    public function test_admin_list_shows_an_empty_state(): void
    {
        $this->actingAs($this->makeUser('Admin'))
            ->get(route('admin.contact-messages.index'))
            ->assertOk()
            ->assertSee('No contact messages yet');
    }

    public function test_admin_list_is_paginated(): void
    {
        $admin = $this->makeUser('Admin');
        foreach (range(1, 12) as $i) {
            $this->inquiry(['first_name' => 'Sender' . $i, 'email' => "s{$i}@example.com"]);
        }

        $this->actingAs($admin)->get(route('admin.contact-messages.index'))
            ->assertOk()->assertSee('Showing')->assertSee('of')->assertSee('page=2', false);
        $this->actingAs($admin)->get(route('admin.contact-messages.index', ['page' => 2]))->assertOk();
    }

    public function test_opening_a_message_marks_it_read_and_it_can_be_marked_unread_again(): void
    {
        $admin = $this->makeUser('Admin');
        $contact = $this->inquiry();

        $this->actingAs($admin)->get(route('admin.contact-messages.show', $contact))
            ->assertOk()
            ->assertSee('Do you accept walk-ins?')
            ->assertSee('mailto:juan@example.com', false);

        $this->assertSame('read', $contact->fresh()->status);
        $this->assertNotNull($contact->fresh()->read_at);

        $this->actingAs($admin)->patch(route('admin.contact-messages.unread', $contact))
            ->assertRedirect(route('admin.contact-messages.index'));
        $this->assertSame('unread', $contact->fresh()->status);
        $this->assertNull($contact->fresh()->read_at);

        $this->actingAs($admin)->patch(route('admin.contact-messages.read', $contact))->assertSessionHas('success');
        $this->assertSame('read', $contact->fresh()->status);
    }

    public function test_user_submitted_content_is_escaped_in_the_admin_pages(): void
    {
        $admin = $this->makeUser('Admin');
        $contact = $this->inquiry(['message' => '<script>alert("xss")</script>', 'first_name' => '<b>Bold</b>']);

        $this->actingAs($admin)->get(route('admin.contact-messages.show', $contact))
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);

        $this->actingAs($admin)->get(route('admin.contact-messages.index'))
            ->assertDontSee('<b>Bold</b>', false);
    }

    public function test_admin_sidebar_has_the_contact_messages_link_with_unread_badge(): void
    {
        $admin = $this->makeUser('Admin');
        $this->inquiry();
        $this->inquiry(['email' => 'b@example.com']);

        $this->actingAs($admin)->get(route('admin.contact-messages.index'))
            ->assertSee(route('admin.contact-messages.index'), false)
            ->assertSee('Contact Messages');
    }

    // ------------------------------------------------------------------ 9. spam protection

    public function test_honeypot_submissions_are_silently_dropped(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), $this->payload(['website' => 'http://spam.example']));

        $response->assertRedirect('/#contact')->assertSessionHas('contact_success');
        $this->assertSame(0, ContactMessage::count());
        Mail::assertNothingSent();
    }

    public function test_the_same_message_sent_twice_is_stored_once(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload());
        $this->post(route('contact.store'), $this->payload())->assertSessionHas('contact_success');

        $this->assertSame(1, ContactMessage::count());
        Mail::assertSent(ContactMessageReceived::class, 1);
    }

    public function test_the_form_is_rate_limited_per_ip(): void
    {
        Mail::fake();

        foreach (range(1, 3) as $i) {
            $this->post(route('contact.store'), $this->payload(['message' => "Inquiry number {$i} please reply"]))
                ->assertSessionHas('contact_success');
        }

        $this->post(route('contact.store'), $this->payload(['message' => 'Inquiry number 4 please reply']))
            ->assertRedirect('/#contact')
            ->assertSessionHas('contact_error');

        $this->assertSame(3, ContactMessage::count());
    }

    public function test_csrf_protection_is_active_for_the_form_route(): void
    {
        // Laravel disables CSRF verification while testing; assert the route
        // still sits behind the web group (which carries VerifyCsrfToken) and
        // that the rendered form emits a token.
        $middleware = app('router')->getRoutes()->getByName('contact.store')->gatherMiddleware();

        $this->assertContains('web', $middleware);
        $this->assertContains('throttle:contact', $middleware);
        $this->assertStringContainsString('name="_token"', $this->get('/')->getContent());
    }

    // ------------------------------------------------------------------ 9. Admin reply

    private function replyPayload(array $override = []): array
    {
        return array_merge([
            'subject' => 'Re: Your inquiry to the clinic',
            'body'    => "Hello Juan,\nYes, we accept walk-ins from 8am to 5pm.",
        ], $override);
    }

    public function test_admin_can_reply_and_the_reply_is_emailed_and_recorded(): void
    {
        Mail::fake();
        $admin = $this->makeUser('Admin');
        $contact = $this->inquiry();

        $this->actingAs($admin)
            ->post(route('admin.contact-messages.reply', $contact), $this->replyPayload())
            ->assertRedirect(route('admin.contact-messages.show', $contact) . '#reply')
            ->assertSessionHas('success');

        Mail::assertSent(ContactMessageReply::class, 1);
        Mail::assertSent(ContactMessageReply::class, function (ContactMessageReply $mail) {
            return $mail->hasTo('juan@example.com')
                && $mail->hasReplyTo('clinic@inbox.test')
                && $mail->envelope()->from === null          // From stays the app's MAIL_FROM_ADDRESS
                && $mail->envelope()->subject === 'Re: Your inquiry to the clinic';
        });
        $this->assertDatabaseHas('contact_message_replies', [
            'contact_message_id' => $contact->id, 'user_id' => $admin->id,
            'subject' => 'Re: Your inquiry to the clinic',
        ]);
    }

    public function test_reply_email_renders_greeting_body_signature_and_original_message(): void
    {
        $contact = $this->inquiry(['message' => 'Do you accept walk-ins?']);

        $html = (new ContactMessageReply($contact, 'Re: Hi', "Line one\nLine two", 'Sample Clinic', 'clinic@inbox.test', '0917 000 0000'))->render();

        $this->assertStringContainsString('Hello Juan,', $html);
        $this->assertStringContainsString('Line one<br />', $html);
        $this->assertStringContainsString('Sample Clinic', $html);
        $this->assertStringContainsString('0917 000 0000', $html);
        $this->assertStringContainsString('Do you accept walk-ins?', $html);
    }

    public function test_reply_is_validated_and_nothing_is_sent_when_invalid(): void
    {
        Mail::fake();
        $contact = $this->inquiry();

        $this->actingAs($this->makeUser('Admin'))
            ->from(route('admin.contact-messages.show', $contact))
            ->post(route('admin.contact-messages.reply', $contact), ['subject' => '', 'body' => ''])
            ->assertRedirect(route('admin.contact-messages.show', $contact) . '#reply')
            ->assertSessionHasErrors(['subject', 'body']);

        $this->actingAs($this->makeUser('Admin'))
            ->post(route('admin.contact-messages.reply', $contact), $this->replyPayload(['body' => str_repeat('a', 5001)]))
            ->assertSessionHasErrors(['body']);

        Mail::assertNothingSent();
        $this->assertSame(0, \DB::table('contact_message_replies')->count());
    }

    public function test_line_breaks_in_the_subject_are_collapsed(): void
    {
        Mail::fake();
        $contact = $this->inquiry();

        $this->actingAs($this->makeUser('Admin'))
            ->post(route('admin.contact-messages.reply', $contact), $this->replyPayload(['subject' => "Hi\r\nBcc: evil@x.test"]));

        Mail::assertSent(ContactMessageReply::class, fn ($m) => $m->envelope()->subject === 'Hi Bcc: evil@x.test');
    }

    public function test_a_failed_reply_keeps_the_text_and_is_not_recorded(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP password rejected by smtp.secret-host.test'));
        $contact = $this->inquiry();

        $response = $this->actingAs($this->makeUser('Admin'))
            ->post(route('admin.contact-messages.reply', $contact), $this->replyPayload());

        $response->assertRedirect(route('admin.contact-messages.show', $contact) . '#reply');
        $response->assertSessionHas('error');
        $this->assertStringNotContainsString('secret-host', session('error'));
        $this->assertSame(0, \DB::table('contact_message_replies')->count());

        $page = $this->get(route('admin.contact-messages.show', $contact));
        $page->assertSee('we accept walk-ins from 8am to 5pm.', false);   // text kept via old()
    }

    public function test_the_identical_reply_is_not_sent_twice(): void
    {
        Mail::fake();
        $admin = $this->makeUser('Admin');
        $contact = $this->inquiry();

        $this->actingAs($admin)->post(route('admin.contact-messages.reply', $contact), $this->replyPayload());
        $this->actingAs($admin)->post(route('admin.contact-messages.reply', $contact), $this->replyPayload())
            ->assertSessionHas('error');

        Mail::assertSent(ContactMessageReply::class, 1);
        $this->assertSame(1, \DB::table('contact_message_replies')->count());
    }

    public function test_reply_history_and_replied_badge_show_up_and_are_escaped(): void
    {
        Mail::fake();
        $admin = $this->makeUser('Admin');
        $contact = $this->inquiry();

        $this->actingAs($admin)->post(route('admin.contact-messages.reply', $contact), $this->replyPayload([
            'body' => 'Thanks <script>alert(1)</script> for writing',
        ]));

        $show = $this->get(route('admin.contact-messages.show', $contact));
        $show->assertSee('Reply History')->assertSee('Replied')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $this->get(route('admin.contact-messages.index'))->assertSee('badge--replied', false);
    }

    public function test_only_admin_can_reply(): void
    {
        Mail::fake();
        $contact = $this->inquiry();

        $this->post(route('admin.contact-messages.reply', $contact), $this->replyPayload())->assertRedirect('/login');

        foreach (['Patient', 'Staff', 'Doctor'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->post(route('admin.contact-messages.reply', $contact), $this->replyPayload())
                ->assertForbidden();
        }

        Mail::assertNothingSent();
        $this->assertSame(0, \DB::table('contact_message_replies')->count());
    }
}
