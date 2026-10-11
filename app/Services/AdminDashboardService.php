<?php

namespace App\Services;

use App\Http\Controllers\Staff\StaffAppointmentController;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserLog;
use Carbon\Carbon;

/**
 * Builds every number/list shown on the Admin Dashboard.
 *
 * Nothing here introduces new workflow, status, or table — it only reads
 * what the existing modules already maintain:
 *
 *  - Appointments : same `status` enum + Appointment::STATUSES list the
 *                   Appointments page uses (StaffAppointmentController).
 *  - Patients     : the SAME two sources the Admin Patients page uses —
 *                   registered patients = users (role Patient, Approved),
 *                   walk-in patients = patients table (is_walk_in = true).
 *                   `patients` rows with is_walk_in = false are NOT counted,
 *                   so nobody is counted twice.
 *  - Inventory    : same Low Stock / Out of Stock status + is_expired /
 *                   is_expiring_soon accessors the Inventory Reports page uses.
 *  - Billing      : Invoice.status / balance and Payment.amount / paid_at,
 *                   same figures the Admin Billing page totals.
 *  - Activity     : the existing `user_logs` audit trail.
 */
class AdminDashboardService
{
    /** Rows shown in the dashboard's compact lists. */
    public const TODAY_LIST_LIMIT   = 8;
    public const ACTIVITY_LIMIT     = 5;
    public const MEDICINE_LIST_LIMIT = 5;

    /** Chart colors (shared by the chart and its legend so they always match). */
    private const GENDER_COLORS = [
        'Male'   => '#4f46e5',
        'Female' => '#ec4899',
        'Other'  => '#10b981',
    ];

    private const STATUS_COLORS = [
        'Pending'     => '#f59e0b',
        'Approved'    => '#10b981',
        'Checked In'  => '#14b8a6',
        'In Progress' => '#f97316',
        'Rescheduled' => '#9b30ff',
        'Completed'   => '#4f46e5',
        'Cancelled'   => '#9ca3af',
        'Rejected'    => '#64748b',
        'No Show'     => '#ef4444',
    ];

    /**
     * Age buckets. Upper bound is inclusive; the last bucket has no upper
     * bound. NOTE: "46–60" and "60+" cannot both contain 60, so 60+ is
     * implemented as 61 and above.
     */
    private const AGE_GROUPS = [
        ['label' => '0–17',  'min' => 0,  'max' => 17],
        ['label' => '18–30', 'min' => 18, 'max' => 30],
        ['label' => '31–45', 'min' => 31, 'max' => 45],
        ['label' => '46–60', 'min' => 46, 'max' => 60],
        ['label' => '60+',   'min' => 61, 'max' => null],
    ];

    public function build(): array
    {
        // Carbon::today()/now() follow config('app.timezone') (Asia/Manila).
        $today = Carbon::today();

        return [
            'todayDate'        => $today->toDateString(),
            'todayLabel'       => Carbon::now()->format('l, F j, Y'),
            'overview'         => $this->todayOverview($today),
            'todayAppointments' => $this->todayAppointments($today),
            'pendingActions'   => $this->pendingActions(),
            'patientStats'     => $this->patientStatistics(),
            'appointmentStats' => $this->appointmentStatistics(),
            'inventory'        => $this->inventoryAlerts(),
            'billing'          => $this->billingOverview($today),
            'recentActivity'   => $this->recentActivity(),
        ];
    }

    // =====================================================================
    // 1. TODAY'S OVERVIEW
    // =====================================================================
    private function todayOverview(Carbon $today): array
    {
        $byStatus = Appointment::whereDate('appointment_date', $today)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total'      => (int) $byStatus->sum(),
            'pending'    => (int) ($byStatus['Pending'] ?? 0),
            'checkedIn'  => (int) ($byStatus['Checked In'] ?? 0),
            'inProgress' => (int) ($byStatus['In Progress'] ?? 0),
            'completed'  => (int) ($byStatus['Completed'] ?? 0),
            'noShow'     => (int) ($byStatus['No Show'] ?? 0),
        ];
    }

    // =====================================================================
    // 2. TODAY'S APPOINTMENTS
    // =====================================================================
    private function todayAppointments(Carbon $today): array
    {
        $base = Appointment::whereDate('appointment_date', $today);

        return [
            'total' => (clone $base)->count(),
            // patient (registered) + walkinPatient are both eager loaded so
            // Appointment::patientName() resolves either one with no N+1.
            'rows'  => (clone $base)
                ->with(['patient', 'walkinPatient', 'doctor'])
                ->orderBy('appointment_time')
                ->limit(self::TODAY_LIST_LIMIT)
                ->get(),
        ];
    }

    // =====================================================================
    // 3. PENDING ACTIONS
    // =====================================================================
    private function pendingActions(): array
    {
        // Same definition as AdminController::pendingAccounts().
        $pendingAccounts = User::where('approval_status', 'Pending')
            ->where('role', '!=', 'Admin')
            ->count();

        $pendingAppointments = Appointment::where('status', 'Pending')->count();

        // Invoices only exist once a consultation is Completed (pay-after-
        // service), so every Unpaid invoice is already collectible.
        $unpaid = Invoice::where('status', 'Unpaid');

        return [
            'accounts'        => $pendingAccounts,
            'appointments'    => $pendingAppointments,
            'unpaidInvoices'  => (clone $unpaid)->count(),
            'unpaidBalance'   => (float) (clone $unpaid)->sum('balance'),
            'total'           => $pendingAccounts + $pendingAppointments + (clone $unpaid)->count(),
        ];
    }

    // =====================================================================
    // 4. PATIENT STATISTICS
    // =====================================================================
    private function patientStatistics(): array
    {
        // Registered patients — identical scope to the Admin Patients page.
        $registered = User::where('role', 'Patient')
            ->where('approval_status', 'Approved')
            ->get(['id', 'gender', 'birthdate']);

        // Walk-in patients only. `patients` rows linked to a user
        // (is_walk_in = false) are the same person as the users row above.
        $walkIns = Patient::where('is_walk_in', true)
            ->get(['id', 'gender', 'birthdate']);

        $everyone = $registered->concat($walkIns);

        // ---- gender ----
        $genderCounts = $everyone->countBy('gender');
        $gender = [];
        foreach (self::GENDER_COLORS as $label => $color) {
            $value = (int) ($genderCounts[$label] ?? 0);

            // Male / Female are always listed; "Other" only when it exists.
            if ($value > 0 || $label !== 'Other') {
                $gender[] = ['label' => $label, 'value' => $value, 'color' => $color];
            }
        }
        $genderTotal = array_sum(array_column($gender, 'value'));

        // ---- age (always derived from birthdate, never stored) ----
        $ageGroups = array_map(
            fn ($g) => ['label' => $g['label'], 'value' => 0],
            self::AGE_GROUPS
        );
        $ageUnknown = 0;

        foreach ($everyone as $person) {
            $age = $person->birthdate ? $person->birthdate->age : null;

            if ($age === null || $age < 0) {
                $ageUnknown++;
                continue;
            }

            foreach (self::AGE_GROUPS as $i => $group) {
                if ($age >= $group['min'] && ($group['max'] === null || $age <= $group['max'])) {
                    $ageGroups[$i]['value']++;
                    break;
                }
            }
        }

        return [
            'total'       => $everyone->count(),
            'registered'  => $registered->count(),
            'walkIn'      => $walkIns->count(),
            'gender'      => $gender,
            'genderTotal' => $genderTotal,
            // Patients whose gender field is empty (only possible on walk-in
            // records) — surfaced as a note so the chart total is explained.
            'genderUnknown' => $everyone->count() - $genderTotal,
            'ageGroups'   => $ageGroups,
            'ageTotal'    => array_sum(array_column($ageGroups, 'value')),
            'ageUnknown'  => $ageUnknown,
        ];
    }

    // =====================================================================
    // 5. APPOINTMENT STATISTICS
    // =====================================================================
    private function appointmentStatistics(): array
    {
        $byStatus = Appointment::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Reuse the Appointments page's status list so only statuses the
        // system really supports are charted, in the same order.
        $items = [];
        foreach (StaffAppointmentController::STATUSES as $status) {
            $items[] = [
                'label' => $status,
                'value' => (int) ($byStatus[$status] ?? 0),
                'color' => self::STATUS_COLORS[$status] ?? '#9ca3af',
            ];
        }

        return [
            'items' => $items,
            'total' => array_sum(array_column($items, 'value')),
        ];
    }

    // =====================================================================
    // 6. MEDICINE / INVENTORY ALERTS
    // =====================================================================
    private function inventoryAlerts(): array
    {
        // Same counting rules as MedicineController::reports().
        $medicines = Medicine::orderBy('medicine_name')->get();

        $counts = ['low' => 0, 'out' => 0, 'expiring' => 0, 'expired' => 0];
        $flagged = [];

        foreach ($medicines as $medicine) {
            $isOut      = $medicine->status === 'Out of Stock';
            $isLow      = $medicine->status === 'Low Stock';
            $isExpired  = $medicine->is_expired;
            $isExpiring = ! $isExpired && $medicine->is_expiring_soon;

            $counts['out']      += $isOut ? 1 : 0;
            $counts['low']      += $isLow ? 1 : 0;
            $counts['expired']  += $isExpired ? 1 : 0;
            $counts['expiring'] += $isExpiring ? 1 : 0;

            // Most serious problem decides the badge + sort order.
            $issue = match (true) {
                $isExpired  => ['Expired', 'red', 1],
                $isOut      => ['Out of Stock', 'red', 2],
                $isLow      => ['Low Stock', 'amber', 3],
                $isExpiring => ['Expiring Soon', 'orange', 4],
                default     => null,
            };

            if ($issue) {
                $flagged[] = [
                    'name'     => $medicine->medicine_name,
                    'detail'   => trim(($medicine->brand ?? '') . ' ' . ($medicine->dosage ?? '')),
                    'quantity' => (int) $medicine->quantity,
                    'unit'     => $medicine->unit,
                    'expires'  => $medicine->expiration_date?->format('M d, Y'),
                    'badge'    => $issue[0],
                    'tone'     => $issue[1],
                    'priority' => $issue[2],
                ];
            }
        }

        usort($flagged, fn ($a, $b) => [$a['priority'], $a['name']] <=> [$b['priority'], $b['name']]);

        return [
            'counts'     => $counts,
            'flagged'    => count($flagged),
            'list'       => array_slice($flagged, 0, self::MEDICINE_LIST_LIMIT),
        ];
    }

    // =====================================================================
    // 7. BILLING OVERVIEW
    // =====================================================================
    private function billingOverview(Carbon $today): array
    {
        $billedToday = Invoice::whereDate('created_at', $today)
            ->where('status', '!=', 'Cancelled');

        $byStatus = Invoice::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'billedToday'    => (float) (clone $billedToday)->sum('total_amount'),
            'invoicesToday'  => (clone $billedToday)->count(),
            'collectedToday' => (float) Payment::whereDate('paid_at', $today)->sum('amount'),
            'paid'           => (int) ($byStatus['Paid'] ?? 0),
            'partial'        => (int) ($byStatus['Partially Paid'] ?? 0),
            'unpaid'         => (int) ($byStatus['Unpaid'] ?? 0),
            // Same formula the Admin Billing page uses for "outstanding".
            'outstanding'    => (float) Invoice::whereIn('status', ['Unpaid', 'Partially Paid'])->sum('balance'),
        ];
    }

    // =====================================================================
    // 8. RECENT ACTIVITY
    // =====================================================================
    private function recentActivity()
    {
        // Real audit-trail rows only. `details` is intentionally not sent to
        // the dashboard — action + actor + module + time is enough, and it
        // keeps any record-specific text off this page.
        return UserLog::with('user:id,first_name,last_name,role')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get(['id', 'user_id', 'action', 'module', 'created_at']);
    }
}