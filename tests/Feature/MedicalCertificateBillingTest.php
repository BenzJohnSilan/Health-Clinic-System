<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\MedicalCertificate;
use App\Models\Service;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Medical Certificate billing: combined invoice (unpaid consultation
 * invoice, Scenario A), separate invoice (paid consultation, Scenario B),
 * idempotency, and the payment-gated print/release rules.
 */
class MedicalCertificateBillingTest extends TestCase
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
            'contact_number'  => '0917000' . str_pad($this->seq, 4, '0', STR_PAD_LEFT),
            'reason'          => 'To Access Clinic Services',
            'username'        => strtolower($role) . $this->seq,
            'email'           => strtolower($role) . $this->seq . '@example.test',
            'password'        => bcrypt('password'),
            'role'            => $role,
            'status'          => 'Active',
            'approval_status' => 'Approved',
            'email_verified_at' => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return User::findOrFail($id);
    }

    private function makeAppointment(User $patient, User $doctor, string $status = 'Completed'): Appointment
    {
        $this->seq++;

        $id = DB::table('appointments')->insertGetId([
            'patient_id'       => $patient->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => '2026-09-30',
            'appointment_time' => '09:' . str_pad($this->seq % 60, 2, '0', STR_PAD_LEFT) . ':00',
            'status'           => $status,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return Appointment::findOrFail($id);
    }

    private function consultationInvoice(Appointment $appointment, User $doctor): Invoice
    {
        $consultation = Service::where('name', 'Consultation')->firstOrFail();
        $consultation->update(['price' => 500]);

        return app(BillingService::class)->syncInvoiceForAppointment(
            $appointment,
            [['service_id' => $consultation->id, 'quantity' => 1]],
            $doctor
        );
    }

    private function makeCertificate(Appointment $appointment, User $doctor): MedicalCertificate
    {
        return MedicalCertificate::create([
            'appointment_id'     => $appointment->id,
            'patient_id'         => $appointment->patient_id,
            'doctor_id'          => $doctor->id,
            'certificate_number' => MedicalCertificate::generateCertificateNumber(),
            'purpose'            => 'Work',
            'medical_statement'  => 'Fit to work.',
            'recommendation'     => 'None.',
            'status'             => 'issued',
            'requested_at'       => now(),
            'issued_at'          => now(),
        ]);
    }

    private function pay(Invoice $invoice, float $amount, User $staff): void
    {
        app(BillingService::class)->recordPayment($invoice, [
            'amount'         => $amount,
            'payment_method' => 'Cash',
        ], $staff);
    }

    private function setCertificateFee(float $price): void
    {
        Service::where('name', Service::MEDICAL_CERTIFICATE)->update(['price' => $price]);
    }

    public function test_certificate_fee_joins_unpaid_consultation_invoice(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $invoice = $this->consultationInvoice($appt, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);

        $result = app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $this->assertSame($invoice->id, $result->id, 'Should reuse the consultation invoice');
        $this->assertSame(1, Invoice::count());

        $invoice->refresh();
        $this->assertSame(2, $invoice->items()->count());
        $this->assertEquals(600.00, (float) $invoice->total_amount);
        $this->assertEquals(600.00, (float) $invoice->balance);
        $this->assertSame('Unpaid', $invoice->status);
        $this->assertSame($invoice->id, $cert->fresh()->invoice_id);
    }

    public function test_paid_consultation_invoice_is_untouched_and_certificate_gets_own_invoice(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $invoice = $this->consultationInvoice($appt, $doctor);
        $this->pay($invoice, 500, $staff);

        $cert   = $this->makeCertificate($appt, $doctor);
        $result = app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $this->assertNotSame($invoice->id, $result->id);
        $this->assertSame(2, Invoice::count());
        $this->assertNull($result->appointment_id);
        $this->assertSame($patient->id, $result->patient_id);
        $this->assertEquals(100.00, (float) $result->total_amount);
        $this->assertSame('Unpaid', $result->status);

        $invoice->refresh();
        $this->assertSame('Paid', $invoice->status);
        $this->assertEquals(500.00, (float) $invoice->total_amount);
        $this->assertSame(1, $invoice->items()->count());

        // Consultation lookup is still unambiguous.
        $this->assertSame($invoice->id, $appt->invoice()->first()->id);
    }

    public function test_partially_paid_consultation_invoice_is_not_modified(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $invoice = $this->consultationInvoice($appt, $doctor);
        $this->pay($invoice, 200, $staff);

        $cert   = $this->makeCertificate($appt, $doctor);
        $result = app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $this->assertNotSame($invoice->id, $result->id);
        $invoice->refresh();
        $this->assertSame('Partially Paid', $invoice->status);
        $this->assertEquals(500.00, (float) $invoice->total_amount);
    }

    public function test_no_consultation_invoice_yet_creates_separate_invoice(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);

        $result = app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $this->assertNull($result->appointment_id);
        $this->assertSame(1, Invoice::count());
    }

    public function test_billing_is_idempotent(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $this->consultationInvoice($appt, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);
        $billing = app(BillingService::class);

        $first = $billing->ensureInvoiceForMedicalCertificate($cert, $doctor);
        for ($i = 0; $i < 5; $i++) {
            $again = $billing->ensureInvoiceForMedicalCertificate(MedicalCertificate::find($cert->id), $doctor);
            $this->assertSame($first->id, $again->id);
        }

        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, $first->items()->count(), 'No duplicate certificate line');
        $this->assertEquals(600.00, (float) $first->fresh()->total_amount);
    }

    public function test_separate_invoice_is_idempotent(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $this->pay($this->consultationInvoice($appt, $doctor), 500, $staff);
        $cert    = $this->makeCertificate($appt, $doctor);
        $billing = app(BillingService::class);

        for ($i = 0; $i < 5; $i++) {
            $billing->ensureInvoiceForMedicalCertificate(MedicalCertificate::find($cert->id), $doctor);
        }

        $this->assertSame(2, Invoice::count());
    }

    public function test_saving_consultation_charges_keeps_certificate_line(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $invoice = $this->consultationInvoice($appt, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);
        app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        // Doctor re-saves the charges.
        $invoice = $this->consultationInvoice($appt, $doctor);

        $this->assertSame(2, $invoice->items()->count());
        $this->assertEquals(600.00, (float) $invoice->fresh()->total_amount);
    }

    public function test_certificate_fee_cannot_be_tagged_as_consultation_service(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $mc      = Service::where('name', Service::MEDICAL_CERTIFICATE)->firstOrFail();

        $invoice = app(BillingService::class)->syncInvoiceForAppointment(
            $appt,
            [['service_id' => $mc->id, 'quantity' => 1]],
            $doctor
        );

        // The certificate fee is never added as a consultation charge; only the
        // automatic Consultation line exists.
        $this->assertSame(0, $invoice->items()->where('item_type', 'medical_certificate')->count());
        $this->assertSame(0, $invoice->items()->where('service_id', $mc->id)->count());
        $this->assertSame(1, $invoice->items()->count());
    }

    public function test_combined_invoice_paid_unlocks_print_and_release_rules(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $invoice = $this->consultationInvoice($appt, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);
        app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $cert = $cert->fresh();
        $this->assertFalse($cert->isPaid());
        $this->assertFalse($cert->canBePrinted());
        $this->assertFalse($cert->canBeReleased());

        $this->pay($invoice->fresh(), 600, $staff);

        $cert = $cert->fresh();
        $this->assertTrue($cert->isPaid());
        $this->assertTrue($cert->canBePrinted());
        $this->assertTrue($cert->canBeReleased());
        $this->assertTrue($cert->isIssued(), 'Payment alone must not release the certificate');
    }

    public function test_rejecting_removes_only_the_certificate_line_from_combined_invoice(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $invoice = $this->consultationInvoice($appt, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);
        $billing = app(BillingService::class);
        $billing->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $billing->cancelInvoiceForMedicalCertificate($cert->fresh());

        $invoice->refresh();
        $this->assertSame('Unpaid', $invoice->status);
        $this->assertEquals(500.00, (float) $invoice->total_amount);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertNull($cert->fresh()->invoice_id);
    }

    public function test_staff_release_requires_paid_invoice_and_is_not_triggered_by_print(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $this->pay($this->consultationInvoice($appt, $doctor), 500, $staff);
        $cert    = $this->makeCertificate($appt, $doctor);
        $invoice = app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $this->grant($staff, ['view_medical_certificates', 'release_medical_certificate']);

        // Unpaid -> release refused.
        $this->actingAs($staff)
            ->post(route('staff.medical-certificates.release', $cert->id))
            ->assertRedirect(route('staff.medical-certificates.index'))
            ->assertSessionHas('error');
        $this->assertSame('issued', $cert->fresh()->status);

        // Doctor cannot print while unpaid.
        $this->actingAs($doctor)
            ->get(route('doctor.medical-certificates.print', $cert->id))
            ->assertStatus(403);

        $this->pay($invoice->fresh(), 100, $staff);

        // Paid -> doctor can print, but printing does not release.
        $this->actingAs($doctor)
            ->get(route('doctor.medical-certificates.print', $cert->id))
            ->assertOk();
        $this->assertSame('issued', $cert->fresh()->status);

        // Staff releases.
        $this->actingAs($staff)
            ->post(route('staff.medical-certificates.release', $cert->id))
            ->assertSessionHas('success');

        $cert = $cert->fresh();
        $this->assertSame('released', $cert->status);
        $this->assertNotNull($cert->released_at);
        $this->assertSame($staff->id, $cert->released_by);
    }

    public function test_patient_has_no_request_routes_and_sees_only_released_certificates(): void
    {
        foreach (['request', 'eligibility', 'store', 'correction.request', 'print'] as $name) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Route::has('patient.medical-certificates.' . $name),
                "patient.medical-certificates.$name must not exist"
            );
        }

        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);

        // Issued but not released: patient cannot open it.
        $this->actingAs($patient)
            ->get(route('patient.medical-certificates.show', $cert->id))
            ->assertStatus(404);

        $cert->update(['status' => 'released', 'released_at' => now()]);

        $this->actingAs($patient)
            ->get(route('patient.medical-certificates.index'))
            ->assertOk()
            ->assertDontSee('Request Medical Certificate');

        $this->actingAs($patient)
            ->get(route('patient.medical-certificates.show', $cert->id))
            ->assertOk();
    }

    public function test_doctor_cannot_create_certificate_from_incomplete_consultation(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor, 'In Progress');

        $this->actingAs($doctor)
            ->post(route('doctor.medical-certificates.store'), $this->certificatePayload($appt))
            ->assertStatus(404);

        $this->assertSame(0, MedicalCertificate::count());
    }

    public function test_doctor_create_issues_certificate_and_bills_it_once(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $invoice = $this->consultationInvoice($appt, $doctor);

        $this->actingAs($doctor)
            ->post(route('doctor.medical-certificates.store'), $this->certificatePayload($appt))
            ->assertRedirect();

        // No intermediate "pending request": it is issued straight away.
        $cert = MedicalCertificate::firstOrFail();
        $this->assertSame('issued', $cert->status);
        $this->assertNotNull($cert->certificate_number);
        $this->assertNotNull($cert->issued_at);
        $this->assertSame($doctor->id, $cert->doctor_id);
        $this->assertSame($invoice->id, $cert->invoice_id);
        $this->assertSame(1, Invoice::count());
        $this->assertEquals(600.00, (float) $invoice->fresh()->total_amount);

        // Submitting again (double click / refresh) creates nothing new.
        $this->actingAs($doctor)
            ->post(route('doctor.medical-certificates.store'), $this->certificatePayload($appt))
            ->assertRedirect(route('doctor.medical-certificates.show', $cert->id))
            ->assertSessionHas('error');

        $this->assertSame(1, MedicalCertificate::count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, $invoice->fresh()->items()->count());
    }

    public function test_doctor_create_after_paid_consultation_uses_separate_invoice(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $paid    = $this->consultationInvoice($appt, $doctor);
        $this->pay($paid, 500, $staff);

        $this->actingAs($doctor)
            ->post(route('doctor.medical-certificates.store'), $this->certificatePayload($appt))
            ->assertRedirect();

        $cert = MedicalCertificate::firstOrFail();
        $this->assertNotSame($paid->id, $cert->invoice_id);
        $this->assertSame(2, Invoice::count());
        $this->assertSame('Paid', $paid->fresh()->status);
        $this->assertEquals(500.00, (float) $paid->fresh()->total_amount);
        $this->assertSame('Unpaid', $cert->invoice->status);
    }

    public function test_old_request_workflow_routes_are_gone(): void
    {
        foreach (['issue', 'reject', 'direct.index', 'direct.store'] as $name) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Route::has('doctor.medical-certificates.' . $name),
                "doctor.medical-certificates.$name must not exist"
            );
        }

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('patient.medical-certificates.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('patient.medical-certificates.create'));
        $this->assertFileDoesNotExist(resource_path('views/patient/medical-certificates/request.blade.php'));
    }

    public function test_patient_cannot_post_or_open_old_request_urls(): void
    {
        $patient = $this->makeUser('Patient');

        $this->actingAs($patient)->post('/patient/medical-certificates', ['purpose' => 'Work'])->assertStatus(405);
        $this->actingAs($patient)->get('/patient/medical-certificates/request')->assertStatus(404);
        $this->actingAs($patient)->get('/patient/medical-certificates/eligibility')->assertStatus(404);
    }

    public function test_patient_index_lists_only_released_certificates(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $issued  = $this->makeCertificate($this->makeAppointment($patient, $doctor), $doctor);
        $released = $this->makeCertificate($this->makeAppointment($patient, $doctor), $doctor);
        $released->update(['status' => 'released', 'released_at' => now()]);

        $this->actingAs($patient)
            ->get(route('patient.medical-certificates.index'))
            ->assertOk()
            ->assertSee($released->certificate_number)
            ->assertDontSee($issued->certificate_number)
            ->assertDontSee('Request Medical Certificate');
    }

    public function test_doctor_pages_no_longer_use_patient_request_wording(): void
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);

        $page = $this->actingAs($doctor)
            ->get(route('doctor.medical-certificates.create', ['appointment_id' => $appt->id]))
            ->assertOk()
            ->assertSee('Create Medical Certificate')
            ->assertSee('Medical Certificate Details')
            ->assertDontSee('Certificate Request')
            ->assertDontSee('Back to Request');

        $this->actingAs($doctor)->get(route('doctor.medical-certificates.index'))
            ->assertOk()->assertDontSee('Pending Request');
    }

    public function test_correction_flow_still_works_and_keeps_invoice(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $this->consultationInvoice($appt, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);
        app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);

        $correction = \App\Models\MedicalCertificateCorrection::create([
            'medical_certificate_id' => $cert->id,
            'patient_id'             => $patient->id,
            'reason'                 => 'Wrong name',
            'status'                 => 'pending',
        ]);
        $cert->update(['status' => 'correction_requested']);
        $invoiceId = $cert->fresh()->invoice_id;

        $this->actingAs($doctor)->get(route('doctor.medical-certificates.correction.review', $cert->id))->assertOk();
        $this->actingAs($doctor)->get(route('doctor.medical-certificates.correction.edit', $cert->id))->assertOk();

        $this->actingAs($doctor)
            ->post(route('doctor.medical-certificates.correction.update', $cert->id), [
                'medical_statement' => 'Corrected statement.',
                'recommendation'    => 'Rest.',
            ])
            ->assertRedirect(route('doctor.medical-certificates.index'));

        $cert = $cert->fresh();
        $this->assertSame('issued', $cert->status);
        $this->assertSame('Corrected statement.', $cert->medical_statement);
        $this->assertSame($invoiceId, $cert->invoice_id);
        $this->assertSame(1, Invoice::count());
        $this->assertSame('resolved', $correction->fresh()->status);
    }

    private function certificatePayload(Appointment $appointment): array
    {
        return [
            'appointment_id'    => $appointment->id,
            'purpose'           => 'Work',
            'medical_statement' => 'Seen and examined.',
            'recommendation'    => 'Rest.',
        ];
    }

    public function test_staff_and_doctor_certificate_pages_render(): void
    {
        $this->setCertificateFee(100);
        $doctor  = $this->makeUser('Doctor');
        $staff   = $this->makeUser('Staff');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor);
        $this->consultationInvoice($appt, $doctor);
        $cert    = $this->makeCertificate($appt, $doctor);
        app(BillingService::class)->ensureInvoiceForMedicalCertificate($cert, $doctor);
        $this->grant($staff, ['view_medical_certificates', 'release_medical_certificate']);

        $this->actingAs($staff)->get(route('staff.medical-certificates.index'))
            ->assertOk()->assertSee($cert->certificate_number)->assertSee('Awaiting payment');
        $this->actingAs($staff)->get(route('staff.medical-certificates.index', ['status' => 'ready']))->assertOk();

        $this->actingAs($doctor)->get(route('doctor.medical-certificates.index'))->assertOk();
        $this->actingAs($doctor)->get(route('doctor.medical-certificates.show', $cert->id))
            ->assertOk()->assertSee('invoice is fully paid');
        $this->actingAs($doctor)->get(route('doctor.medical-certificates.create'))->assertOk();
    }

    private function grant(User $user, array $slugs): void
    {
        foreach ($slugs as $slug) {
            $id = DB::table('permissions')->where('slug', $slug)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $slug, 'slug' => $slug, 'module' => 'Medical Certificates',
                    'description' => $slug, 'created_at' => now(), 'updated_at' => now(),
                ]);

            DB::table('user_permissions')->insert([
                'user_id' => $user->id, 'permission_id' => $id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
