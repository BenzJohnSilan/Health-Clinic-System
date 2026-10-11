<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\User;
use App\Models\UserLog;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Consultation fee is automatic · Fee Waiver keeps the original fee for audit ·
 * Prescription != Purchase · Staff dispensing is the single source of truth for
 * clinic-medicine stock AND billing:
 *
 *     dispensed quantity = billed quantity = stock deducted
 */
class ConsultationWaiverAndDispensingTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // Standard consultation fee for every scenario.
        Service::where('name', Service::CONSULTATION)->update(['price' => 500, 'is_active' => true]);
    }

    // ------------------------------------------------------------------
    // fixtures
    // ------------------------------------------------------------------

    private function makeUser(string $role): User
    {
        $this->seq++;

        $id = DB::table('users')->insertGetId([
            'first_name' => $role . $this->seq, 'last_name' => 'Test',
            'birthdate' => '1990-01-01', 'gender' => 'Male', 'civil_status' => 'Single',
            'address' => 'Somewhere', 'contact_number' => '0917300' . str_pad($this->seq, 4, '0', STR_PAD_LEFT),
            'reason' => 'To Access Clinic Services',
            'username' => strtolower($role) . 'wd' . $this->seq, 'email' => strtolower($role) . 'wd' . $this->seq . '@example.test',
            'password' => bcrypt('password'), 'role' => $role, 'status' => 'Active', 'approval_status' => 'Approved', 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function grant(User $user, array $slugs): User
    {
        foreach ($slugs as $slug) {
            $id = DB::table('permissions')->where('slug', $slug)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $slug, 'slug' => $slug, 'module' => 'Test', 'description' => $slug,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('user_permissions')->insert([
                'user_id' => $user->id, 'permission_id' => $id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function doctor(array $perms = ['create_invoice', 'edit_invoice']): User
    {
        return $this->grant($this->makeUser('Doctor'), $perms);
    }

    private function dispenser(): User
    {
        return $this->grant($this->makeUser('Staff'), ['view_appointments', 'view_appointment_details', 'dispense_prescription']);
    }

    private function cashier(): User
    {
        return $this->grant($this->makeUser('Staff'), ['view_billing', 'record_payment', 'view_payment_history', 'view_billing_reports']);
    }

    private function appointment(User $doctor, string $status = 'In Progress'): Appointment
    {
        $this->seq++;
        $patient = $this->makeUser('Patient');

        $id = DB::table('appointments')->insertGetId([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'reference_no' => 'APT-T' . str_pad($this->seq, 5, '0', STR_PAD_LEFT),
            'appointment_date' => now()->toDateString(), 'appointment_time' => '09:' . str_pad($this->seq % 60, 2, '0', STR_PAD_LEFT) . ':00',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Appointment::findOrFail($id);
    }

    /** A clinic medicine with one live batch of $stock units. */
    private function medicine(float $price = 5, int $stock = 50, string $name = 'Paracetamol 500mg'): int
    {
        $id = DB::table('medicines')->insertGetId([
            'medicine_name' => $name, 'category' => 'Test', 'quantity' => $stock, 'unit' => 'tablet',
            'price' => $price, 'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'Available', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('medicine_batches')->insert([
            'medicine_id' => $id, 'batch_no' => 'B' . $id, 'quantity' => $stock,
            'expiration_date' => now()->addYear()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function stock(int $medicineId): int
    {
        return (int) DB::table('medicine_batches')->where('medicine_id', $medicineId)->sum('quantity');
    }

    /** Doctor writes a prescription through the real route. */
    private function prescribe(User $doctor, Appointment $appt, $medicineId, int $qty, ?string $manualName = null): Prescription
    {
        $payload = [
            'appointment_id' => $appt->id, 'medicine_id' => $medicineId ?? 'manual',
            'dosage' => '500mg', 'frequency' => '3x a day', 'duration' => '5 days',
            'quantity_prescribed' => $qty,
        ];
        if ($medicineId === null) {
            $payload['manual_medicine_name'] = $manualName ?? 'Amoxicillin 500mg';
        }

        $this->actingAs($doctor)->post(route('doctor.prescriptions.store'), $payload)->assertSessionHasNoErrors();

        return Prescription::where('appointment_id', $appt->id)->latest('id')->firstOrFail();
    }

    private function complete(User $doctor, Appointment $appt, array $extra = [])
    {
        return $this->actingAs($doctor)->patch(route('doctor.appointments.completeConsultation', $appt->id), array_merge([
            'chief_complaint' => 'Cough', 'diagnosis' => 'Common cold', 'treatment' => 'Rest and fluids',
        ], $extra));
    }

    private function dispense(User $staff, Prescription $rx, int $qty)
    {
        return $this->actingAs($staff)->patch(route('staff.prescriptions.dispense', $rx->id), ['dispensed_quantity' => $qty]);
    }

    private function invoiceOf(Appointment $appt): Invoice
    {
        return Invoice::where('appointment_id', $appt->id)->firstOrFail();
    }

    // ==================================================================
    // A. Normal consultation — automatic, no checkbox
    // ==================================================================

    public function test_a_consultation_is_charged_automatically_without_any_checkbox(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);

        // The Charges step offers no Consultation checkbox, only an "automatic" row.
        $consult = Service::where('name', Service::CONSULTATION)->firstOrFail();
        $page = $this->actingAs($doctor)->get(route('doctor.appointments.show', $appt->id));
        $page->assertOk();
        $html = $page->getContent();
        $this->assertStringNotContainsString('name="service_ids[]" value="' . $consult->id . '"', $html);
        $this->assertStringContainsString('charged automatically', $html);
        $this->assertStringContainsString('Fee Waiver / No Charge', $html);

        // Complete with nothing prepared at all.
        $this->complete($doctor, $appt)->assertSessionHas('success');

        $invoice = $this->invoiceOf($appt);
        $this->assertSame('Completed', $appt->fresh()->status);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('Consultation', $invoice->items()->first()->description);
        $this->assertEquals(500, $invoice->subtotal);
        $this->assertEquals(0, $invoice->discount);
        $this->assertEquals(500, $invoice->total_amount);
        $this->assertSame('Unpaid', $invoice->status);
    }

    public function test_a_consultation_is_never_duplicated_when_saved_or_completed_more_than_once(): void
    {
        $doctor  = $this->doctor();
        $appt    = $this->appointment($doctor);
        $consult = Service::where('name', Service::CONSULTATION)->firstOrFail();

        // Saved as a draft twice (even with the Consultation id sneaked into the list)…
        foreach ([1, 2] as $_) {
            $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
                'service_ids' => [$consult->id], 'service_qty' => [$consult->id => 3],
            ])->assertSessionHasNoErrors();
        }
        $this->complete($doctor, $appt);

        // …and re-saved after completion.
        foreach ([1, 2] as $_) {
            $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [])
                ->assertSessionHasNoErrors();
        }

        $invoice = $this->invoiceOf($appt);
        $this->assertSame(1, $invoice->items()->count(), 'Exactly one Consultation line.');
        $this->assertSame(1, (int) $invoice->items()->first()->quantity);
        $this->assertEquals(500, $invoice->total_amount);
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_consultation_is_charged_even_if_the_doctor_has_no_billing_permission(): void
    {
        $doctor = $this->doctor([]);
        $appt   = $this->appointment($doctor);

        $this->complete($doctor, $appt)->assertSessionHas('success');

        $this->assertEquals(500, $this->invoiceOf($appt)->total_amount);
    }

    public function test_a_optional_extra_services_can_still_be_added(): void
    {
        $doctor  = $this->doctor();
        $appt    = $this->appointment($doctor);
        $extraId = DB::table('services')->insertGetId([
            'name' => 'Nebulization', 'description' => 'x', 'price' => 200, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'service_ids' => [$extraId], 'service_qty' => [$extraId => 1],
        ]);
        $this->complete($doctor, $appt);

        $this->assertEquals(700, $this->invoiceOf($appt)->total_amount);
    }

    // ==================================================================
    // B. Fee waiver
    // ==================================================================

    public function test_b_waiver_keeps_the_original_fee_and_nets_to_zero(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);

        $this->complete($doctor, $appt, [
            'charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'courtesy',
        ])->assertSessionHasNoErrors();

        $invoice = $this->invoiceOf($appt);
        $line    = $invoice->items()->firstOrFail();

        // Original fee preserved — never overwritten to 0.
        $this->assertEquals(500, $line->unit_price);
        $this->assertEquals(500, $line->amount);
        $this->assertEquals(500, Service::where('name', 'Consultation')->value('price'));

        // Waived amount, net, reason and who/when are stored.
        $this->assertTrue($line->isWaiver());
        $this->assertEquals(500, $line->discount_amount);
        $this->assertEquals(0, $line->netAmount());
        $this->assertSame('courtesy', $line->waiver_reason);
        $this->assertSame('Doctor/Clinic Courtesy', $line->waiverReasonLabel());
        $this->assertSame($doctor->id, (int) $line->waived_by);
        $this->assertNotNull($line->waived_at);

        // Invoice: ₱500 − ₱500 = ₱0, nothing to collect.
        $this->assertEquals(500, $invoice->subtotal);
        $this->assertEquals(500, $invoice->discount);
        $this->assertEquals(0, $invoice->total_amount);
        $this->assertEquals(0, $invoice->balance);
        $this->assertSame('Paid', $invoice->status);
        $this->assertSame(0, Payment::count(), 'A waiver is not a payment.');
    }

    public function test_b_only_the_four_reasons_are_accepted_and_a_reason_is_required(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);

        $this->assertSame(['courtesy', 'follow_up', 'financial_hardship', 'other'], array_keys(config('billing.waiver_reasons')));

        // no reason
        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1])
            ->assertSessionHasErrors('waiver_reason');
        // a reason that is not on the list
        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'community_program'])
            ->assertSessionHasErrors('waiver_reason');

        // Nothing was half-completed.
        $this->assertSame('In Progress', $appt->fresh()->status);
        $this->assertSame(0, Invoice::count());

        // The same rules apply when saving charges.
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), ['fee_waiver' => 1])
            ->assertSessionHasErrors('waiver_reason');
        $this->assertNull($appt->fresh()->billing_draft);
    }

    public function test_b_other_requires_an_explanation(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);

        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'other'])
            ->assertSessionHasErrors('waiver_notes');
        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'other', 'waiver_notes' => '   '])
            ->assertSessionHasErrors('waiver_notes');
        $this->assertSame('In Progress', $appt->fresh()->status);

        $this->complete($doctor, $appt, [
            'charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'other',
            'waiver_notes' => 'Staff member of a partner clinic',
        ])->assertSessionHasNoErrors();

        $line = $this->invoiceOf($appt)->items()->firstOrFail();
        $this->assertSame('other', $line->waiver_reason);
        $this->assertSame('Staff member of a partner clinic', $line->waiver_notes);
    }

    public function test_b_waiver_saved_with_charges_is_applied_on_completion_and_the_box_can_be_cleared(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'fee_waiver' => 1, 'waiver_reason' => 'follow_up',
        ])->assertSessionHasNoErrors();

        // Completing without re-sending the form uses the saved draft.
        $this->complete($doctor, $appt);
        $this->assertSame('follow_up', $this->invoiceOf($appt)->items()->first()->waiver_reason);

        // Doctor changes their mind before any payment: un-waive.
        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [])
            ->assertSessionHasNoErrors();

        $invoice = $this->invoiceOf($appt);
        $line    = $invoice->items()->first();
        $this->assertFalse($line->isWaiver());
        $this->assertNull($line->waiver_reason);
        $this->assertEquals(500, $invoice->total_amount);
        $this->assertSame('Unpaid', $invoice->status);
    }

    public function test_b_what_is_on_screen_wins_over_an_older_draft_at_completion(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'fee_waiver' => 1, 'waiver_reason' => 'courtesy',
        ]);

        // The page now shows the box unticked and sends that along with Complete.
        $this->complete($doctor, $appt, ['charges_mirrored' => 1])->assertSessionHasNoErrors();

        $this->assertEquals(500, $this->invoiceOf($appt)->total_amount);
    }

    public function test_b_waiver_is_always_the_whole_consultation_fee_never_an_arbitrary_amount(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);

        $this->complete($doctor, $appt, [
            'charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'courtesy',
            // crafted fields that must be ignored
            'waiver_amount' => 100, 'discount_amount' => 1, 'unit_price' => 1, 'total_amount' => 1, 'amount' => 1,
        ]);

        $line = $this->invoiceOf($appt)->items()->firstOrFail();
        $this->assertEquals(500, $line->discount_amount);
        $this->assertEquals(500, $line->unit_price);
        $this->assertEquals(0, $this->invoiceOf($appt)->total_amount);
    }

    public function test_b_a_doctor_without_billing_permission_cannot_waive(): void
    {
        $doctor = $this->doctor([]);
        $appt   = $this->appointment($doctor);

        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'courtesy'])
            ->assertForbidden();

        $this->assertSame('In Progress', $appt->fresh()->status);
        $this->assertSame(0, Invoice::count());

        $this->actingAs($doctor)->patch(route('doctor.appointments.saveCharges', $appt->id), [
            'fee_waiver' => 1, 'waiver_reason' => 'courtesy',
        ])->assertForbidden();
    }

    public function test_b_staff_cannot_change_or_remove_a_doctors_waiver(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $rx     = $this->prescribe($doctor, $appt, $this->medicine(5, 50), 10);
        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'courtesy']);
        $this->dispense($this->dispenser(), $rx, 6);

        $invoice = $this->invoiceOf($appt);
        $waived  = $invoice->items()->get()->first(fn ($i) => $i->isWaiver());
        $medLine = $invoice->items()->where('item_type', 'medicine')->first();

        // The cashier tries to clear the waiver and discounts the medicine instead.
        $this->actingAs($this->cashier())->post(route('staff.billing.discounts.update', $invoice->id), [
            'items' => [
                $waived->id  => ['discount_type' => ''],
                $medLine->id => ['discount_type' => 'senior_citizen'],
            ],
        ])->assertSessionHasNoErrors();

        $waived = $waived->fresh();
        $this->assertTrue($waived->isWaiver(), 'The waiver is untouched.');
        $this->assertEquals(500, $waived->discount_amount);
        $this->assertSame('courtesy', $waived->waiver_reason);
        $this->assertEquals(24, $invoice->fresh()->total_amount, 'Only the medicine got the 20% discount: 30 − 6.');
    }

    public function test_b_waiver_is_visible_on_staff_billing_the_receipt_and_the_patient_invoice(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $rx     = $this->prescribe($doctor, $appt, $this->medicine(5, 50), 10);
        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'financial_hardship']);
        $this->dispense($this->dispenser(), $rx, 6);
        $invoice = $this->invoiceOf($appt);

        $cashier = $this->cashier();
        $show = $this->actingAs($cashier)->get(route('staff.billing.show', $invoice->id));
        $show->assertOk();
        $text = html_entity_decode($show->getContent(), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('Consultation Waiver — Financial Hardship', $text);
        $this->assertStringContainsString('original fee ₱500.00', $text);
        $this->assertStringContainsString('Locked — applied by', $text);

        // receipt after a payment
        $this->actingAs($cashier)->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 30, 'payment_method' => 'Cash']);
        $payment = Payment::firstOrFail();
        $receipt = $this->actingAs($cashier)->get(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]));
        $receipt->assertOk();
        $this->assertStringContainsString('Consultation Waiver', html_entity_decode($receipt->getContent(), ENT_QUOTES | ENT_HTML5));

        // the patient sees why the line is waived (patients hold view_billing by default)
        $this->grant($appt->patient, ['view_billing', 'view_payment_history']);
        $patientPage = $this->actingAs($appt->patient)->get(route('patient.billing.show', $invoice->id));
        $patientPage->assertOk();
        $this->assertStringContainsString('Fee waived', html_entity_decode($patientPage->getContent(), ENT_QUOTES | ENT_HTML5));
    }

    // ==================================================================
    // C / E. Prescription is not a purchase
    // ==================================================================

    public function test_c_a_clinic_prescription_deducts_nothing_and_bills_nothing(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);

        $rx = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);

        $this->assertSame(10, (int) $rx->quantity_prescribed);
        $this->assertSame('Pending', $rx->fresh()->dispense_status);
        $this->assertNull($rx->fresh()->dispensed_quantity);

        $this->assertSame(50, $this->stock($med), 'No stock deducted by a prescription.');
        $this->assertSame(0, DB::table('medicine_stock_movements')->count());

        $invoice = $this->invoiceOf($appt);
        $this->assertSame(0, $invoice->items()->where('item_type', 'medicine')->count(), 'No medicine billed yet.');
        $this->assertEquals(500, $invoice->total_amount);
    }

    public function test_e_other_manual_input_is_saved_but_never_stocked_dispensed_or_billed(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $this->medicine(5, 50);

        $manual = $this->prescribe($doctor, $appt, null, 10, 'Amoxicillin 500mg');
        $this->complete($doctor, $appt);

        // Saved as part of the prescription / medical record.
        $this->assertNull($manual->medicine_id);
        $this->assertSame('Amoxicillin 500mg', $manual->manual_medicine_name);
        $this->assertTrue($manual->isOutsidePharmacy());

        // Staff cannot dispense it; nothing changes.
        $staff = $this->dispenser();
        $this->dispense($staff, $manual, 10)->assertSessionHas('error');

        $this->assertSame('Pending', $manual->fresh()->dispense_status);
        $this->assertNull($manual->fresh()->dispensed_quantity);
        $this->assertSame(0, DB::table('medicine_stock_movements')->count());
        $this->assertSame(0, InvoiceItem::where('item_type', 'medicine')->count());
        $this->assertEquals(500, $this->invoiceOf($appt)->total_amount);

        // It is not offered for clinic dispensing on the Staff page.
        $page = $this->actingAs($staff)->get(route('staff.appointments.show', $appt->id));
        $page->assertOk();
        $this->assertStringContainsString('Outside pharmacy', $page->getContent());
        $page->assertDontSee(route('staff.prescriptions.dispense', $manual->id), false);

        // …and the billing layer refuses it even when called directly.
        $this->expectException(ValidationException::class);
        app(BillingService::class)->addDispensedMedicineToInvoice($manual->fresh(), 5, $staff);
    }

    // ==================================================================
    // D. Patient buys clinic medicine — the headline example
    // ==================================================================

    public function test_d_patient_buys_6_of_10_prescribed_stock_and_bill_both_use_6(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);                 // ₱5 each, stock 50
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);

        $staff = $this->dispenser();
        $this->dispense($staff, $rx, 6)->assertSessionHas('success')->assertSessionHasNoErrors();

        // Inventory 50 → 44 (not 40).
        $this->assertSame(44, $this->stock($med));
        $this->assertSame(44, (int) DB::table('medicines')->where('id', $med)->value('quantity'));

        // Billing: 6 × ₱5 = ₱30 (not ₱50).
        $invoice = $this->invoiceOf($appt)->fresh();
        $line    = $invoice->items()->where('item_type', 'medicine')->firstOrFail();
        $this->assertSame(6, (int) $line->quantity);
        $this->assertEquals(5, $line->unit_price);
        $this->assertEquals(30, $line->amount);
        $this->assertSame($rx->id, (int) $line->prescription_id);

        // Final invoice: Consultation ₱500 + Paracetamol ×6 ₱30 = ₱530.
        $this->assertSame(2, $invoice->items()->count());
        $this->assertEquals(530, $invoice->subtotal);
        $this->assertEquals(530, $invoice->total_amount);
        $this->assertSame('Unpaid', $invoice->status);

        // Dispensed = billed = deducted = 6; prescribed stays 10 and is honestly shown as partial.
        $movements = DB::table('medicine_stock_movements')->where('movement_type', 'dispense')->get();
        $this->assertSame(-6, (int) $movements->sum('quantity_change'));
        $rx = $rx->fresh();
        $this->assertSame(6, (int) $rx->dispensed_quantity);
        $this->assertSame(10, (int) $rx->quantity_prescribed);
        $this->assertSame($staff->id, (int) $rx->dispensed_by);
        $this->assertNotNull($rx->dispensed_at);

        // Audit trail names who, what, how many, which visit and which invoice.
        $log = UserLog::where('action', 'Dispensed Medicine')->latest('id')->firstOrFail();
        $this->assertSame($staff->id, (int) $log->user_id);
        $this->assertStringContainsString('6 of 10', $log->details);
        $this->assertStringContainsString($appt->reference_no, $log->details);
        $this->assertStringContainsString($invoice->invoice_no, $log->details);
        $this->assertStringContainsString($invoice->invoice_no, (string) $movements->first()->notes);
    }

    public function test_d_dispensing_twice_never_duplicates_stock_deduction_or_the_invoice_line(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);
        $staff = $this->dispenser();

        $this->dispense($staff, $rx, 6)->assertSessionHas('success');
        $this->dispense($staff, $rx, 6)->assertSessionHas('error');   // double click / second tab
        $this->dispense($staff, $rx, 4)->assertSessionHas('error');

        $this->assertSame(44, $this->stock($med));
        $this->assertSame(1, DB::table('medicine_stock_movements')->count());
        $this->assertSame(1, InvoiceItem::where('item_type', 'medicine')->count());
        $this->assertEquals(530, $this->invoiceOf($appt)->total_amount);

        // The billing layer is idempotent on its own too.
        $again = app(BillingService::class)->addDispensedMedicineToInvoice($rx->fresh(), 6, $staff);
        $this->assertSame(1, InvoiceItem::where('item_type', 'medicine')->count());
        $this->assertSame($rx->id, (int) $again->prescription_id);

        // A unique key backs it up at database level.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('invoice_items')->insert([
            'invoice_id' => $this->invoiceOf($appt)->id, 'description' => 'dup', 'item_type' => 'medicine',
            'prescription_id' => $rx->id, 'quantity' => 1, 'unit_price' => 1, 'amount' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_d_invalid_or_excessive_quantities_and_insufficient_stock_change_nothing(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 3);                  // only 3 in stock
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);
        $staff = $this->dispenser();

        $this->dispense($staff, $rx, 0)->assertSessionHasErrors('dispensed_quantity');
        $this->dispense($staff, $rx, -2)->assertSessionHasErrors('dispensed_quantity');
        $this->dispense($staff, $rx, 11)->assertSessionHas('error');                // more than prescribed
        $this->dispense($staff, $rx, 5)->assertSessionHasErrors('dispensed_quantity'); // more than available

        $this->assertSame(3, $this->stock($med));
        $this->assertSame('Pending', $rx->fresh()->dispense_status);
        $this->assertSame(0, DB::table('medicine_stock_movements')->count());
        $this->assertSame(0, InvoiceItem::where('item_type', 'medicine')->count());
        $this->assertEquals(500, $this->invoiceOf($appt)->total_amount);

        // Exactly what is available is fine.
        $this->dispense($staff, $rx, 3)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->stock($med));
        $this->assertEquals(515, $this->invoiceOf($appt)->fresh()->total_amount);
    }

    public function test_d_expired_stock_cannot_be_dispensed_or_billed(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        DB::table('medicine_batches')->where('medicine_id', $med)->update(['expiration_date' => now()->subDay()->toDateString()]);
        $rx = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);

        $this->dispense($this->dispenser(), $rx, 2)->assertSessionHasErrors('dispensed_quantity');

        $this->assertSame(0, InvoiceItem::where('item_type', 'medicine')->count());
        $this->assertSame('Pending', $rx->fresh()->dispense_status);
    }

    public function test_d_nothing_can_be_dispensed_before_the_consultation_is_completed(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);            // still In Progress
        $med    = $this->medicine(5, 50);
        $rx     = $this->prescribe($doctor, $appt, $med, 10);

        $this->dispense($this->dispenser(), $rx, 6)->assertSessionHas('error');

        $this->assertSame(50, $this->stock($med));
        $this->assertSame(0, Invoice::count());
    }

    public function test_d_medicine_is_billed_on_a_separate_invoice_when_the_visit_invoice_is_already_paid(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        $rx1    = $this->prescribe($doctor, $appt, $med, 10);
        $rx2    = $this->prescribe($doctor, $appt, $this->medicine(8, 20, 'Cetirizine'), 4);
        $this->complete($doctor, $appt);

        $cashier = $this->cashier();
        $main    = $this->invoiceOf($appt);
        $this->actingAs($cashier)->post(route('staff.billing.payments.store', $main->id), ['amount' => 500, 'payment_method' => 'Cash']);
        $this->assertSame('Paid', $main->fresh()->status);

        $staff = $this->dispenser();
        $this->dispense($staff, $rx1, 6)->assertSessionHasNoErrors();
        $this->dispense($staff, $rx2, 2)->assertSessionHasNoErrors();

        // The paid invoice is never modified…
        $this->assertEquals(500, $main->fresh()->total_amount);
        $this->assertSame(1, $main->items()->count());
        $this->assertSame('Paid', $main->fresh()->status);

        // …both medicines share ONE new unpaid invoice for the same patient.
        $separate = Invoice::whereNull('appointment_id')->get();
        $this->assertCount(1, $separate);
        $this->assertSame($appt->patient_id, (int) $separate->first()->patient_id);
        $this->assertSame(2, $separate->first()->items()->count());
        $this->assertEquals(30 + 16, $separate->first()->total_amount);
        $this->assertSame('Unpaid', $separate->first()->status);
    }

    public function test_d_a_medicine_already_billed_by_the_old_flow_is_adopted_not_billed_twice(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);

        // Simulate an invoice created before this change: a medicine line for 10, no prescription link.
        $invoice = $this->invoiceOf($appt);
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoice->id, 'description' => 'Paracetamol 500mg', 'item_type' => 'medicine',
            'medicine_id' => $med, 'prescription_id' => null, 'quantity' => 10, 'unit_price' => 5, 'amount' => 50,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(BillingService::class)->recalculateInvoiceTotals($invoice->fresh());
        $this->assertEquals(550, $invoice->fresh()->total_amount);

        // Patient actually takes 6: that line becomes 6, no second line is added.
        $this->dispense($this->dispenser(), $rx, 6)->assertSessionHasNoErrors();

        $invoice = $invoice->fresh();
        $this->assertSame(1, $invoice->items()->where('item_type', 'medicine')->count());
        $this->assertSame(6, (int) $invoice->items()->where('item_type', 'medicine')->first()->quantity);
        $this->assertSame($rx->id, (int) $invoice->items()->where('item_type', 'medicine')->first()->prescription_id);
        $this->assertEquals(530, $invoice->total_amount);
        $this->assertSame(44, $this->stock($med));
    }

    // ==================================================================
    // Examples that must work end to end (Parts 16 & 17)
    // ==================================================================

    public function test_part_16_example_530_paid_in_cash_with_change_70_and_receipt(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);
        $this->dispense($this->dispenser(), $rx, 6);

        $invoice = $this->invoiceOf($appt)->fresh();
        $this->assertEquals(530, $invoice->total_amount);

        // Cash received ₱600 → change ₱70. (Tendered/change is the cashier's on-screen helper;
        // the payment recorded is the amount due.)
        $cashier = $this->cashier();
        $page = $this->actingAs($cashier)->get(route('staff.billing.show', $invoice->id));
        $this->assertStringContainsString('id="amountTendered"', $page->getContent());
        $this->assertSame(70.0, 600.0 - (float) $invoice->total_amount);

        $this->actingAs($cashier)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 530, 'payment_method' => 'Cash'])
            ->assertSessionHasNoErrors();

        $invoice = $invoice->fresh();
        $this->assertSame('Paid', $invoice->status);
        $this->assertEquals(530, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance);

        $payment = Payment::firstOrFail();
        $this->actingAs($cashier)
            ->get(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]))
            ->assertOk();
    }

    public function test_part_17_waived_consultation_still_bills_medicine_normally(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt, ['charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'courtesy']);

        // Right after completion nothing is due.
        $this->assertEquals(0, $this->invoiceOf($appt)->total_amount);
        $this->assertSame('Paid', $this->invoiceOf($appt)->status);

        $this->dispense($this->dispenser(), $rx, 6)->assertSessionHasNoErrors();

        $invoice = $this->invoiceOf($appt)->fresh();
        $this->assertSame(1, Invoice::count(), 'The medicine joins the same, still-unpaid invoice.');
        $this->assertSame(2, $invoice->items()->count());
        $this->assertEquals(530, $invoice->subtotal);      // 500 + 30
        $this->assertEquals(500, $invoice->discount);      // the waiver
        $this->assertEquals(30, $invoice->total_amount);
        $this->assertEquals(30, $invoice->balance);
        $this->assertSame('Unpaid', $invoice->status, 'It becomes collectible again because medicine was added.');
        $this->assertSame(44, $this->stock($med));

        // The cashier collects only ₱30.
        $this->actingAs($this->cashier())
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 30, 'payment_method' => 'Cash'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Paid', $invoice->fresh()->status);
    }

    // ==================================================================
    // F / G. Partial and full payment
    // ==================================================================

    public function test_f_and_g_partial_then_full_payment(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $rx     = $this->prescribe($doctor, $appt, $this->medicine(5, 50), 10);
        $this->complete($doctor, $appt);
        $this->dispense($this->dispenser(), $rx, 6);
        $invoice = $this->invoiceOf($appt);
        $cashier = $this->cashier();

        // Partial
        $this->actingAs($cashier)->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 300, 'payment_method' => 'Cash'])
            ->assertSessionHasNoErrors();
        $invoice = $invoice->fresh();
        $this->assertSame('Partially Paid', $invoice->status);
        $this->assertEquals(300, $invoice->amount_paid);
        $this->assertEquals(230, $invoice->balance);

        // Over the balance is refused.
        $this->actingAs($cashier)->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 231, 'payment_method' => 'Cash'])
            ->assertSessionHasErrors('amount');
        $this->assertEquals(230, $invoice->fresh()->balance);

        // GCash needs its reference number.
        $this->actingAs($cashier)->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 100, 'payment_method' => 'GCash'])
            ->assertSessionHasErrors('reference_number');

        // The rest
        $this->actingAs($cashier)->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 230, 'payment_method' => 'GCash', 'reference_number' => 'GC123'])
            ->assertSessionHasNoErrors();
        $invoice = $invoice->fresh();
        $this->assertSame('Paid', $invoice->status);
        $this->assertEquals(530, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance);
        $this->assertSame(2, Payment::count());

        // A settled invoice accepts nothing more, and a late medicine can no longer be forced onto it.
        $this->actingAs($cashier)->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 1, 'payment_method' => 'Cash'])
            ->assertSessionHasErrors('amount');
    }

    // ==================================================================
    // H / I / J. Authorization and crafted requests
    // ==================================================================

    public function test_h_billing_permissions_work_and_a_cashier_cannot_dispense(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);
        $invoice = $this->invoiceOf($appt);
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get(route('staff.billing.show', $invoice->id))->assertOk();

        // Billing permission is not dispensing permission.
        $this->dispense($cashier, $rx, 6)->assertForbidden();
        $this->actingAs($cashier)->get(route('staff.appointments.show', $appt->id))->assertForbidden();

        $this->assertSame(50, $this->stock($med));
        $this->assertSame(0, InvoiceItem::where('item_type', 'medicine')->count());
    }

    public function test_i_other_staff_keep_their_permissions_and_billing_stays_permission_based(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $rx     = $this->prescribe($doctor, $appt, $this->medicine(5, 50), 10);
        $this->complete($doctor, $appt);
        $invoice = $this->invoiceOf($appt);

        // Dispensing permission works on its own — and gives NO billing access.
        $dispenser = $this->dispenser();
        $this->dispense($dispenser, $rx, 6)->assertSessionHas('success');
        $this->actingAs($dispenser)->get(route('staff.billing.index'))->assertForbidden();
        $this->actingAs($dispenser)->get(route('staff.billing.show', $invoice->id))->assertForbidden();
        $this->actingAs($dispenser)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 10, 'payment_method' => 'Cash'])
            ->assertForbidden();

        // A Staff with no permissions at all cannot dispense either.
        $nobody = $this->makeUser('Staff');
        $rx2 = $this->prescribe($doctor, $this->appointment($doctor), $this->medicine(5, 50, 'Other Med'), 4);
        $this->assertSame(0, Payment::count());
        Appointment::whereKey($rx2->appointment_id)->update(['status' => 'Completed']);
        $this->dispense($nobody, $rx2, 2)->assertForbidden();
    }

    public function test_j_crafted_requests_and_other_roles_are_blocked(): void
    {
        $doctor = $this->doctor();
        $appt   = $this->appointment($doctor);
        $med    = $this->medicine(5, 50);
        $rx     = $this->prescribe($doctor, $appt, $med, 10);
        $this->complete($doctor, $appt);
        $staff = $this->dispenser();

        // A Doctor / Patient cannot dispense.
        $this->dispense($doctor, $rx, 6)->assertForbidden();
        $this->dispense($appt->patient, $rx, 6)->assertForbidden();

        // A patient / staff cannot reach the Doctor's charges or completion.
        $other = $this->appointment($doctor);
        $this->actingAs($staff)->patch(route('doctor.appointments.saveCharges', $other->id), ['fee_waiver' => 1, 'waiver_reason' => 'courtesy'])
            ->assertForbidden();
        $this->actingAs($appt->patient)->patch(route('doctor.appointments.completeConsultation', $other->id), [
            'chief_complaint' => 'x', 'diagnosis' => 'x', 'treatment' => 'x',
        ])->assertForbidden();

        // Another doctor cannot waive someone else's consultation.
        $intruder = $this->doctor();
        $this->actingAs($intruder)->patch(route('doctor.appointments.saveCharges', $other->id), ['fee_waiver' => 1, 'waiver_reason' => 'courtesy'])
            ->assertNotFound();
        $this->actingAs($intruder)->patch(route('doctor.appointments.completeConsultation', $other->id), [
            'chief_complaint' => 'x', 'diagnosis' => 'x', 'treatment' => 'x', 'charges_mirrored' => 1, 'fee_waiver' => 1, 'waiver_reason' => 'courtesy',
        ])->assertNotFound();
        $this->assertSame('In Progress', $other->fresh()->status);

        // Nothing leaked through any of the above.
        $this->assertSame(50, $this->stock($med));
        $this->assertSame(0, InvoiceItem::where('item_type', 'medicine')->count());
    }
}
