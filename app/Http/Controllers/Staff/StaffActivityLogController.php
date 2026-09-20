<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\UserLog;
use Illuminate\Http\Request;

class StaffActivityLogController extends Controller
{
    /**
     * Staff Activity Logs — read-only, and STRICTLY limited to the logs
     * belonging to the currently authenticated Staff account.
     *
     * Security note: the scoping happens here, in the backend query
     * (UserLog::forUser($id)), never in the Blade view. A Staff user can
     * never see another account's logs by editing the URL/query string —
     * every filter below is applied on top of that same user_id-scoped
     * query, so there is no code path that returns unscoped results.
     */
    public function index(Request $request)
    {
        $staffId = auth()->id();

        $query = UserLog::forUser($staffId)->orderBy('created_at', 'desc');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('details', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
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

        $logs = $query->paginate(10)->withQueryString();

        $modules = UserLog::MODULES;

        return view('staff.activity-logs', compact('logs', 'modules'));
    }
}
