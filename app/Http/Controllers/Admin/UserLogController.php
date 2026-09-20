<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserLog;
use Illuminate\Http\Request;

class UserLogController extends Controller
{
    /**
     * The account roles a log's actor can have. Drives the Role filter
     * on the Admin User Logs page and mirrors the `role` enum on the
     * users table (see the users migration).
     */
    public const ROLES = ['Admin', 'Staff', 'Doctor', 'Patient'];

    /**
     * Admin User Logs — the system-wide audit trail. Unlike the Staff /
     * Patient Activity Logs pages (which are hard-scoped to the logged-in
     * account via UserLog::forUser()), this page is intentionally
     * unscoped: Admin is allowed to see every actor's activity.
     *
     * The `user_id` column always represents the actor who performed the
     * action (never the patient/doctor/user a given action was performed
     * on) — every UserLog::create() call site across the app sets it to
     * auth()->id(). This controller only reads that data; it never writes
     * logs itself.
     */
    public function index(Request $request)
    {
        $query = UserLog::with('user')->orderBy('created_at', 'desc');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        if ($role = $request->query('role')) {
            if (in_array($role, self::ROLES, true)) {
                $query->whereHas('user', function ($uq) use ($role) {
                    $uq->where('role', $role);
                });
            }
        }

        if ($module = $request->query('module')) {
            if (in_array($module, UserLog::MODULES, true)) {
                $query->where('module', $module);
            }
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->paginate(15)->withQueryString();

        $roles   = self::ROLES;
        $modules = UserLog::MODULES;

        return view('admin.user-logs', compact('logs', 'roles', 'modules'));
    }
}
