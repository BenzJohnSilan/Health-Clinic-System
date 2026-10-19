<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PatientNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Duplicate GCash reference check, manual invoice void, and the Patient
 * billing notifications.
 */
class BillingVoidAndGcashReferenceTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function makeUser(string $role): User
    {
        $this->seq++;

        $id = DB::table('users')->insertGetId([
            'first_name'      => $role . $this->seq,
            'last_name'       => 'Test',
            'birthdate'       => '1990-01-01',
            'gender'          => 'Male',
            'civil_status'    => 'Single',
            'address'         => 'Somewhere',
            'contact_number'  => '0917200' . str_pad($this->seq, 4, '0', STR_PAD_LEFT),
            'username'        => strtolower($role) . 'vgr' . $this->seq,
            'email'           => strtolower($role) . 'vgr' . $this->seq . '@example.test',
            'password'        => bcrypt('password'),
            'role'            => $role,
            'status'          => 'Active',
            'approval_status' => 'Approved',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return User::findOrFail($id);
    }

    private function grant(User $user, array $slugs): void
    {
        foreach ($slugs as $slug) {
            $id = DB::table('permissions')->where('slug', $slug)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $slug, 'slug' => $slug, 'module' => 'Billing & Payments',
                    'description' => $slug, 'created_at' => now(), 'updated_at' => now(),
                ]);

            DB::table('user_permissions')->insert([
                'user_id' => $user->id, 'permission_id' => $id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** A Completed consultation with a ₱1,000 Unpaid invoice. */
    private function invoiceFor(User $patient): Invoice
    {
        $this->seq++;
        $doctor = $this->makeUser('Doctor');

        $apptId = DB::table('appointments')->insertGetId([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:' . str_pad($this->seq % 60, 2, '0', STR_PAD_LEFT) . ':00',
            'status'           => 'Completed',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $service = Service::where('name', 'Consultation')->firstOrFail();
        $service->update(['price' => 1000]);

        return app(BillingService::class)->syncInvoiceForAppointment(
            Appointment::findOrFail($apptId),
            [['service_id' => $service->id, 'quantity' => 1]],
            $doctor
        );
    }

    // ---------------------------------------------------------------
    // Duplicate GCash reference
    // ---------------------------------------------------------------

    public function test_a_gcash_reference_cannot_be_reused_on_another_invoice(): void
    {
        $cashier = $this->makeUser('Staff');
        $a = $this->invoiceFor($this->makeUser('Patient'));
        $b = $this->invoiceFor($this->makeUser('Patient'));
        $svc = app(BillingService::class);

        $svc->recordPayment($a, ['amount' => 500, 'payment_method' => 'GCash', 'reference_number' => 'ABC123'], $cashier);

        $this->expectException(ValidationException::class);

        // Same number typed in lowercase with stray spaces is still a duplicate.
        $svc->recordPayment($b, ['amount' => 500, 'payment_method' => 'GCash', 'reference_number' => ' abc123 '], $cashier);
    }

    public function test_a_duplicate_reference_records_nothing(): void
    {
        $cashier = $this->makeUser('Staff');
        $a = $this->invoiceFor($this->makeUser('Patient'));
        $b = $this->invoiceFor($this->makeUser('Patient'));
        $svc = app(BillingService::class);

        $svc->recordPayment($a, ['amount' => 500, 'payment_method' => 'GCash', 'reference_number' => 'REF-1'], $cashier);

        try {
            $svc->recordPayment($b, ['amount' => 500, 'payment_method' => 'GCash', 'reference_number' => 'REF-1'], $cashier);
            $this->fail('Duplicate reference should have been rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reference_number', $e->errors());
        }

        $this->assertSame(1, Payment::count());
        $this->assertSame('Unpaid', $b->fresh()->status);
    }

    public function test_cash_payments_and_distinct_references_are_unaffected(): void
    {
        $cashier = $this->makeUser('Staff');
        $a = $this->invoiceFor($this->makeUser('Patient'));
        $svc = app(BillingService::class);

        $svc->recordPayment($a, ['amount' => 300, 'payment_method' => 'GCash', 'reference_number' => 'REF-A'], $cashier);
        $svc->recordPayment($a, ['amount' => 300, 'payment_method' => 'GCash', 'reference_number' => 'REF-B'], $cashier);
        $svc->recordPayment($a, ['amount' => 100, 'payment_method' => 'Cash'], $cashier);
        $svc->recordPayment($a->fresh(), ['amount' => 100, 'payment_method' => 'Cash'], $cashier);

        $this->assertSame(4, Payment::count());
    }

    // ---------------------------------------------------------------
    // Void invoice
    // ---------------------------------------------------------------

    public function test_cashier_can_void_an_unpaid_invoice_with_a_reason_and_it_is_logged(): void
    {
        $cashier = $this->makeUser('Staff');
        $this->grant($cashier, ['view_billing', 'record_payment']);
        $invoice = $this->invoiceFor($this->makeUser('Patient'));

        $this->actingAs($cashier)
            ->post(route('staff.billing.void', $invoice->id), ['void_reason' => 'Charged in error'])
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('Cancelled', $invoice->status);
        $this->assertEquals(0, $invoice->balance);

        $log = DB::table('user_logs')->where('action', 'Voided Invoice')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Charged in error', $log->details);
    }

    public function test_void_requires_a_reason(): void
    {
        $cashier = $this->makeUser('Staff');
        $this->grant($cashier, ['view_billing', 'record_payment']);
        $invoice = $this->invoiceFor($this->makeUser('Patient'));

        $this->actingAs($cashier)
            ->post(route('staff.billing.void', $invoice->id), ['void_reason' => '   '])
            ->assertSessionHasErrors('void_reason');

        $this->assertSame('Unpaid', $invoice->fresh()->status);
    }

    public function test_an_invoice_with_a_payment_cannot_be_voided(): void
    {
        $cashier = $this->makeUser('Staff');
        $this->grant($cashier, ['view_billing', 'record_payment']);
        $invoice = $this->invoiceFor($this->makeUser('Patient'));

        app(BillingService::class)->recordPayment($invoice, ['amount' => 100, 'payment_method' => 'Cash'], $cashier);

        $this->actingAs($cashier)
            ->post(route('staff.billing.void', $invoice->id), ['void_reason' => 'Oops'])
            ->assertSessionHasErrors('void_reason');

        $this->assertSame('Partially Paid', $invoice->fresh()->status);
    }

    public function test_a_voided_invoice_cannot_be_paid_or_voided_again(): void
    {
        $cashier = $this->makeUser('Staff');
        $invoice = $this->invoiceFor($this->makeUser('Patient'));
        $svc = app(BillingService::class);

        $svc->voidInvoice($invoice, 'Charged in error', $cashier);

        try {
            $svc->voidInvoice($invoice, 'Again', $cashier);
            $this->fail('Second void should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('void_reason', $e->errors());
        }

        $this->expectException(ValidationException::class);
        $svc->recordPayment($invoice->fresh(), ['amount' => 100, 'payment_method' => 'Cash'], $cashier);
    }

    public function test_void_needs_the_record_payment_permission(): void
    {
        $staff = $this->makeUser('Staff');
        $this->grant($staff, ['view_billing']);
        $invoice = $this->invoiceFor($this->makeUser('Patient'));

        $this->actingAs($staff)
            ->post(route('staff.billing.void', $invoice->id), ['void_reason' => 'x'])
            ->assertForbidden();

        $this->assertSame('Unpaid', $invoice->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Patient notifications
    // ---------------------------------------------------------------

    public function test_patient_gets_bill_ready_then_payment_received_notifications(): void
    {
        $patient = $this->makeUser('Patient');
        $this->grant($patient, ['view_billing', 'view_payment_history']);
        $invoice = $this->invoiceFor($patient);
        $service = app(PatientNotificationService::class);

        $titles = $service->build($patient)->pluck('title');
        $this->assertTrue($titles->contains('Your Bill Is Ready'));
        $this->assertFalse($titles->contains('Payment Received'));

        app(BillingService::class)->recordPayment($invoice, ['amount' => 1000, 'payment_method' => 'Cash'], $this->makeUser('Staff'));

        $titles = $service->build($patient)->pluck('title');
        $this->assertTrue($titles->contains('Payment Received'));
        // Fully paid -> no longer a "bill is ready" reminder.
        $this->assertFalse($titles->contains('Your Bill Is Ready'));
    }

    public function test_billing_notifications_need_the_view_billing_permission_and_stay_private(): void
    {
        $patient = $this->makeUser('Patient');
        $other   = $this->makeUser('Patient');
        $this->grant($other, ['view_billing', 'view_payment_history']);
        $this->invoiceFor($patient);   // belongs to $patient only
        $service = app(PatientNotificationService::class);

        // No permission -> nothing.
        $this->assertFalse($service->build($patient)->pluck('category')->contains('billing'));
        // Has permission but not the owner -> nothing.
        $this->assertFalse($service->build($other)->pluck('category')->contains('billing'));
    }

    public function test_voided_and_unfinished_consultation_bills_do_not_notify(): void
    {
        $patient = $this->makeUser('Patient');
        $this->grant($patient, ['view_billing']);
        $invoice = $this->invoiceFor($patient);

        app(BillingService::class)->voidInvoice($invoice, 'Charged in error', $this->makeUser('Staff'));

        $this->assertFalse(app(PatientNotificationService::class)->build($patient)->pluck('category')->contains('billing'));
    }
}