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
 * Remaining billing edge cases: Doctor charge-draft permissions, locking
 * after payment, dashboard dates, outstanding balance, empty invoices,
 * payment validation and receipts.
 */
class BillingEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function makeUser(string $role): User
    {
        $this->seq++;

        $id = DB::table('users')->insertGetId([
            'first_name'      => $role . $this->seq,
            'last_name'       => 'Edge',
            'birthdate'       => '1990-01-01',
            'gender'          => 'Male',
            'civil_status'    => 'Single',
            'address'         => 'Somewhere',
            'contact_number'  => '0917200' . str_pad($this->seq, 4, '0', STR_PAD_LEFT),
            'reason'          => 'To Access Clinic Services',
            'username'        => strtolower($role) . 'edge' . $this->seq,
            'email'           => strtolower($role) . 'edge' . $this->seq . '@example.test',
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
            'appointment_time' => '10:' . str_pad($this->seq % 60, 2, '0', STR_PAD_LEFT) . ':00',
            'status'           => $status,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return Appointment::findOrFail($id);
    }

    private function consultation(float $price = 1000): Service
    {
        $service = Service::where('name', 'Consultation')->firstOrFail();
        $service->update(['price' => $price]);

        return $service;
    }

    private function invoice(float $price = 1000, ?User $doctor = null): array
    {
        $doctor  = $doctor ?? $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor, 'Completed');
        $service = $this->consultation($price);

        $invoice = app(BillingService::class)->syncInvoiceForAppointment(
            $appt, [['service_id' => $service->id, 'quantity' => 1]], $doctor
        );

        return [$invoice, $doctor, $appt, $service];
    }

    private function pay(Invoice $invoice, float $amount, string $method = 'Cash', ?string $ref = null)
    {
        return app(BillingService::class)->recordPayment($invoice->fresh(), [
            'amount' => $amount, 'payment_method' => $method, 'reference_number' => $ref,
        ], $this->makeUser('Staff'));
    }

    // ------------------------------------------------------------------
    // 1. Doctor charge-draft permissions (existing rules kept as they are)
    // ------------------------------------------------------------------

    public function test_create_only_doctor_can_start_a_draft_but_not_edit_it(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');
        $service = $this->consultation();
        $payload = ['service_ids' => [$service->id], 'service_qty' => [$service->id => 1]];

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), $payload)
            ->assertSessionHas('success');
        $this->assertNotNull($appt->fresh()->billing_draft);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), $payload)
            ->assertForbidden();
    }

    public function test_edit_only_doctor_cannot_create_a_draft_but_can_complete_the_consultation(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');
        $service = $this->consultation();

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id],
        ])->assertForbidden();
        $this->assertNull($appt->fresh()->billing_draft);

        $this->actingAs($doctor)->patch(route('doctor.appointments.completeConsultation', $appt->id), [
            'chief_complaint' => 'Cough', 'diagnosis' => 'Cold', 'treatment' => 'Rest',
        ])->assertSessionHas('success');
        $this->assertSame('Completed', $appt->fresh()->status);
    }

    public function test_doctor_with_both_permissions_can_create_then_edit_the_draft(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');
        $service = $this->consultation();

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id], 'service_qty' => [$service->id => 1],
        ])->assertSessionHas('success');
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id], 'service_qty' => [$service->id => 3],
        ])->assertSessionHas('success');

        $this->assertSame(3, $appt->fresh()->billing_draft['services'][0]['quantity']);
        $this->assertSame(0, Invoice::count());
    }

    // ------------------------------------------------------------------
    // 2 / 3. Locking once a payment exists
    // ------------------------------------------------------------------

    public function test_charges_cannot_be_changed_added_or_removed_after_a_payment(): void
    {
        [$invoice, $doctor, $appt, $service] = $this->invoice(1000);
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $this->pay($invoice, 500);

        $before = $invoice->fresh()->only(['subtotal', 'discount', 'total_amount', 'amount_paid', 'balance', 'status']);
        $itemsBefore = $invoice->items()->get(['id', 'quantity', 'unit_price', 'amount'])->toArray();

        // Doctor, via the real endpoint: change quantity, remove everything.
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id], 'service_qty' => [$service->id => 5],
        ])->assertSessionHas('error');
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [])
            ->assertSessionHas('error');

        // Service level too (not just the controller): the same call must be a no-op.
        $same = app(BillingService::class)->syncInvoiceForAppointment(
            $appt->fresh(), [['service_id' => $service->id, 'quantity' => 9]], $doctor
        );
        $this->assertTrue($same->exists);

        $this->assertSame($before, $invoice->fresh()->only(['subtotal', 'discount', 'total_amount', 'amount_paid', 'balance', 'status']));
        $this->assertSame($itemsBefore, $invoice->items()->get(['id', 'quantity', 'unit_price', 'amount'])->toArray());
        $this->assertSame('Partially Paid', $invoice->fresh()->status);
    }

    public function test_recalculation_cannot_change_a_partially_paid_invoice(): void
    {
        [$invoice] = $this->invoice(1000);
        $this->pay($invoice, 500);

        // Even a tampered item price must not leak into the totals.
        $invoice->items()->update(['unit_price' => 700]);
        $result = app(BillingService::class)->recalculateInvoiceTotals($invoice->fresh());

        $this->assertEquals(1000, $result->total_amount);
        $this->assertEquals(500, $result->balance);
        $this->assertSame('Partially Paid', $result->status);
    }

    public function test_discount_cannot_be_changed_after_a_payment_even_with_a_discount_applied(): void
    {
        [$invoice] = $this->invoice(1000);
        $item = $invoice->items()->first();
        $svc  = app(BillingService::class);

        $svc->applyItemDiscounts($invoice, [$item->id => ['discount_type' => 'clinic_discount', 'discount_rate' => 5]]);
        $this->pay($invoice, 500);

        foreach ([
            ['discount_type' => 'clinic_discount', 'discount_rate' => 50],
            ['discount_type' => 'senior_citizen', 'discount_rate' => 100],
            ['discount_type' => '', 'discount_rate' => ''],
        ] as $attempt) {
            try {
                $svc->applyItemDiscounts($invoice->fresh(), [$item->id => $attempt]);
                $this->fail('Discount was changed after a payment.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('discount', $e->errors());
            }
        }

        $fresh = $invoice->fresh();
        $this->assertEquals(50, $fresh->discount);
        $this->assertEquals(950, $fresh->total_amount);
        $this->assertEquals(450, $fresh->balance);
    }

    public function test_discount_cannot_be_changed_through_the_staff_http_endpoint_after_payment(): void
    {
        [$invoice] = $this->invoice(1000);
        $staff = $this->makeUser('Staff');
        $this->grant($staff, ['view_billing', 'record_payment']);
        $item = $invoice->items()->first();
        $this->pay($invoice, 100);

        $this->actingAs($staff)->post(route('staff.billing.discounts.update', $invoice->id), [
            'items' => [$item->id => ['discount_type' => 'clinic_discount', 'discount_rate' => 90]],
        ])->assertSessionHasErrors('discount');

        $this->assertEquals(1000, $invoice->fresh()->total_amount);
    }

    // ------------------------------------------------------------------
    // 4 / 5. Dashboard dates and Outstanding Balance
    // ------------------------------------------------------------------

    public function test_dashboard_uses_invoice_date_for_sales_and_payment_date_for_payments(): void
    {
        [$invoice] = $this->invoice(1000);
        $staff = $this->makeUser('Staff');
        $this->grant($staff, ['view_billing', 'view_billing_reports']);

        // Invoice finalized Sept 30, patient paid on Oct 1.
        DB::table('invoices')->where('id', $invoice->id)->update(['created_at' => '2026-09-30 15:00:00']);
        $payment = $this->pay($invoice, 1000);
        DB::table('payments')->where('id', $payment->id)->update(['paid_at' => '2026-10-01 09:00:00']);

        $sept30 = $this->actingAs($staff)
            ->get(route('staff.billing.index', ['range' => 'custom', 'from' => '2026-09-30', 'to' => '2026-09-30']))
            ->viewData('reportTotals');
        $this->assertEquals(1000, $sept30['grossSales']);
        $this->assertEquals(1000, $sept30['netSales']);
        $this->assertEquals(0, $sept30['cashPayments']);
        $this->assertSame(0, $sept30['paymentTransactions']);

        $oct1 = $this->actingAs($staff)
            ->get(route('staff.billing.index', ['range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-01']))
            ->viewData('reportTotals');
        $this->assertEquals(0, $oct1['grossSales']);
        $this->assertEquals(0, $oct1['netSales']);
        $this->assertEquals(1000, $oct1['cashPayments']);
        $this->assertSame(1, $oct1['paymentTransactions']);
    }

    public function test_outstanding_balance_counts_only_real_unpaid_amounts(): void
    {
        $staff = $this->makeUser('Staff');
        $this->grant($staff, ['view_billing', 'view_billing_reports']);

        [$unpaid]  = $this->invoice(1000);            // 1000 owed
        [$partial] = $this->invoice(1000);
        $this->pay($partial, 500);                    //  500 owed
        [$paid]    = $this->invoice(1000);
        $this->pay($paid, 1000);                      //    0 owed
        [$cancelled] = $this->invoice(1000);
        DB::table('invoices')->where('id', $cancelled->id)->update(['status' => 'Cancelled', 'balance' => 0]);

        $totals = $this->actingAs($staff)->get(route('staff.billing.index'))->viewData('reportTotals');

        $this->assertEquals(1500, $totals['totalOutstanding']);
        $this->assertEquals(1000, $unpaid->fresh()->balance);
        $this->assertEquals(500, $partial->fresh()->balance);
        $this->assertEquals(0, $paid->fresh()->balance);
    }

    // ------------------------------------------------------------------
    // 6 / 7. No charges, and charges after completion
    // ------------------------------------------------------------------

    public function test_removing_every_optional_charge_keeps_the_automatic_consultation_line(): void
    {
        [$invoice, $doctor, $appt] = $this->invoice(1000);
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [])
            ->assertSessionHas('success');

        // The Consultation fee is automatic: clearing the form leaves exactly
        // that one line (never a ₱0.00 invoice, never a duplicate).
        $this->assertSame(1, Invoice::count());
        $this->assertSame(1, $appt->fresh()->invoice->items()->count());
        $this->assertEquals(1000, $appt->fresh()->invoice->total_amount);

        // ...and the Doctor can still add a charge afterwards.
        $service = $this->consultation(300);
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$service->id], 'service_qty' => [$service->id => 1],
        ])->assertSessionHas('success');
        $this->assertEquals(300, Invoice::firstOrFail()->total_amount);
    }

    public function test_certificate_line_survives_the_doctor_re_saving_charges(): void
    {
        [$invoice, $doctor, $appt] = $this->invoice(500);
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);

        DB::table('services')->where('name', Service::MEDICAL_CERTIFICATE)->exists()
            || Service::create(['name' => Service::MEDICAL_CERTIFICATE, 'description' => 'MC', 'price' => 100, 'is_active' => true]);
        DB::table('services')->where('name', Service::MEDICAL_CERTIFICATE)->update(['price' => 100]);

        $certId = DB::table('medical_certificates')->insertGetId($this->certificateRow($appt, $doctor));
        $cert   = \App\Models\MedicalCertificate::findOrFail($certId);
        $combined = app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);
        $this->assertSame($invoice->id, $combined->id);
        $this->assertEquals(600, $combined->fresh()->total_amount);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), []);

        $this->assertSame(1, Invoice::count());
        $this->assertEquals(600, Invoice::first()->total_amount, 'Consultation (automatic) + the certificate line.');
        $this->assertSame(1, Invoice::first()->items()->where('item_type', 'medical_certificate')->count());
    }

    private function certificateRow(Appointment $appt, User $doctor): array
    {
        $columns = DB::getSchemaBuilder()->getColumnListing('medical_certificates');
        $row = [
            'certificate_number' => 'MC-EDGE-' . $this->seq++,
            'appointment_id'     => $appt->id,
            'patient_id'         => $appt->patient_id,
            'doctor_id'          => $doctor->id,
            'purpose'            => 'Work',
            'medical_statement'  => 'Seen.',
            'recommendation'     => 'Rest.',
            'status'             => 'issued',
            'created_at'         => now(),
            'updated_at'         => now(),
        ];

        return array_intersect_key($row, array_flip($columns));
    }

    public function test_prescription_alone_is_never_billed_and_never_touches_stock(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $this->grant($doctor, ['create_invoice', 'edit_invoice']);
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');

        $medId = DB::table('medicines')->insertGetId([
            'medicine_name' => 'Rx Only Med', 'category' => 'Test', 'quantity' => 40, 'unit' => 'tablet',
            'price' => 25, 'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'Available', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('prescriptions')->insert([
            'appointment_id' => $appt->id, 'medicine_id' => $medId, 'dosage' => '500mg',
            'frequency' => 'daily', 'duration' => '10 days', 'quantity_prescribed' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Doctor saves charges without ticking the prescription, then completes.
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), []);
        $this->actingAs($doctor)->patch(route('doctor.appointments.completeConsultation', $appt->id), [
            'chief_complaint' => 'Cough', 'diagnosis' => 'Cold', 'treatment' => 'Medicine',
        ]);

        $this->assertSame('Completed', $appt->fresh()->status);
        // The invoice exists (automatic Consultation fee) but the prescription adds nothing to it.
        $invoice = Invoice::firstOrFail();
        $this->assertSame(0, $invoice->items()->where('item_type', 'medicine')->count(), 'A prescription alone must not create any medicine charge.');
        $this->assertSame(40, (int) DB::table('medicines')->where('id', $medId)->value('quantity'));
    }

    // ------------------------------------------------------------------
    // 10. Payment validation, status consistency and receipts
    // ------------------------------------------------------------------

    public function test_negative_zero_overpayment_cancelled_and_paid_payments_are_all_rejected(): void
    {
        [$invoice] = $this->invoice(1000);
        $staff = $this->makeUser('Staff');
        $svc   = app(BillingService::class);

        foreach ([-50, 0, 1000.01] as $bad) {
            try {
                $svc->recordPayment($invoice->fresh(), ['amount' => $bad, 'payment_method' => 'Cash'], $staff);
                $this->fail("Payment of {$bad} was accepted.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('amount', $e->errors());
            }
        }

        DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'Cancelled']);
        try {
            $svc->recordPayment($invoice->fresh(), ['amount' => 10, 'payment_method' => 'Cash'], $staff);
            $this->fail('A cancelled invoice accepted a payment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame(0, DB::table('payments')->count());
    }

    public function test_staff_can_pay_over_http_and_open_the_receipt(): void
    {
        [$invoice] = $this->invoice(1000);
        $staff = $this->makeUser('Staff');
        $this->grant($staff, ['view_billing', 'record_payment', 'view_payment_history']);

        $this->actingAs($staff)->post(route('staff.billing.payments.store', $invoice->id), [
            'amount' => 400, 'payment_method' => 'Cash',
        ])->assertRedirect();

        $payment = DB::table('payments')->first();
        $this->assertNotNull($payment);
        $this->assertSame('Partially Paid', $invoice->fresh()->status);

        $this->actingAs($staff)
            ->get(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]))
            ->assertOk()->assertSee($invoice->invoice_no);

        // GCash through the real endpoint: reference number still required.
        $this->actingAs($staff)->post(route('staff.billing.payments.store', $invoice->id), [
            'amount' => 100, 'payment_method' => 'GCash',
        ])->assertSessionHasErrors('reference_number');
        $this->actingAs($staff)->post(route('staff.billing.payments.store', $invoice->id), [
            'amount' => 100, 'payment_method' => 'GCash', 'reference_number' => 'GC-9',
        ])->assertRedirect();

        $this->assertSame(2, DB::table('payments')->count());
        $this->assertEquals(500, $invoice->fresh()->amount_paid);
        $this->assertEquals(500, $invoice->fresh()->balance);
    }
}
