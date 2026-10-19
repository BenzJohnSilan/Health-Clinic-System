<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\UserLog;
use App\Services\BillingReportService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    // ================= DASHBOARD =================
    /**
     * One Staff Dashboard for every Staff account. Its CONTENT depends on
     * what the signed-in account is permitted to do — never on the
     * Position text (e.g. "Cashier"), which is only a label:
     *
     *  - view_appointments  -> appointment summary cards, Today's
     *                          Appointments, pending appointments alert
     *  - view_billing       -> Unpaid Invoices card, billing alerts
     *  - view_billing_reports -> Today's Collections / Transactions /
     *                          Discounts / Cash Collections cards (same data the Billing
     *                          report cards are gated by)
     *  - view_payment_history -> Today's Transactions list (+ receipt links)
     *
     * A Staff member with both appointment and billing permissions sees
     * both sets; with neither, the dashboard still renders (no errors) and
     * simply shows the greeting and whatever quick actions they have.
     * Data for a section is only queried when its permission is held.
     */
    public function dashboard()
    {
        $staff = auth()->user();
        $today = Carbon::today();

        // Resolve every permission the dashboard depends on exactly once.
        $can = [
            'appointments'    => $staff->hasPermission('view_appointments'),
            'patients'        => $staff->hasPermission('view_patients'),
            'billing'         => $staff->hasPermission('view_billing'),
            'record_payment'  => $staff->hasPermission('record_payment'),
            'payment_history' => $staff->hasPermission('view_payment_history'),
            'billing_reports' => $staff->hasPermission('view_billing_reports'),
            'medicine'        => $staff->hasPermission('view_medicine_inventory'),
        ];

        // ---------- Appointments (view_appointments only) ----------
        $totalAppointments      = 0;
        $pendingAppointments    = 0;
        $approvedAppointments   = 0;
        $completedAppointments  = 0;
        $cancelledAppointments  = 0;
        $totalTodayAppointments = 0;
        $todayAppointments      = collect();

        if ($can['appointments']) {
            $totalAppointments     = Appointment::count();
            $pendingAppointments   = Appointment::where('status', 'Pending')->count();
            $approvedAppointments  = Appointment::where('status', 'Approved')->count();
            $completedAppointments = Appointment::where('status', 'Completed')->count();
            $cancelledAppointments = Appointment::where('status', 'Cancelled')->count();

            // Today's appointments, earliest first — only the next 5 are shown
            // on the dashboard card so it stays compact; the total is fetched
            // separately (count only) so the "Showing X of Y" text underneath
            // stays accurate even though the display list itself is limited.
            $totalTodayAppointments = Appointment::whereDate('appointment_date', $today)->count();

            $todayAppointments = Appointment::with(['patient', 'walkinPatient', 'doctor'])
                ->whereDate('appointment_date', $today)
                ->orderByRaw('appointment_time IS NULL')   // walk-ins (no reserved time) after scheduled
                ->orderBy('appointment_time', 'asc')
                ->orderBy('arrived_at', 'asc')
                ->take(5)
                ->get();
        }

        // ---------- Billing (view_billing, then per-section permissions) ----------
        $billing = $can['billing']
            ? $this->billingSummary($can['billing_reports'], $can['payment_history'])
            : $this->emptyBillingSummary();

        // Same greeting logic/time bands as the Patient Dashboard
        // (PatientController::dashboard), kept in sync intentionally.
        $hour = Carbon::now()->hour;
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good Morning';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Good Afternoon';
        } else {
            $greeting = 'Good Evening';
        }

        return view('staff.dashboard', compact(
            'staff',
            'greeting',
            'can',
            'billing',
            'totalAppointments',
            'pendingAppointments',
            'approvedAppointments',
            'completedAppointments',
            'cancelledAppointments',
            'todayAppointments',
            'totalTodayAppointments'
        ));
    }

    /**
     * Billing figures for the dashboard, all read from the real billing
     * tables (nothing hardcoded) with the same rules the Billing & Payments
     * page already uses:
     *
     *  - invoices are limited to Invoice::collectible() (pay-after-service:
     *    only Completed consultations / Medical Certificate invoices);
     *  - a "transaction" is one row in `payments` — a payment row only ever
     *    exists once Staff recorded it, so every row is a completed payment
     *    (the table has no pending/failed status);
     *  - invoice statuses are the existing Unpaid / Partially Paid / Paid.
     *
     * The money figures are report data, so they are only computed for a
     * Staff member who holds view_billing_reports; the transactions list
     * (patient names, invoice numbers, receipt links) only for
     * view_payment_history.
     */
    private function billingSummary(bool $withReports, bool $withTransactions): array
    {
        $summary = $this->emptyBillingSummary();

        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $summary['unpaidInvoices'] = Invoice::collectible()
            ->where('status', 'Unpaid')
            ->count();

        $summary['partiallyPaidInvoices'] = Invoice::collectible()
            ->where('status', 'Partially Paid')
            ->count();

        $paymentsToday = fn () => Payment::whereBetween('paid_at', [$start, $end]);

        if ($withReports) {
            // Collections = money actually received today (payment date), read
            // through the SAME service method the Revenue Report uses, so the
            // dashboard and the report can never disagree.
            $pay = app(BillingReportService::class)->paymentTotals($start, $end);

            $summary['todaySales']        = $pay['total'];          // card: Today's Collections
            $summary['transactionsToday'] = $pay['transactions'];
            $summary['cashRevenue']       = $pay['cash'];           // card: Cash Collections

            // Today's Discounts = the discount (invoices.discount, which is
            // the sum of the line discounts) of invoices that received their
            // FIRST payment today. Discounts are locked once a payment exists,
            // so this is the moment the discount is actually applied to a
            // transaction; counting by first payment also means an invoice
            // paid in several instalments (or across several days) never has
            // its discount counted twice. (The Revenue Report instead counts
            // invoices CREATED in the period - see its definitions box.)
            $paidTodayIds = $paymentsToday()->distinct()->pluck('invoice_id');

            if ($paidTodayIds->isNotEmpty()) {
                $paidBeforeToday = Payment::whereIn('invoice_id', $paidTodayIds)
                    ->where('paid_at', '<', $start)
                    ->distinct()
                    ->pluck('invoice_id');

                $firstPaidToday = $paidTodayIds->diff($paidBeforeToday);

                if ($firstPaidToday->isNotEmpty()) {
                    $summary['todayDiscounts'] = Money::pesos(Money::cents(
                        Invoice::whereIn('id', $firstPaidToday)->sum('discount')
                    ));
                }
            }
        }

        if ($withTransactions) {
            $summary['totalTodayTransactions'] = $paymentsToday()->count();

            // Newest first; only the latest 5 are shown on the card.
            $summary['transactions'] = Payment::with(['invoice.patient', 'invoice.walkinPatient'])
                ->whereBetween('paid_at', [$start, $end])
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->take(5)
                ->get();
        }

        return $summary;
    }

    /** Zeroed billing figures so the dashboard view never hits a missing key. */
    private function emptyBillingSummary(): array
    {
        return [
            'unpaidInvoices'         => 0,
            'partiallyPaidInvoices'  => 0,
            'todaySales'             => 0.0,
            'transactionsToday'      => 0,
            'todayDiscounts'         => 0.0,
            'cashRevenue'            => 0.0,
            'totalTodayTransactions' => 0,
            'transactions'           => collect(),
        ];
    }

    // ================= ACCOUNT SETTINGS =================
    public function settings()
    {
        $staff = auth()->user();

        return view('staff.account-settings', compact('staff'));
    }

    // ================= PROFILE =================
    public function profile()
    {
        $staff = auth()->user();

        return view('staff.profile', compact('staff'));
    }

    // ================= CHANGE PASSWORD PAGE =================
    public function changePassword()
    {
        return view('staff.change-password');
    }

    // ================= UPDATE PROFILE =================
    public function updateProfile(Request $request)
    {
        $staff = auth()->user();

        $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'gender'         => 'nullable|string|max:50',
            'civil_status'   => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:500',
            'contact_number' => 'nullable|string|max:20',
            'username'       => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'avatar'         => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'gender',
            'civil_status',
            'address',
            'contact_number',
            'username',
            'email'
        ]);

        // ================= HANDLE AVATAR UPLOAD =================
        if ($request->hasFile('avatar')) {

            // Delete old avatar
            if ($staff->avatar && Storage::disk('public')->exists($staff->avatar)) {
                Storage::disk('public')->delete($staff->avatar);
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');

            $data['avatar'] = $path;
        }

        $staff->update($data);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Profile',
            'module'  => 'Account',
            'details' => 'Updated account profile information.',
        ]);

        return back()->with('success', 'Profile updated successfully!');
    }

    // ================= UPDATE AVATAR (independent from profile form) =================
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $staff = auth()->user();

        if ($staff->avatar && Storage::disk('public')->exists($staff->avatar)) {
            Storage::disk('public')->delete($staff->avatar);
        }

        $staff->avatar = $request->file('avatar')->store('avatars', 'public');
        $staff->save();

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Profile Picture',
            'module'  => 'Account',
            'details' => 'Updated profile picture.',
        ]);

        return redirect()->route('staff.settings')
            ->with('success', 'Profile picture updated successfully!');
    }

    // ================= REMOVE AVATAR =================
    public function removeAvatar()
    {
        $staff = auth()->user();

        if ($staff->avatar) {
            if (Storage::disk('public')->exists($staff->avatar)) {
                Storage::disk('public')->delete($staff->avatar);
            }

            $staff->avatar = null;
            $staff->save();

            UserLog::create([
                'user_id' => auth()->id(),
                'action'  => 'Removed Profile Picture',
                'module'  => 'Account',
                'details' => 'Removed profile picture.',
            ]);
        }

        return redirect()->route('staff.settings')
            ->with('success', 'Profile picture removed successfully!');
    }

    // ================= UPDATE PASSWORD =================
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:6|confirmed',
        ]);

        $staff = auth()->user();

        // Check current password
        if (!Hash::check($request->current_password, $staff->password)) {

            return back()->withErrors([
                'current_password' => 'Incorrect current password.'
            ]);
        }

        // Update password
        $staff->update([
            'password' => Hash::make($request->password)
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Changed Password',
            'module'  => 'Account',
            'details' => 'Changed account password.',
        ]);

        return back()->with('success', 'Password updated successfully!');
    }
}