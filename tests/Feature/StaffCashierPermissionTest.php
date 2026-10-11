<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;
use App\Services\BillingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A "Cashier" is NOT a role: it is a Staff account (role = Staff,
 * position = "Cashier") whose access is limited by permissions. These
 * tests prove that:
 *
 *  - the account is created through the normal Add User flow,
 *  - the Position text never grants (or removes) access by itself,
 *  - the one shared Staff Dashboard, the sidebar, the quick actions and
 *    the notification bell all follow permissions,
 *  - every route stays protected when a URL is typed by hand,
 *  - the dashboard figures come from the real billing tables.
 */
class StaffCashierPermissionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // ------------------------------------------------------------------
    // fixtures
    // ------------------------------------------------------------------

    private function makeUser(string $role, ?string $position = null): User
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
            'reason'          => 'To Access Clinic Services',
            'username'        => strtolower($role) . 'csh' . $this->seq,
            'email'           => strtolower($role) . 'csh' . $this->seq . '@example.test',
            'password'        => bcrypt('password'),
            'role'            => $role,
            'position'        => $position,
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
                    'name' => $slug, 'slug' => $slug, 'module' => 'Test',
                    'description' => $slug, 'created_at' => now(), 'updated_at' => now(),
                ]);

            DB::table('user_permissions')->insert([
                'user_id' => $user->id, 'permission_id' => $id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** A Staff member labelled "Cashier" holding exactly $slugs. */
    private function cashier(array $slugs = Permission::CASHIER_SLUGS): User
    {
        $staff = $this->makeUser('Staff', 'Cashier');
        $this->grant($staff, $slugs);

        return $staff;
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

    /** A collectible (Completed-consultation) invoice for $price. */
    private function invoice(float $price = 1000): Invoice
    {
        $doctor  = $this->makeUser('Doctor');
        $patient = $this->makeUser('Patient');
        $appt    = $this->makeAppointment($patient, $doctor, 'Completed');

        $service = \App\Models\Service::where('name', 'Consultation')->firstOrFail();
        $service->update(['price' => $price]);

        return app(BillingService::class)->syncInvoiceForAppointment(
            $appt, [['service_id' => $service->id, 'quantity' => 1]], $doctor
        );
    }

    private function pay(Invoice $invoice, float $amount, string $method = 'Cash', ?User $by = null): Payment
    {
        return app(BillingService::class)->recordPayment(
            $invoice->fresh(),
            ['amount' => $amount, 'payment_method' => $method, 'reference_number' => $method === 'GCash' ? 'GC-' . $this->seq++ : null],
            $by ?? $this->makeUser('Staff')
        );
    }

    /** Rendered page text with HTML entities decoded (apostrophes, &amp;). */
    private function text($response): string
    {
        return html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5);
    }

    private function assertPageHas($response, string $needle): void
    {
        $this->assertStringContainsString($needle, $this->text($response), "Expected the page to contain: {$needle}");
    }

    private function assertPageLacks($response, string $needle): void
    {
        $this->assertStringNotContainsString($needle, $this->text($response), "Expected the page NOT to contain: {$needle}");
    }

    // ------------------------------------------------------------------
    // PART 1/2/3 — creating the account, presets, defaults
    // ------------------------------------------------------------------

    private function addUserPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'birthdate'      => '1995-05-05',
            'gender'         => 'Male',
            'civil_status'   => 'Single',
            'address'        => 'Sample St',
            'contact_number' => '09171234567',
            'username'       => 'juan.cashier',
            'email'          => 'juan.cashier@example.test',
            'role'           => 'Staff',
            'status'         => 'Active',
            'employee_id'    => 'EMP-0099',
            'position'       => 'Cashier',
        ], $overrides);
    }

    public function test_cashier_is_created_as_a_normal_staff_account_with_billing_permissions_only(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->makeUser('Admin');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->addUserPayload(['permission_preset' => 'cashier']))
            ->assertSessionHasNoErrors();

        $juan = User::where('email', 'juan.cashier@example.test')->firstOrFail();

        // Role stays Staff — there is no separate Cashier role.
        $this->assertSame('Staff', $juan->role);
        $this->assertSame('Cashier', $juan->position);
        $this->assertSame(0, User::where('role', 'Cashier')->count());

        $slugs = $juan->permissions()->pluck('slug')->sort()->values()->all();
        $expected = Permission::CASHIER_SLUGS;
        sort($expected);
        $this->assertSame($expected, $slugs, 'Cashier preset must grant exactly the four billing permissions.');

        foreach (['view_appointments', 'view_patients', 'view_medicine_inventory', 'view_laboratory_requests', 'view_medical_certificates'] as $slug) {
            $this->assertFalse($juan->hasPermission($slug), "{$slug} must NOT be granted automatically to a Cashier.");
        }
    }

    public function test_staff_created_without_a_preset_still_gets_the_standard_defaults(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->makeUser('Admin');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->addUserPayload([
                'username' => 'plain.staff', 'email' => 'plain.staff@example.test',
                'contact_number' => '09171234568', 'employee_id' => 'EMP-0100', 'position' => 'Receptionist',
            ]))
            ->assertSessionHasNoErrors();

        $staff = User::where('email', 'plain.staff@example.test')->firstOrFail();

        $actual = $staff->permissions()->pluck('slug')->sort()->values()->all();
        $expected = Permission::defaultSlugsForRole('Staff');
        sort($expected);

        $this->assertSame($expected, $actual, 'Existing default Staff behaviour must be unchanged.');
        $this->assertTrue($staff->hasPermission('view_appointments'));
        $this->assertTrue($staff->hasPermission('view_billing'));
    }

    public function test_unknown_preset_is_rejected_and_preset_is_ignored_for_other_roles(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->makeUser('Admin');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->addUserPayload(['permission_preset' => 'superuser']))
            ->assertSessionHasErrors('permission_preset');
        $this->assertSame(0, User::where('email', 'juan.cashier@example.test')->count());

        // A Doctor submitted with a stray preset still gets the Doctor defaults.
        $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->addUserPayload([
                'role' => 'Doctor', 'specialization' => 'GP', 'license_number' => 'LIC-1',
                'username' => 'dr.preset', 'email' => 'dr.preset@example.test',
                'contact_number' => '09171234569', 'permission_preset' => 'cashier',
                'employee_id' => null, 'position' => null,
            ]))
            ->assertSessionHasNoErrors();

        $doctor = User::where('email', 'dr.preset@example.test')->firstOrFail();
        $actual = $doctor->permissions()->pluck('slug')->sort()->values()->all();
        $expected = Permission::defaultSlugsForRole('Doctor');
        sort($expected);
        $this->assertSame($expected, $actual);
    }

    public function test_admin_can_customise_a_staff_members_permissions_in_manage_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = $this->makeUser('Admin');
        $staff = $this->makeUser('Staff', 'Cashier');
        Permission::assignDefaultsTo($staff); // starts as a standard Staff

        $cashierIds = Permission::whereIn('slug', Permission::CASHIER_SLUGS)->pluck('id')->all();

        $this->actingAs($admin)
            ->put(route('admin.permissions.update', $staff->id), ['permissions' => $cashierIds])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            Permission::CASHIER_SLUGS,
            $staff->fresh()->permissions()->pluck('slug')->all()
        );

        $page = $this->actingAs($admin)->get(route('admin.permissions.index'));
        $page->assertOk();
        $this->assertPageHas($page, 'Quick preset');
        $this->assertPageHas($page, 'Cashier (Billing & Payments only)');
        $this->assertPageHas($page, 'Cashier'); // the Position label is shown for Staff
    }

    public function test_reseeding_never_restores_defaults_on_a_customised_staff_account(): void
    {
        $this->seed(PermissionSeeder::class);

        $cashier  = $this->cashier();                 // customised: billing only
        $unconfigured = $this->makeUser('Staff');     // legacy: no permission rows at all

        $this->seed(PermissionSeeder::class);         // the documented "re-run after adding a permission"

        $this->assertEqualsCanonicalizing(
            Permission::CASHIER_SLUGS,
            $cashier->fresh()->permissions()->pluck('slug')->all(),
            'A customised Cashier must keep exactly its permissions after the seeder runs again.'
        );

        $this->assertTrue($unconfigured->fresh()->hasPermission('view_appointments'), 'Never-configured Staff still get the defaults.');
        $this->assertTrue($unconfigured->fresh()->hasPermission('view_billing'));
    }

    // ------------------------------------------------------------------
    // The Position label never decides access — permissions do
    // ------------------------------------------------------------------

    public function test_position_cashier_without_permissions_grants_nothing(): void
    {
        $fakeCashier = $this->makeUser('Staff', 'Cashier'); // labelled Cashier, zero permissions

        $this->actingAs($fakeCashier)->get(route('staff.billing.index'))->assertForbidden();
        $this->actingAs($fakeCashier)->get(route('staff.appointments.index'))->assertForbidden();

        // The dashboard itself is still reachable, and simply has nothing sensitive on it.
        $dash = $this->actingAs($fakeCashier)->get(route('staff.dashboard'));
        $dash->assertOk();
        $this->assertPageLacks($dash, "Today's Sales");
        $this->assertPageLacks($dash, 'Total Appointments');
        $this->assertPageHas($dash, 'Your Workspace');
    }

    public function test_billing_permissions_work_whatever_the_position_says(): void
    {
        $receptionist = $this->makeUser('Staff', 'Receptionist');
        $this->grant($receptionist, Permission::CASHIER_SLUGS);

        $this->actingAs($receptionist)->get(route('staff.billing.index'))->assertOk();
        $dash = $this->actingAs($receptionist)->get(route('staff.dashboard'));
        $this->assertPageHas($dash, "Today's Sales");
    }

    // ------------------------------------------------------------------
    // TEST 1 — Cashier: dashboard, billing access, blocked modules
    // ------------------------------------------------------------------

    public function test_cashier_dashboard_shows_billing_content_only(): void
    {
        $cashier = $this->cashier();
        $this->makeAppointment($this->makeUser('Patient'), $this->makeUser('Doctor'), 'Pending');

        $res = $this->actingAs($cashier)->get(route('staff.dashboard'));
        $res->assertOk();

        foreach ([
            "Today's Sales", 'Transactions Today', 'Unpaid Invoices', "Today's Discounts", 'Cash Revenue',
            "Today's Transactions", 'Needs Attention',
            '<span>Billing & Payments</span>',
            "No payment-related items require your attention.",
        ] as $expected) {
            $this->assertPageHas($res, $expected);
        }

        // No appointment content — not even for appointments that exist.
        // Quick Actions are removed for a billing-only Cashier (and billing is never a quick action).
        foreach (['Quick Actions', 'actionsWrapper', 'Collect Payment', 'Payment History', 'Billing Reports'] as $unexpected) {
            $this->assertPageLacks($res, $unexpected);
        }

        foreach (['Total Appointments', "Today's Appointments", 'View Appointments', 'Pending Appointment', 'View Patients'] as $unexpected) {
            $this->assertPageLacks($res, $unexpected);
        }

        // Nothing links to modules the Cashier has no permission for.
        foreach ([
            route('staff.appointments.index'), route('staff.patients.index'), route('staff.medicines.index'),
            route('staff.laboratory-requests.index'), route('staff.medical-certificates.index'),
        ] as $url) {
            $res->assertDontSee($url, false);
        }
    }

    public function test_cashier_can_use_billing_but_is_blocked_everywhere_else_by_url(): void
    {
        $cashier = $this->cashier();
        $invoice = $this->invoice(1000);
        $appt    = $invoice->appointment;

        // Allowed
        $this->actingAs($cashier)->get(route('staff.billing.index'))->assertOk();
        $this->actingAs($cashier)->get(route('staff.billing.show', $invoice->id))->assertOk();

        $this->actingAs($cashier)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 400, 'payment_method' => 'Cash'])
            ->assertSessionHasNoErrors();
        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->actingAs($cashier)
            ->get(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]))
            ->assertOk();

        // Blocked (permission middleware, even though the URLs are typed by hand)
        $this->actingAs($cashier)->get(route('staff.appointments.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('staff.appointments.show', $appt->id))->assertForbidden();
        $this->actingAs($cashier)->get(route('staff.pending-appointments'))->assertForbidden();
        $this->actingAs($cashier)->post(route('staff.appointments.approve', $appt->id))->assertForbidden();
        $this->actingAs($cashier)->get(route('staff.patients.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('staff.medicines.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('staff.laboratory-requests.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('staff.medical-certificates.index'))->assertForbidden();
    }

    public function test_cashier_sidebar_shows_billing_and_hides_other_modules(): void
    {
        $res = $this->actingAs($this->cashier())->get(route('staff.billing.index'));
        $res->assertOk();

        $this->assertPageHas($res, '<span>Billing & Payments</span>');
        $this->assertPageHas($res, '<span>Dashboard</span>');

        foreach (['<span>Appointments</span>', '<span>Patients</span>', '<span>Medicine</span>',
                  '<span>Laboratory Results</span>', '<span>Medical Certificates</span>'] as $hidden) {
            $this->assertPageLacks($res, $hidden);
        }
    }

    public function test_cashier_does_not_see_appointment_notifications(): void
    {
        $patient     = $this->makeUser('Patient');
        $appointment = $this->makeAppointment($patient, $this->makeUser('Doctor'), 'Pending');
        // Real appointment activity that must not leak (raw inserts skip the
        // model hook that records status events, so record one explicitly).
        \App\Models\AppointmentStatusEvent::create([
            'appointment_id' => $appointment->id,
            'status'         => 'Pending',
            'occurred_at'    => now(),
        ]);
        $this->assertSame(1, DB::table('appointment_status_events')->count());

        $cashier = $this->cashier();
        $service = app(\App\Services\StaffNotificationService::class);

        $this->assertCount(0, $service->build($cashier), 'A Cashier gets no appointment notifications.');
        $this->assertSame(0, $service->unreadCount($cashier));

        // Nor can a real notification key be marked as read through the endpoint.
        $event = \App\Models\AppointmentStatusEvent::first();
        $key   = "staff_appointment_{$event->appointment_id}_{$event->status}_{$event->id}";
        $this->actingAs($cashier)->postJson(route('staff.notifications.read'), ['key' => $key])->assertStatus(422);

        // A Staff member who may view appointments still gets them.
        $staff = $this->makeUser('Staff');
        $this->grant($staff, ['view_appointments']);
        $this->assertGreaterThan(0, $service->build($staff)->count());
    }

    // ------------------------------------------------------------------
    // TEST 2 — normal Staff with appointment permissions
    // ------------------------------------------------------------------

    public function test_staff_with_appointment_permissions_keeps_the_appointment_dashboard(): void
    {
        $staff = $this->makeUser('Staff', 'Receptionist');
        $this->grant($staff, ['view_appointments', 'view_patients']);
        $this->makeAppointment($this->makeUser('Patient'), $this->makeUser('Doctor'), 'Pending');

        $res = $this->actingAs($staff)->get(route('staff.dashboard'));
        $res->assertOk();

        foreach (['Total Appointments', 'Pending', 'Approved', 'Completed', 'Cancelled', "Today's Appointments",
                  '1 Pending Appointment', 'View Appointments', 'View Patients'] as $expected) {
            $this->assertPageHas($res, $expected);
        }

        // ...and none of the billing/cashier content.
        foreach (["Today's Sales", "Today's Transactions", 'Unpaid Invoices', 'Collect Payment', '<span>Billing & Payments</span>'] as $unexpected) {
            $this->assertPageLacks($res, $unexpected);
        }

        $this->assertSame(1, $res->viewData('totalAppointments'));
        $this->assertSame(1, $res->viewData('pendingAppointments'));
    }

    // ------------------------------------------------------------------
    // TEST 3 — appointment AND billing permissions together
    // ------------------------------------------------------------------

    public function test_staff_with_appointment_and_billing_permissions_sees_both_without_errors(): void
    {
        $staff = $this->makeUser('Staff');
        $this->grant($staff, array_merge(['view_appointments', 'view_patients'], Permission::CASHIER_SLUGS));
        $this->makeAppointment($this->makeUser('Patient'), $this->makeUser('Doctor'), 'Pending');
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 300, 'Cash');

        $res = $this->actingAs($staff)->get(route('staff.dashboard'));
        $res->assertOk();

        foreach (['Total Appointments', "Today's Appointments", 'View Appointments',
                  "Today's Sales", "Today's Transactions", 'Unpaid Invoices', 'Quick Actions',
                  '1 Pending Appointment', '1 Partially Paid Invoice'] as $expected) {
            $this->assertPageHas($res, $expected);
        }
    }

    // ------------------------------------------------------------------
    // TEST 4 — without record_payment
    // ------------------------------------------------------------------

    public function test_without_record_payment_staff_cannot_record_payment_or_discount(): void
    {
        $staff   = $this->cashier(['view_billing', 'view_payment_history', 'view_billing_reports']);
        $invoice = $this->invoice(1000);
        $item    = $invoice->items()->first();

        // Dashboard: no Collect Payment action; other billing actions remain.
        $dash = $this->actingAs($staff)->get(route('staff.dashboard'));
        $this->assertPageLacks($dash, 'Collect Payment');

        // The invoice page explains why there is no payment form.
        $show = $this->actingAs($staff)->get(route('staff.billing.show', $invoice->id));
        $show->assertOk();
        $this->assertPageHas($show, 'You do not have permission to record payments');

        // Direct URL access is denied and nothing was written.
        $this->actingAs($staff)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 100, 'payment_method' => 'Cash'])
            ->assertForbidden();
        $this->actingAs($staff)
            ->post(route('staff.billing.discounts.update', $invoice->id), ['items' => [$item->id => ['discount_type' => 'senior_citizen']]])
            ->assertForbidden();

        $this->assertSame(0, Payment::count());
        $this->assertNull($item->fresh()->discount_type);
    }

    // ------------------------------------------------------------------
    // TEST 5 — without view_billing
    // ------------------------------------------------------------------

    public function test_without_view_billing_the_billing_menu_and_routes_are_closed(): void
    {
        $staff   = $this->cashier(['record_payment', 'view_payment_history', 'view_billing_reports']);
        $invoice = $this->invoice(1000);

        $this->actingAs($staff)->get(route('staff.billing.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('staff.billing.show', $invoice->id))->assertForbidden();

        $dash = $this->actingAs($staff)->get(route('staff.dashboard'));
        $dash->assertOk();
        $this->assertPageLacks($dash, '<span>Billing & Payments</span>'); // sidebar + quick action
        $this->assertPageLacks($dash, 'Unpaid Invoices');
        $this->assertPageLacks($dash, "Today's Sales");
        $dash->assertDontSee(route('staff.billing.index'), false);
    }

    // ------------------------------------------------------------------
    // TEST 6 — without view_payment_history
    // ------------------------------------------------------------------

    public function test_without_view_payment_history_history_receipts_and_transactions_are_closed(): void
    {
        $staff   = $this->cashier(['view_billing', 'record_payment', 'view_billing_reports']);
        $invoice = $this->invoice(1000);
        $existing = $this->pay($invoice, 100, 'Cash');

        $dash = $this->actingAs($staff)->get(route('staff.dashboard'));
        $dash->assertOk();
        $this->assertPageLacks($dash, 'Payment History');
        $this->assertPageLacks($dash, "Today's Transactions");
        $dash->assertDontSee('/receipt/', false);
        $this->assertPageHas($dash, "Today's Sales"); // report cards still need only view_billing_reports

        // Direct URL access to a receipt is denied.
        $this->actingAs($staff)
            ->get(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $existing->id]))
            ->assertForbidden();

        // The invoice page hides the history instead of showing it.
        $show = $this->actingAs($staff)->get(route('staff.billing.show', $invoice->id));
        $this->assertPageHas($show, 'You do not have permission to view payment history.');

        // Recording a payment succeeds and lands back on the invoice — not on a 403 receipt.
        $res = $this->actingAs($staff)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 200, 'payment_method' => 'Cash']);
        $res->assertRedirect(route('staff.billing.show', $invoice->id));
        $this->assertSame(2, Payment::where('invoice_id', $invoice->id)->count());
    }

    public function test_with_view_payment_history_a_recorded_payment_still_opens_the_receipt(): void
    {
        $staff   = $this->cashier();
        $invoice = $this->invoice(1000);

        $res = $this->actingAs($staff)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 1000, 'payment_method' => 'Cash']);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $res->assertRedirect(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]));
    }

    // ------------------------------------------------------------------
    // TEST 7 — without view_billing_reports
    // ------------------------------------------------------------------

    public function test_without_view_billing_reports_report_figures_are_hidden_everywhere(): void
    {
        $staff   = $this->cashier(['view_billing', 'record_payment', 'view_payment_history']);
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 300, 'Cash');

        $dash = $this->actingAs($staff)->get(route('staff.dashboard'));
        $dash->assertOk();
        $this->assertPageLacks($dash, 'Billing Reports');
        foreach (["Today's Sales", 'Transactions Today', "Today's Discounts", 'Cash Revenue'] as $moneyCard) {
            $this->assertPageLacks($dash, $moneyCard);
        }
        $this->assertPageHas($dash, 'Unpaid Invoices');       // needs only view_billing
        $this->assertPageHas($dash, "Today's Transactions");  // needs view_payment_history

        // The report figures are not even computed for this account.
        $this->assertEquals(0, $dash->viewData('billing')['todaySales']);

        // "Direct URL": the reports live on the billing page — with any range
        // the totals stay hidden for an account without the permission.
        $page = $this->actingAs($staff)->get(route('staff.billing.index', ['range' => 'month']));
        $page->assertOk();
        $this->assertNull($page->viewData('reportTotals'));
        $this->assertPageLacks($page, 'Gross Sales');
    }

    // ------------------------------------------------------------------
    // Dashboard figures come from the real billing records
    // ------------------------------------------------------------------

    public function test_cashier_dashboard_figures_are_calculated_from_the_database(): void
    {
        $cashier = $this->cashier();

        // A: ₱1000, 20% senior discount on the line (₱200) -> total ₱800.
        //    Paid today in two payments: ₱500 Cash + ₱300 GCash.
        $a = $this->invoice(1000);
        app(BillingService::class)->applyItemDiscounts($a, [
            $a->items()->first()->id => ['discount_type' => 'senior_citizen'],
        ]);
        $this->pay($a, 500, 'Cash');
        $this->pay($a, 300, 'GCash');

        // B: ₱1000 with the same ₱200 discount; FIRST payment was YESTERDAY
        //    (₱100 Cash), then ₱50 Cash today -> its discount belongs to
        //    yesterday, never to today.
        $b = $this->invoice(1000);
        app(BillingService::class)->applyItemDiscounts($b, [
            $b->items()->first()->id => ['discount_type' => 'senior_citizen'],
        ]);
        $yesterdayPayment = $this->pay($b, 100, 'Cash');
        Payment::whereKey($yesterdayPayment->id)->update(['paid_at' => now()->subDay()]);
        $this->pay($b, 50, 'Cash');

        // C: untouched, Unpaid.   (A is Paid, B is Partially Paid.)
        $c = $this->invoice(400);

        $res = $this->actingAs($cashier)->get(route('staff.dashboard'));
        $res->assertOk();
        $billing = $res->viewData('billing');

        $this->assertEquals(850, $billing['todaySales'], "Today's payments only: 500 + 300 + 50 (yesterday's 100 excluded).");
        $this->assertSame(3, $billing['transactionsToday']);
        $this->assertEquals(550, $billing['cashRevenue'], 'Cash today only: 500 + 50.');
        $this->assertEquals(200, $billing['todayDiscounts'], "Only A's line-item discount; B's was applied yesterday.");
        $this->assertSame(1, $billing['unpaidInvoices'], 'Only C is still Unpaid.');
        $this->assertSame(1, $billing['partiallyPaidInvoices'], 'B is Partially Paid.');

        // Today's Transactions: the 3 payments from today, newest first.
        $this->assertCount(3, $billing['transactions']);
        $this->assertSame(3, $billing['totalTodayTransactions']);
        $this->assertFalse($billing['transactions']->contains('id', $yesterdayPayment->id));

        // Rendered values match.
        foreach (['₱850.00', '₱550.00', '₱200.00', $a->invoice_no, $b->invoice_no, 'Cash', 'GCash',
                  '1 Unpaid Invoice', '1 Partially Paid Invoice'] as $expected) {
            $this->assertPageHas($res, $expected);
        }
        $this->assertPageLacks($res, $c->invoice_no); // an unpaid invoice is not a transaction

        // Each transaction row links to the existing receipt page.
        $first = $billing['transactions']->first();
        $res->assertSee(route('staff.billing.receipt', ['invoice' => $first->invoice_id, 'payment' => $first->id]), false);
    }

    public function test_pay_after_service_rule_is_respected_on_the_dashboard(): void
    {
        $cashier = $this->cashier();

        // An invoice whose consultation is still In Progress is not collectible,
        // so it must not be counted as an unpaid invoice for the cashier.
        $invoice = $this->invoice(1000);
        $invoice->appointment()->update(['status' => 'In Progress']);

        $res = $this->actingAs($cashier)->get(route('staff.dashboard'));
        $this->assertSame(0, $res->viewData('billing')['unpaidInvoices']);
        $this->assertPageHas($res, "You're all caught up!");
        $this->assertPageHas($res, 'No payment-related items require your attention.');
    }

    public function test_dashboard_empty_states_render_cleanly(): void
    {
        $res = $this->actingAs($this->cashier())->get(route('staff.dashboard'));
        $res->assertOk();

        $this->assertPageHas($res, 'No payments recorded today.');
        $this->assertPageHas($res, '₱0.00');
        $this->assertSame(0, $res->viewData('billing')['transactionsToday']);
    }

    // ------------------------------------------------------------------
    // Sidebar — no empty RECORDS section for a Cashier
    // ------------------------------------------------------------------

    private function sidebarLabels($response): array
    {
        preg_match_all('/<span class="nav-section-label">([^<]+)<\/span>/', $response->getContent(), $m);

        return $m[1];
    }

    public function test_cashier_sidebar_is_main_billing_account_only(): void
    {
        $res = $this->actingAs($this->cashier())->get(route('staff.dashboard'));
        $res->assertOk();

        $this->assertSame(['Main', 'Billing', 'Account'], $this->sidebarLabels($res));
        $this->assertPageHas($res, '<span>Activity Logs</span>');
        $this->assertPageHas($res, '<span>Account Settings</span>');
        $this->assertPageHas($res, '<span>Billing & Payments</span>');
    }

    public function test_records_section_still_shows_for_staff_with_a_records_permission(): void
    {
        $staff = $this->makeUser('Staff');
        $this->grant($staff, ['view_patients', 'view_appointments']);

        $labels = $this->sidebarLabels($this->actingAs($staff)->get(route('staff.dashboard')));

        $this->assertSame(['Main', 'Appointments', 'Records', 'Account'], $labels);
    }

    // ------------------------------------------------------------------
    // Dashboard cards are display-only; no Quick Actions container
    // ------------------------------------------------------------------

    public function test_billing_cards_are_not_clickable(): void
    {
        $res = $this->actingAs($this->cashier())->get(route('staff.dashboard'));
        $html = $res->getContent();

        $this->assertSame(5, substr_count($html, 'appt-card--static'), 'All five metric cards are static.');

        // Not links, no click handlers, nothing inside the card row is an <a> or <button>.
        $row = substr($html, strpos($html, 'id="realBillingStats"'));
        $row = substr($row, 0, strpos($row, 'dashboard-grid'));

        $this->assertStringNotContainsString('<a ', $row);
        $this->assertStringNotContainsString('<button', $row);
        $this->assertStringNotContainsString('onclick', $row);
        $this->assertSame(0, preg_match('/<a [^>]*class="appt-card/', $html));
    }

    public function test_quick_actions_remain_for_staff_with_other_modules_but_never_include_billing(): void
    {
        $staff = $this->makeUser('Staff');
        $this->grant($staff, array_merge(['view_appointments', 'view_patients'], Permission::CASHIER_SLUGS));

        $res = $this->actingAs($staff)->get(route('staff.dashboard'));

        $this->assertPageHas($res, 'Quick Actions');
        $this->assertPageHas($res, 'View Appointments');
        $this->assertPageHas($res, 'View Patients');
        foreach (['Collect Payment', 'Payment History', 'Billing Reports', 'Create Invoice', 'Issue Receipt'] as $gone) {
            $this->assertPageLacks($res, $gone);
        }
    }

    // ------------------------------------------------------------------
    // Billing & Payments — Action button follows the invoice status
    // ------------------------------------------------------------------

    /** The HTML of the list row for $invoice. */
    private function listRow($response, Invoice $invoice): string
    {
        foreach (explode('<tr', $response->getContent()) as $chunk) {
            if (str_contains($chunk, $invoice->invoice_no)) {
                return $chunk;
            }
        }

        $this->fail("Invoice {$invoice->invoice_no} is not in the list.");
    }

    public function test_billing_list_action_button_depends_on_invoice_status(): void
    {
        $cashier = $this->cashier();

        $unpaid  = $this->invoice(500);
        $partial = $this->invoice(1000);
        $this->pay($partial, 600, 'Cash');
        $paid    = $this->invoice(300);
        $this->pay($paid, 300, 'Cash');
        $cancelled = $this->invoice(200);
        Invoice::whereKey($cancelled->id)->update(['status' => 'Cancelled']);

        $res = $this->actingAs($cashier)->get(route('staff.billing.index'));
        $res->assertOk();

        foreach ([$unpaid, $partial] as $invoice) {
            $row = $this->listRow($res, $invoice);
            $this->assertStringContainsString('Collect Payment', $row);
            $this->assertStringContainsString(route('staff.billing.show', $invoice->id) . '#paymentForm', $row);
        }

        foreach ([$paid, $cancelled] as $invoice) {
            $row = $this->listRow($res, $invoice);
            $this->assertStringNotContainsString('Collect Payment', $row);
            $this->assertMatchesRegularExpression('/class="btn-view">View<\/a>/', $row);
        }
    }

    public function test_staff_without_record_payment_only_gets_view_even_on_unpaid_invoices(): void
    {
        $viewer  = $this->cashier(['view_billing']);
        $unpaid  = $this->invoice(500);

        $row = $this->listRow($this->actingAs($viewer)->get(route('staff.billing.index')), $unpaid);

        $this->assertStringNotContainsString('Collect Payment', $row);
        $this->assertStringContainsString('>View</a>', $row);
    }

    // ------------------------------------------------------------------
    // Payment form content + cash change helper
    // ------------------------------------------------------------------

    public function test_payment_form_shows_the_invoice_figures_and_a_display_only_cash_change_helper(): void
    {
        $cashier = $this->cashier();
        $invoice = $this->invoice(1000);
        $this->pay($invoice, 400, 'Cash');
        $invoice = $invoice->fresh();

        $res = $this->actingAs($cashier)->get(route('staff.billing.show', $invoice->id));
        $res->assertOk();
        $html = $res->getContent();

        $form = substr($html, strpos($html, 'id="paymentForm"'));
        $form = substr($form, 0, strpos($form, '</form>'));
        $form = html_entity_decode($form, ENT_QUOTES | ENT_HTML5);

        foreach ([$invoice->invoice_no, $invoice->patientName(), '₱1,000.00', '₱400.00', '₱600.00', 'Cash', 'GCash', 'Amount to Pay'] as $needle) {
            $this->assertStringContainsString($needle, $form);
        }

        // Tendered/change exist for cash but are NOT submitted (no name attribute).
        $this->assertStringContainsString('id="amountTendered"', $form);
        $this->assertStringContainsString('id="payChangeValue"', $form);
        preg_match('/<input[^>]*id="amountTendered"[^>]*>/', $form, $tender);
        $this->assertStringNotContainsString('name=', $tender[0]);
    }

    // ------------------------------------------------------------------
    // SCENARIO E / F — full and partial payment, guards on a settled invoice
    // ------------------------------------------------------------------

    public function test_full_payment_marks_the_invoice_paid_and_keeps_the_receipt_flow(): void
    {
        $cashier = $this->cashier();
        $invoice = $this->invoice(550);

        $res = $this->actingAs($cashier)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 550, 'payment_method' => 'Cash']);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $res->assertRedirect(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]));

        $invoice = $invoice->fresh();
        $this->assertSame('Paid', $invoice->status);
        $this->assertEquals(550, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance);

        // Existing receipt page (with its print button) still opens.
        $receipt = $this->actingAs($cashier)->get(route('staff.billing.receipt', ['invoice' => $invoice->id, 'payment' => $payment->id]));
        $receipt->assertOk();
        $this->assertPageHas($receipt, 'print');

        // Settled invoice: View in the list, and no payment form on its page.
        $row = $this->listRow($this->actingAs($cashier)->get(route('staff.billing.index')), $invoice);
        $this->assertStringNotContainsString('Collect Payment', $row);

        $show = $this->actingAs($cashier)->get(route('staff.billing.show', $invoice->id));
        $this->assertStringNotContainsString('id="paymentForm"', $show->getContent());
        $this->assertPageHas($show, 'This invoice is fully paid.');
    }

    public function test_partial_payment_keeps_collect_payment_until_the_balance_is_zero(): void
    {
        $cashier = $this->cashier();
        $invoice = $this->invoice(1000);

        $this->actingAs($cashier)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 600, 'payment_method' => 'Cash'])
            ->assertSessionHasNoErrors();

        $invoice = $invoice->fresh();
        $this->assertSame('Partially Paid', $invoice->status);
        $this->assertEquals(600, $invoice->amount_paid);
        $this->assertEquals(400, $invoice->balance);

        $list = $this->actingAs($cashier)->get(route('staff.billing.index'));
        $this->assertStringContainsString('Collect Payment', $this->listRow($list, $invoice));

        $show = $this->actingAs($cashier)->get(route('staff.billing.show', $invoice->id));
        $this->assertStringContainsString('id="paymentForm"', $show->getContent());
        $this->assertPageHas($show, '₱400.00');

        // Pay the remaining ₱400.
        $this->actingAs($cashier)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 400, 'payment_method' => 'Cash'])
            ->assertSessionHasNoErrors();

        $invoice = $invoice->fresh();
        $this->assertSame('Paid', $invoice->status);
        $this->assertEquals(1000, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->balance);
        $this->assertSame(2, Payment::where('invoice_id', $invoice->id)->count());

        $list = $this->actingAs($cashier)->get(route('staff.billing.index'));
        $row  = $this->listRow($list, $invoice);
        $this->assertStringNotContainsString('Collect Payment', $row);
        $this->assertStringContainsString('>View</a>', $row);
    }

    public function test_no_further_payment_can_be_recorded_on_a_zero_balance_or_overpaid_invoice(): void
    {
        $cashier = $this->cashier();
        $paid    = $this->invoice(300);
        $this->pay($paid, 300, 'Cash');

        // Already paid: rejected server-side even though the form is hidden.
        $this->actingAs($cashier)
            ->post(route('staff.billing.payments.store', $paid->id), ['amount' => 50, 'payment_method' => 'Cash'])
            ->assertSessionHasErrors('amount');
        $this->assertSame(1, Payment::where('invoice_id', $paid->id)->count());

        // More than the remaining balance: rejected too.
        $partial = $this->invoice(1000);
        $this->pay($partial, 600, 'Cash');
        $this->actingAs($cashier)
            ->post(route('staff.billing.payments.store', $partial->id), ['amount' => 500, 'payment_method' => 'Cash'])
            ->assertSessionHasErrors('amount');
        $this->assertEquals(600, $partial->fresh()->amount_paid);
    }

    public function test_staff_without_billing_permissions_cannot_reach_billing_by_url(): void
    {
        $staff = $this->makeUser('Staff', 'Receptionist');
        $this->grant($staff, ['view_appointments']);
        $invoice = $this->invoice(500);

        $this->actingAs($staff)->get(route('staff.billing.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('staff.billing.show', $invoice->id))->assertForbidden();
        $this->actingAs($staff)
            ->post(route('staff.billing.payments.store', $invoice->id), ['amount' => 100, 'payment_method' => 'Cash'])
            ->assertForbidden();
        $this->assertSame(0, Payment::count());
    }
}
