<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\BillingReportService;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Billing summary cards vs. revenue report vs. payment records.
 *
 * One controlled data set (see seedScenario()) is used to prove that
 *  - Cash + Non-Cash collections = payment rows (by PAYMENT date),
 *  - Gross / Discount / Net come from invoices CREATED in the period,
 *  - the summary cards and the report tables agree for the same filter,
 *  - Outstanding is the current balance of open, valid invoices,
 *  - joins (invoice x items x payments) never inflate a total.
 *
 * "Now" is frozen at Thu 15 Oct 2026 14:00 (Asia/Manila) so the Today /
 * Week / Month ranges are deterministic.
 */
class BillingSummaryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @var array<string, Invoice> */
    private array $inv = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-15 14:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function makeUser(string $role): User
    {
        $this->seq++;

        $id = DB::table('users')->insertGetId([
            'first_name'      => $role . $this->seq,
            'last_name'       => 'Recon',
            'birthdate'       => '1990-01-01',
            'gender'          => 'Male',
            'civil_status'    => 'Single',
            'address'         => 'Somewhere',
            'contact_number'  => '0917300' . str_pad($this->seq, 4, '0', STR_PAD_LEFT),
            'username'        => strtolower($role) . 'recon' . $this->seq,
            'email'           => strtolower($role) . 'recon' . $this->seq . '@example.test',
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

    private function cashier(): User
    {
        $u = $this->makeUser('Staff');
        $this->grant($u, ['view_billing', 'view_billing_reports', 'view_payment_history', 'record_payment']);

        return $u;
    }

    private function appointment(string $status): Appointment
    {
        $this->seq++;
        $id = DB::table('appointments')->insertGetId([
            'patient_id'       => $this->makeUser('Patient')->id,
            'doctor_id'        => $this->makeUser('Doctor')->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '10:' . str_pad($this->seq % 60, 2, '0', STR_PAD_LEFT) . ':00',
            'status'           => $status,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return Appointment::findOrFail($id);
    }

    /** Consultation invoice (one line, fee $price) created at $createdAt. */
    private function consultationInvoice(float $price, string $createdAt, string $apptStatus = 'Completed'): Invoice
    {
        $service = Service::where('name', 'Consultation')->firstOrFail();
        $service->update(['price' => $price]);

        // The service only creates an invoice for a Completed consultation,
        // so create it that way, then move the appointment to $apptStatus.
        $appt    = $this->appointment('Completed');
        $invoice = app(BillingService::class)->syncInvoiceForAppointment(
            $appt, [['service_id' => $service->id, 'quantity' => 1]], User::find($appt->doctor_id)
        );

        DB::table('invoices')->where('id', $invoice->id)->update(['created_at' => $createdAt]);
        DB::table('appointments')->where('id', $appt->id)->update(['status' => $apptStatus]);

        return $invoice->fresh();
    }

    private function addLine(Invoice $invoice, string $type, string $description, int $qty, float $unit): void
    {
        DB::table('invoice_items')->insert([
            'invoice_id'  => $invoice->id,
            'description' => $description,
            'item_type'   => $type,
            'quantity'    => $qty,
            'unit_price'  => $unit,
            'amount'      => $qty * $unit,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** Record a payment through the real service, then move it to $paidAt. */
    private function pay(Invoice $invoice, float $amount, string $method, string $paidAt, ?string $ref = null): Payment
    {
        $payment = app(BillingService::class)->recordPayment($invoice->fresh(), [
            'amount' => $amount, 'payment_method' => $method,
            'reference_number' => $method === 'GCash' ? ($ref ?? 'REF' . (++$this->seq) . 'X') : null,
        ], $this->makeUser('Staff'));

        DB::table('payments')->where('id', $payment->id)->update(['paid_at' => $paidAt]);

        return $payment;
    }

    /**
     * The controlled data set.  (Frozen "today" = 2026-10-15.)
     *
     *  A  created 14 Oct  consultation 1000 (20 % senior = 200 off) + medicine 3x100 + certificate 200
     *                     net 1300; paid 500 Cash 14 Oct, 300 GCash 15 Oct 09:00, 100 Cash 15 Oct 23:59:59 -> owes 400
     *  B  created 15 Oct  consultation 1000, unpaid                                              -> owes 1000
     *  C  created 15 Oct  consultation 1000, paid in full today 600 Cash + 400 GCash             -> owes 0
     *  D  created 15 Oct  consultation 1000, VOIDED
     *  E  created 15 Oct  consultation 1000, consultation still In Progress (not collectible)
     *  F  created 15 Oct  walk-in, no appointment, legacy invoice-level discount 50 on a 500 line -> owes 450
     *  H  created  5 Oct  consultation 1000, paid 200 Cash on 5 Oct                              -> owes 800
     *  G  created 15 Oct  consultation 1000, 100 % doctor waiver  -> total 0, Paid, no payment rows
     */
    private function seedScenario(): void
    {
        // A
        $a = $this->consultationInvoice(1000, '2026-10-14 09:00:00');
        $this->addLine($a, 'medicine', 'Paracetamol', 3, 100);
        $this->addLine($a, 'medical_certificate', 'Medical Certificate', 1, 200);
        DB::table('invoice_items')->where('invoice_id', $a->id)->where('item_type', 'service')
            ->update(['discount_type' => 'senior_citizen', 'discount_rate' => 20]);
        $a = app(BillingService::class)->recalculateInvoiceTotals($a->fresh());
        $this->pay($a, 500, 'Cash', '2026-10-14 10:00:00');
        $this->pay($a, 300, 'GCash', '2026-10-15 09:00:00');
        $this->pay($a, 100, 'Cash', '2026-10-15 23:59:59');
        $this->inv['A'] = $a->fresh();

        // B
        $this->inv['B'] = $this->consultationInvoice(1000, '2026-10-15 08:00:00');

        // C
        $c = $this->consultationInvoice(1000, '2026-10-15 08:30:00');
        $this->pay($c, 600, 'Cash', '2026-10-15 10:00:00');
        $this->pay($c, 400, 'GCash', '2026-10-15 11:00:00');
        $this->inv['C'] = $c->fresh();

        // D (voided)
        $d = $this->consultationInvoice(1000, '2026-10-15 09:00:00');
        app(BillingService::class)->voidInvoice($d->fresh(), 'Entered by mistake', $this->makeUser('Staff'));
        $this->inv['D'] = $d->fresh();

        // E (not collectible yet)
        $this->inv['E'] = $this->consultationInvoice(1000, '2026-10-15 09:30:00', 'In Progress');

        // F (walk-in, no appointment, legacy invoice-level discount)
        $walkin = Patient::create(['first_name' => 'Walk', 'last_name' => 'In']);
        $fId = DB::table('invoices')->insertGetId([
            'invoice_no' => 'INV-20261015-09001', 'appointment_id' => null,
            'walkin_patient_id' => $walkin->id, 'patient_name' => 'Walk In',
            'subtotal' => 500, 'discount' => 50, 'total_amount' => 450,
            'amount_paid' => 0, 'balance' => 450, 'status' => 'Unpaid',
            'created_at' => '2026-10-15 10:30:00', 'updated_at' => '2026-10-15 10:30:00',
        ]);
        DB::table('invoice_items')->insert([
            'invoice_id' => $fId, 'description' => 'Medical Certificate', 'item_type' => 'medical_certificate',
            'quantity' => 1, 'unit_price' => 500, 'amount' => 500,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->inv['F'] = Invoice::findOrFail($fId);

        // H (earlier this month)
        $h = $this->consultationInvoice(1000, '2026-10-05 09:00:00');
        $this->pay($h, 200, 'Cash', '2026-10-05 09:30:00');
        $this->inv['H'] = $h->fresh();

        // G (100 % doctor waiver -> total 0)
        $g = $this->consultationInvoice(1000, '2026-10-15 12:00:00');
        DB::table('invoice_items')->where('invoice_id', $g->id)->update([
            'discount_type' => \App\Models\InvoiceItem::WAIVER_TYPE, 'discount_rate' => 100,
        ]);
        $this->inv['G'] = app(BillingService::class)->recalculateInvoiceTotals($g->fresh());
    }

    private function range(string $range, ?string $from = null, ?string $to = null): array
    {
        $params = array_filter(['range' => $range, 'from' => $from, 'to' => $to]);

        return app(BillingReportService::class)->resolveRange(new Request($params));
    }

    // ------------------------------------------------------------------
    // sanity of the scenario itself
    // ------------------------------------------------------------------

    public function test_scenario_invoices_have_the_expected_money_state(): void
    {
        $this->seedScenario();

        $this->assertEquals(1300, $this->inv['A']->total_amount);
        $this->assertEquals(900, $this->inv['A']->amount_paid);
        $this->assertEquals(400, $this->inv['A']->balance);
        $this->assertSame('Partially Paid', $this->inv['A']->status);
        $this->assertSame('Paid', $this->inv['C']->status);
        $this->assertSame('Cancelled', $this->inv['D']->status);
        $this->assertEquals(450, $this->inv['F']->total_amount);
        $this->assertEquals(0, $this->inv['G']->total_amount);
        $this->assertSame('Paid', $this->inv['G']->status);
    }

    // ------------------------------------------------------------------
    // Collections: by PAYMENT date, cash vs non-cash, no inflation
    // ------------------------------------------------------------------

    public function test_collections_today_come_from_payment_rows_by_payment_date(): void
    {
        $this->seedScenario();
        [$from, $to] = $this->range('today');
        $pay = app(BillingReportService::class)->paymentTotals($from, $to);

        // Today: A 300 GCash + A 100 Cash (23:59:59 - last second counts)
        //        C 600 Cash + C 400 GCash.  A's 500 (yesterday) is NOT today.
        $this->assertEquals(700, $pay['cash']);
        $this->assertEquals(700, $pay['gcash']);
        $this->assertEquals(1400, $pay['total']);
        $this->assertSame(4, $pay['transactions']);
    }

    public function test_cash_plus_non_cash_always_equals_total_collections_and_the_raw_payment_rows(): void
    {
        $this->seedScenario();

        foreach (['today', 'week', 'month'] as $r) {
            [$from, $to] = $this->range($r);
            $pay = app(BillingReportService::class)->paymentTotals($from, $to);

            $raw = (float) Payment::whereBetween('paid_at', [$from, $to])->sum('amount');
            $this->assertEquals($raw, $pay['total'], "range $r: total must equal the raw payment rows");
            $this->assertEquals($pay['total'], $pay['cash'] + $pay['gcash'], "range $r: cash + non-cash = total");
        }
    }

    public function test_week_month_and_custom_ranges(): void
    {
        $this->seedScenario();
        $svc = app(BillingReportService::class);

        // Week = Mon 12 Oct - Sun 18 Oct: today's 1400 + A's 500 on 14 Oct.
        [$f, $t] = $this->range('week');
        $this->assertEquals('2026-10-12', $f->toDateString());
        $this->assertEquals('2026-10-18', $t->toDateString());
        $this->assertEquals(1900, $svc->paymentTotals($f, $t)['total']);

        // Month adds H's 200 on 5 Oct.
        [$f, $t] = $this->range('month');
        $this->assertEquals(2100, $svc->paymentTotals($f, $t)['total']);

        // Custom, single day (end date inclusive): 14 Oct only = A's 500 Cash.
        [$f, $t] = $this->range('custom', '2026-10-14', '2026-10-14');
        $pay = $svc->paymentTotals($f, $t);
        $this->assertEquals(500, $pay['cash']);
        $this->assertEquals(0, $pay['gcash']);

        // Custom, swapped dates are corrected, not rejected.
        [$f, $t] = $this->range('custom', '2026-10-15', '2026-10-14');
        $this->assertEquals('2026-10-14', $f->toDateString());
        $this->assertEquals('2026-10-15', $t->toDateString());
        $this->assertEquals(1900, $svc->paymentTotals($f, $t)['total']);
    }

    public function test_garbage_dates_fall_back_to_today_and_empty_periods_are_zero(): void
    {
        $this->seedScenario();

        [$f, $t, $range] = $this->range('custom', 'not-a-date', 'also-bad');
        $this->assertSame('today', $range);
        $this->assertEquals('2026-10-15', $f->toDateString());

        // A period with no activity: every figure is a number zero, nothing breaks.
        [$f, $t] = $this->range('custom', '2026-01-01', '2026-01-31');
        $svc  = app(BillingReportService::class);
        $sum  = $svc->summary($f, $t);
        $rep  = $svc->build($f, $t);

        foreach (['grossSales', 'totalDiscount', 'netSales', 'cashPayments', 'nonCashPayments', 'totalCollected'] as $k) {
            $this->assertEquals(0, $sum[$k], $k);
        }
        $this->assertSame(0, $sum['paymentTransactions']);
        $this->assertEquals(0, $rep['totals']['net']);
        $this->assertSame(0, $rep['invoiceCount']);
        // Outstanding is "right now", so it is NOT zero in an empty past month.
        $this->assertEquals(2650, $sum['totalOutstanding']);
    }

    // ------------------------------------------------------------------
    // Sales: Gross / Discount / Net by INVOICE date; voided & not-yet-collectible excluded
    // ------------------------------------------------------------------

    public function test_sales_figures_use_invoice_date_and_skip_void_and_uncollectible_invoices(): void
    {
        $this->seedScenario();
        [$from, $to] = $this->range('today');
        $s = app(BillingReportService::class)->summary($from, $to);

        // Created today and counted: B 1000, C 1000, F 500 (50 off), G 1000 (waived).
        //   (A was created yesterday; D is voided; E is not collectible.)
        $this->assertEquals(3500, $s['grossSales']);
        $this->assertEquals(1050, $s['totalDiscount']);   // F 50 + G 1000 waiver
        $this->assertEquals(2450, $s['netSales']);
    }

    // ------------------------------------------------------------------
    // Outstanding: current balance of open, valid invoices
    // ------------------------------------------------------------------

    public function test_outstanding_is_the_current_balance_of_open_valid_invoices_only(): void
    {
        $this->seedScenario();
        [$from, $to] = $this->range('today');
        $s = app(BillingReportService::class)->summary($from, $to);

        // A 400 + B 1000 + F 450 + H 800.  C paid, D voided, E not collectible, G settled.
        $this->assertEquals(2650, $s['totalOutstanding']);

        // The date filter must NOT change it.
        [$f2, $t2] = $this->range('month');
        $this->assertEquals(2650, app(BillingReportService::class)->summary($f2, $t2)['totalOutstanding']);

        // And it equals "total - payments" worked out from the payment rows.
        $fromRows = 0.0;
        foreach (['A', 'B', 'C', 'F', 'H', 'G'] as $k) {
            $i = $this->inv[$k];
            $fromRows += max((float) $i->total_amount - (float) Payment::where('invoice_id', $i->id)->sum('amount'), 0);
        }
        $this->assertEquals($fromRows, $s['totalOutstanding']);
    }

    // ------------------------------------------------------------------
    // Cards vs. report tables: must agree for the same filter
    // ------------------------------------------------------------------

    public function test_summary_cards_and_report_tables_agree_for_every_range(): void
    {
        $this->seedScenario();
        $svc = app(BillingReportService::class);

        foreach ([['today'], ['week'], ['month'], ['custom', '2026-10-14', '2026-10-15']] as $args) {
            [$from, $to] = $this->range(...$args);
            $cards  = $svc->summary($from, $to);
            $report = $svc->build($from, $to);
            $label  = implode(' ', $args);

            $this->assertEquals($cards['grossSales'], $report['totals']['gross'], "$label: Gross Sales card = table total");
            $this->assertEquals($cards['totalDiscount'], $report['totals']['discount'], "$label: Total Discount card = table total");
            $this->assertEquals($cards['netSales'], $report['totals']['net'], "$label: Net Sales card = table total");
            $this->assertEquals($cards['cashPayments'], $report['payments']['cash'], "$label: Cash card = Payments Received table");
            $this->assertEquals($cards['nonCashPayments'], $report['payments']['gcash'], "$label: Non-Cash card = Payments Received table");
            $this->assertEquals($cards['paymentTransactions'], $report['payments']['transactions'], "$label: transactions");
            $this->assertEquals(
                $report['totals']['net'],
                round(array_sum(array_column($report['daily'], 'total')), 2),
                "$label: daily breakdown adds up to Net Sales"
            );
        }
    }

    public function test_paid_so_far_estimate_adds_up_to_the_real_money_on_those_invoices(): void
    {
        $this->seedScenario();
        $svc = app(BillingReportService::class);

        foreach ([['today'], ['month']] as $args) {
            [$from, $to] = $this->range(...$args);
            $report = $svc->build($from, $to);

            // The estimate spreads each invoice's REAL payments over its lines,
            // so the grand total must equal what those invoices actually paid.
            $ids = Invoice::collectible()->where('status', '!=', 'Cancelled')
                ->whereBetween('created_at', [$from, $to])->pluck('id');
            $realPaid = (float) Payment::whereIn('invoice_id', $ids)->sum('amount');

            $this->assertEquals($realPaid, $report['totals']['collected'], implode(' ', $args));
            $this->assertEquals(
                round($report['totals']['net'] - $report['totals']['collected'], 2),
                $report['totals']['uncollected']
            );
        }
    }

    public function test_one_payment_on_a_many_line_invoice_is_never_counted_per_line(): void
    {
        $this->seedScenario();
        // A has 3 lines and 3 payments. A join-based total would give 9 rows.
        [$from, $to] = $this->range('custom', '2026-10-14', '2026-10-14');
        $svc = app(BillingReportService::class);

        $this->assertEquals(500, $svc->paymentTotals($from, $to)['total']);
        $this->assertEquals(1500, $svc->summary($from, $to)['grossSales']);   // 1000 + 300 + 200, once
        $this->assertEquals(200, $svc->summary($from, $to)['totalDiscount']); // 200 once, not x3
        $this->assertEquals(1300, $svc->summary($from, $to)['netSales']);
    }

    // ------------------------------------------------------------------
    // Pages: Staff + Admin render the same numbers; dashboard uses the same payment rules
    // ------------------------------------------------------------------

    public function test_staff_and_admin_report_pages_show_identical_cards(): void
    {
        $this->seedScenario();
        $staff = $this->cashier();
        $admin = $this->makeUser('Admin');

        $s = $this->actingAs($staff)->get(route('staff.billing.report'));
        $s->assertOk();
        $a = $this->actingAs($admin)->get(route('admin.billing.report'));
        $a->assertOk();

        $this->assertEquals($s->viewData('reportTotals'), $a->viewData('reportTotals'));
        $this->assertEquals($s->viewData('report')['totals'], $a->viewData('report')['totals']);

        $cards = $s->viewData('reportTotals');
        $this->assertEquals(700, $cards['cashPayments']);
        $this->assertEquals(700, $cards['nonCashPayments']);
        $s->assertSee('₱1,400.00', false);   // total received, printed in the Payments Received table
    }

    public function test_staff_dashboard_collections_match_the_report_for_today(): void
    {
        $this->seedScenario();
        $staff = $this->cashier();

        $dash   = $this->actingAs($staff)->get(route('staff.dashboard'))->viewData('billing');
        $report = $this->actingAs($staff)->get(route('staff.billing.report'))->viewData('reportTotals');

        $this->assertEquals($report['totalCollected'], $dash['todaySales']);
        $this->assertEquals($report['cashPayments'], $dash['cashRevenue']);
        $this->assertSame($report['paymentTransactions'], $dash['transactionsToday']);
    }

    public function test_dashboard_labels_say_collections_not_sales(): void
    {
        $this->seedScenario();
        $res = $this->actingAs($this->cashier())->get(route('staff.dashboard'));

        // (assertSee escapes the apostrophe the same way Blade does)
        $res->assertSee("Today's Collections");
        $res->assertSee('Cash Collections');
        $res->assertDontSee("Today's Sales");
        $res->assertDontSee('Cash Revenue');
    }

    public function test_admin_dashboard_collected_today_matches_staff_collections_and_counts_the_last_second(): void
    {
        $this->seedScenario();
        $admin = $this->makeUser('Admin');

        $billing = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('billing');

        $this->assertEquals(1400, $billing['collectedToday']);
        $this->assertEquals(2650, $billing['outstanding']);
    }

    public function test_a_walk_in_invoice_is_reported_like_any_other(): void
    {
        $this->seedScenario();
        [$from, $to] = $this->range('today');
        $report = app(BillingReportService::class)->build($from, $to);

        // F is a walk-in medical-certificate invoice: counted once, in its own category.
        $this->assertEquals(500, $report['categories'][BillingReportService::CERTIFICATE]['gross']);
        $this->assertSame(1, $report['categories'][BillingReportService::CERTIFICATE]['lines']);
    }
}
