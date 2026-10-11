<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pay-after-service billing: charges are only a DRAFT while the
 * consultation is In Progress, the invoice appears (and becomes
 * collectible) only after Complete Consultation, plus the payment /
 * discount rules and the Staff dashboard metrics.
 */
class PayAfterServiceBillingTest extends TestCase
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
            'contact_number'  => '0917100' . str_pad($this->seq, 4, '0', STR_PAD_LEFT),
            'reason'          => 'To Access Clinic Services',
            'username'        => strtolower($role) . 'pas' . $this->seq,
            'email'           => strtolower($role) . 'pas' . $this->seq . '@example.test',
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

    private function makeAppointment(User $patient, User $doctor, string $status): Appointment
    {
        $this->seq++;

        $id = DB::table('appointments')->insertGetId([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:' . str_pad($this->seq % 60, 2, '0', STR_PAD_LEFT) . ':00',
            'status'           => $status,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return Appointment::findOrFail($id);
    }

    private function consultationService(float $price = 1000): Service
    {
        $service = Service::where('name', 'Consultation')->firstOrFail();
        $service->update(['price' => $price]);

        return $service;
    }

    private function completedInvoice(float $price = 1000): array
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor, 'Completed');
        $service = $this->consultationService($price);

        $invoice = app(BillingService::class)->syncInvoiceForAppointment(
            $appt, [['service_id' => $service->id, 'quantity' => 1]], $doctor
        );

        return [$invoice, $doctor, $appt];
    }

    private function completePayload(): array
    {
        return [
            'chief_complaint' => 'Cough',
            'diagnosis'       => 'Common cold',
            'treatment'       => 'Rest and fluids',
        ];
    }

    // ------------------------------------------------------------------
    // TASK 1 — invoice timing
    // ------------------------------------------------------------------

    public function test_save_charges_while_in_progress_creates_only_a_draft_never_an_invoice(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');
        $service = $this->consultationService();

        $this->actingAs($doctor)
            ->patch(route('doctor.appointments.saveCharges', $appt->id), [
                'service_ids' => [$service->id],
                'service_qty' => [$service->id => 1],
            ])
            ->assertSessionHas('success');

        $this->assertSame(0, Invoice::count(), 'No invoice may exist while In Progress.');
        $this->assertNotNull($appt->fresh()->billing_draft);
        $this->assertSame($service->id, $appt->fresh()->billing_draft['services'][0]['service_id']);
    }

    public function test_draft_is_prefilled_on_the_consultation_page_without_an_invoice(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');
        $service = $this->consultationService();

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id], 'service_qty' => [$service->id => 2],
        ]);

        $this->actingAs($doctor)->get(route('doctor.appointments.show', $appt->id))
            ->assertOk()
            ->assertSee('Draft');

        $this->assertSame(0, Invoice::count());
    }

    public function test_service_refuses_to_create_an_invoice_for_a_non_completed_appointment(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $service = $this->consultationService();

        foreach (['In Progress', 'Checked In', 'Approved'] as $status) {
            $appt = $this->makeAppointment($patient, $doctor, $status);

            try {
                app(BillingService::class)->syncInvoiceForAppointment(
                    $appt, [['service_id' => $service->id, 'quantity' => 1]], $doctor
                );
                $this->fail("Invoice was created for a {$status} appointment.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('charges', $e->errors());
            }
        }

        $this->assertSame(0, Invoice::count());
    }

    public function test_complete_consultation_turns_the_draft_into_the_invoice_for_staff(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $staff   = $this->makeUser('Staff');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $this->grant($staff, ['view_billing', 'record_payment']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');
        $service = $this->consultationService(1000);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id], 'service_qty' => [$service->id => 1],
        ]);
        $this->assertSame(0, Invoice::count());

        $this->actingAs($doctor)
            ->patch(route('doctor.appointments.completeConsultation', $appt->id), $this->completePayload())
            ->assertSessionHas('success');

        $this->assertSame('Completed', $appt->fresh()->status);
        $this->assertNull($appt->fresh()->billing_draft, 'Draft is cleared once it became the invoice.');

        $invoice = Invoice::firstOrFail();
        $this->assertSame($appt->id, $invoice->appointment_id);
        $this->assertEquals(1000, $invoice->total_amount);
        $this->assertSame('Unpaid', $invoice->status);

        $this->actingAs($staff)->get(route('staff.billing.index'))
            ->assertOk()->assertSee($invoice->invoice_no);
        $this->actingAs($staff)->get(route('staff.billing.show', $invoice->id))->assertOk();
    }

    public function test_completed_consultation_is_always_charged_the_consultation_fee(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');

        $this->consultationService(500);

        // Nothing prepared at all: the Consultation fee is still charged.
        $this->actingAs($doctor)
            ->patch(route('doctor.appointments.completeConsultation', $appt->id), $this->completePayload());
        $this->assertSame(1, Invoice::count());
        $this->assertEquals(500, Invoice::first()->total_amount);

        // Prepared an EMPTY selection: same result, and never a duplicate line.
        $appt2 = $this->makeAppointment($patient, $doctor, 'In Progress');
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt2->id), []);
        $this->actingAs($doctor)
            ->patch(route('doctor.appointments.completeConsultation', $appt2->id), $this->completePayload());
        $this->assertSame(2, Invoice::count());
        $this->assertSame(1, $appt2->fresh()->invoice->items()->count());
    }

    public function test_charges_cannot_be_prepared_for_other_statuses(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'Approved');
        $service = $this->consultationService();

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id],
        ])->assertSessionHas('error');

        $this->assertSame(0, Invoice::count());
        $this->assertNull($appt->fresh()->billing_draft);
    }

    public function test_completed_consultation_can_still_be_billed_directly_by_the_doctor(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'Completed');
        $service = $this->consultationService(500);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id], 'service_qty' => [$service->id => 1],
        ])->assertSessionHas('success');

        $this->assertSame(1, Invoice::count());
        $this->assertEquals(500, Invoice::first()->total_amount);

        // Ticking Consultation again never doubles the charge.
        $this->assertSame(1, Invoice::first()->items()->count());

        // Completed + nothing selected: the automatic Consultation fee is still billed.
        $appt2 = $this->makeAppointment($patient, $doctor, 'Completed');
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt2->id), [])
            ->assertSessionHas('success');
        $this->assertSame(2, Invoice::count());
        $this->assertEquals(500, $appt2->fresh()->invoice->total_amount);
    }

    public function test_a_legacy_in_progress_invoice_can_not_be_paid_discounted_or_seen_by_staff(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $staff   = $this->makeUser('Staff');
        $this->grant($staff, ['view_billing', 'record_payment']);
        $appt    = $this->makeAppointment($patient, $doctor, 'Completed');
        $service = $this->consultationService(1000);

        $invoice = app(BillingService::class)->syncInvoiceForAppointment(
            $appt, [['service_id' => $service->id, 'quantity' => 1]], $doctor
        );

        // Simulate an invoice created by the OLD flow while still In Progress.
        $appt->update(['status' => 'In Progress']);

        $this->actingAs($staff)->get(route('staff.billing.index'))
            ->assertOk()->assertDontSee($invoice->invoice_no);
        $this->actingAs($staff)->get(route('staff.billing.show', $invoice->id))->assertNotFound();

        try {
            app(BillingService::class)->recordPayment($invoice, [
                'amount' => 100, 'payment_method' => 'Cash',
            ], $staff);
            $this->fail('A payment was accepted before the consultation was completed.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $item = $invoice->items()->first();
        $this->expectException(ValidationException::class);
        app(BillingService::class)->applyItemDiscounts($invoice, [
            $item->id => ['discount_type' => 'clinic_discount', 'discount_rate' => 5],
        ]);
    }

    public function test_doctor_cannot_bill_medicine_and_prescriptions_never_touch_stock(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');
        $this->consultationService(500);

        $make = fn (string $name, float $price) => DB::table('medicines')->insertGetId([
            'medicine_name' => $name, 'category' => 'Test', 'quantity' => 50, 'unit' => 'tablet',
            'price' => $price, 'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'Available', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $rx = fn (?int $medicineId, string $manual = null) => DB::table('prescriptions')->insertGetId([
            'appointment_id' => $appt->id, 'medicine_id' => $medicineId, 'manual_medicine_name' => $manual,
            'dosage' => '500mg', 'frequency' => 'daily', 'duration' => '5 days',
            'quantity_prescribed' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $clinic = $rx($make('Clinic Med', 10), null);
        $manual = $rx(null, 'Outside pharmacy med');

        // A crafted request still tries to bill the prescriptions through the Doctor's form.
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'medicine_rx_ids' => [$clinic, $manual],
            'medicine_rx_qty' => [$clinic => 3, $manual => 2],
        ]);
        $this->assertSame(0, Invoice::count(), 'Still In Progress: no invoice yet.');

        $this->actingAs($doctor)
            ->patch(route('doctor.appointments.completeConsultation', $appt->id), $this->completePayload());

        $invoice = Invoice::firstOrFail();
        $items   = $invoice->items()->get();
        $this->assertCount(1, $items, 'Only the automatic Consultation line — no medicine is billed by the Doctor.');
        $this->assertSame('Consultation', $items->first()->description);
        $this->assertEquals(500, $invoice->total_amount);
        $this->assertSame(50, (int) DB::table('medicines')->where('medicine_name', 'Clinic Med')->value('quantity'));
    }

    // ------------------------------------------------------------------
    // TASK 3 — discounts
    // ------------------------------------------------------------------

    public function test_discount_math_gross_minus_discount_equals_net(): void
    {
        [$invoice] = $this->completedInvoice(1000);
        $item = $invoice->items()->first();

        $updated = app(BillingService::class)->applyItemDiscounts($invoice, [
            $item->id => ['discount_type' => 'clinic_discount', 'discount_rate' => 5],
        ]);

        $this->assertEquals(1000, $updated->subtotal);
        $this->assertEquals(50, $updated->discount);
        $this->assertEquals(950, $updated->total_amount);
        $this->assertEquals(950, $updated->balance);
    }

    public function test_senior_and_pwd_rates_cannot_be_tampered_with_from_the_browser(): void
    {
        [$invoice] = $this->completedInvoice(1000);
        $item = $invoice->items()->first();

        $updated = app(BillingService::class)->applyItemDiscounts($invoice, [
            $item->id => ['discount_type' => 'senior_citizen', 'discount_rate' => 100],
        ]);

        $this->assertEquals(20, $item->fresh()->discount_rate, 'Submitted rate must be ignored for fixed-rate types.');
        $this->assertEquals(200, $updated->discount);
        $this->assertEquals(800, $updated->total_amount);

        $updated = app(BillingService::class)->applyItemDiscounts($updated, [
            $item->id => ['discount_type' => 'pwd', 'discount_rate' => 1],
        ]);
        $this->assertEquals(20, $item->fresh()->discount_rate);
        $this->assertEquals(800, $updated->total_amount);
    }

    public function test_discount_is_locked_once_a_payment_exists(): void
    {
        [$invoice, , ] = $this->completedInvoice(1000);
        $staff = $this->makeUser('Staff');
        $item  = $invoice->items()->first();

        app(BillingService::class)->recordPayment($invoice, ['amount' => 100, 'payment_method' => 'Cash'], $staff);

        $this->expectException(ValidationException::class);
        app(BillingService::class)->applyItemDiscounts($invoice->fresh(), [
            $item->id => ['discount_type' => 'clinic_discount', 'discount_rate' => 50],
        ]);
    }

    // ------------------------------------------------------------------
    // TASK 4 / 7 — partial payment, overpayment, GCash
    // ------------------------------------------------------------------

    public function test_partial_then_full_payment_and_overpayment_protection(): void
    {
        [$invoice] = $this->completedInvoice(1000);
        $staff     = $this->makeUser('Staff');
        $svc       = app(BillingService::class);

        $svc->recordPayment($invoice, ['amount' => 500, 'payment_method' => 'Cash'], $staff);
        $invoice->refresh();
        $this->assertEquals(500, $invoice->amount_paid);
        $this->assertEquals(500, $invoice->balance);
        $this->assertSame('Partially Paid', $invoice->status);

        try {
            $svc->recordPayment($invoice, ['amount' => 500.01, 'payment_method' => 'Cash'], $staff);
            $this->fail('Overpayment was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $svc->recordPayment($invoice->fresh(), ['amount' => 500, 'payment_method' => 'Cash'], $staff);
        $invoice->refresh();
        $this->assertEquals(1000, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance);
        $this->assertSame('Paid', $invoice->status);

        try {
            $svc->recordPayment($invoice, ['amount' => 1, 'payment_method' => 'Cash'], $staff);
            $this->fail('A paid invoice accepted another payment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    public function test_gcash_requires_a_reference_number_and_cash_does_not(): void
    {
        [$invoice] = $this->completedInvoice(1000);
        $staff     = $this->makeUser('Staff');
        $svc       = app(BillingService::class);

        try {
            $svc->recordPayment($invoice, ['amount' => 100, 'payment_method' => 'GCash'], $staff);
            $this->fail('GCash payment without reference was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reference_number', $e->errors());
        }

        $gcash = $svc->recordPayment($invoice, [
            'amount' => 100, 'payment_method' => 'GCash', 'reference_number' => 'GC-123',
        ], $staff);
        $this->assertSame('GC-123', $gcash->reference_number);

        $cash = $svc->recordPayment($invoice->fresh(), ['amount' => 100, 'payment_method' => 'Cash'], $staff);
        $this->assertNull($cash->reference_number);
    }

    // ------------------------------------------------------------------
    // TASK 2 — dashboard metrics
    // ------------------------------------------------------------------

    public function test_billing_dashboard_metrics(): void
    {
        [$invoice] = $this->completedInvoice(1000);
        $staff     = $this->makeUser('Staff');
        $this->grant($staff, ['view_billing', 'view_billing_reports', 'record_payment']);
        $svc  = app(BillingService::class);
        $item = $invoice->items()->first();

        $svc->applyItemDiscounts($invoice, [
            $item->id => ['discount_type' => 'clinic_discount', 'discount_rate' => 5],
        ]);
        // One PHP 950 invoice paid in two payments of different methods.
        $svc->recordPayment($invoice->fresh(), ['amount' => 500, 'payment_method' => 'Cash'], $staff);
        $svc->recordPayment($invoice->fresh(), [
            'amount' => 200, 'payment_method' => 'GCash', 'reference_number' => 'GC-1',
        ], $staff);

        $response = $this->actingAs($staff)->get(route('staff.billing.index'));
        $response->assertOk();

        $totals = $response->viewData('reportTotals');
        $this->assertEquals(1000, $totals['grossSales']);
        $this->assertEquals(50, $totals['totalDiscount']);
        $this->assertEquals(950, $totals['netSales']);
        $this->assertEquals(500, $totals['cashPayments']);
        $this->assertEquals(200, $totals['nonCashPayments']);
        $this->assertSame(2, $totals['paymentTransactions'], 'Counts payment records, not invoices.');
        $this->assertEquals(250, $totals['totalOutstanding']);

        $response->assertSee('Gross Sales')->assertSee('Net Sales')
            ->assertSee('Cash Payments')->assertSee('Non-Cash Payments')
            ->assertSee('Payment Transactions')->assertSee('Outstanding Balance')
            ->assertDontSee('Cash Revenue')->assertDontSee('Cash Sales');
    }
}
