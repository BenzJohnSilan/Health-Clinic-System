<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * The only roles that can ever appear on this page. Patient must
     * never be manageable here, and Admin does not need manual
     * permissions (full bypass via User::hasPermission()).
     */
    private const MANAGEABLE_ROLES = ['Doctor', 'Staff'];

    // ===============================
    // Manage Permissions — list + inline manage panel
    // ===============================
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $role   = $request->query('role');

        // Only Doctor/Staff are ever manageable here — guard against a
        // tampered role value sneaking into the query.
        if (!in_array($role, self::MANAGEABLE_ROLES, true)) {
            $role = '';
        }

        $users = User::whereIn('role', self::MANAGEABLE_ROLES)
            ->where('approval_status', 'Approved')
            ->where('status', 'Active')
            ->withCount('permissions')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $like = '%' . $search . '%';
                    $q->where('first_name', 'like', $like)
                      ->orWhere('last_name', 'like', $like)
                      ->orWhere('username', 'like', $like)
                      ->orWhere('email', 'like', $like);
                });
            })
            ->when($role !== '', function ($query) use ($role) {
                $query->where('role', $role);
            })
            ->orderBy('role')
            ->orderBy('first_name')
            ->paginate(15)
            ->appends($request->query());

        // Every user's currently assigned permission slugs, keyed by
        // user id, so the Blade view can pre-check the right boxes in
        // each user's Manage panel without an extra query per row.
        $userPermissionSlugs = User::whereIn('id', $users->pluck('id'))
            ->with('permissions:id,slug')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->permissions->pluck('slug')]);

        $groupedPermissions = Permission::orderBy('id')->get()->groupBy('module');

        return view('admin.permissions.index', [
            'users'               => $users,
            'groupedPermissions'  => $groupedPermissions,
            'userPermissionSlugs' => $userPermissionSlugs,
            'search'              => $search,
            'role'                => $role,
        ]);
    }

    // ===============================
    // Save the checked permissions for one Doctor/Staff user
    // ===============================
    public function update(Request $request, User $user)
    {
        // Guard against direct/manual requests trying to touch an
        // Admin or Patient account through this endpoint.
        if (!in_array($user->role, self::MANAGEABLE_ROLES, true)) {
            abort(403, 'This account cannot be managed from Manage Permissions.');
        }

        $request->validate([
            'permissions'   => 'array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $user->permissions()->sync($request->input('permissions', []));

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Permissions',
            'module'  => 'Account',
            'details' => trim($user->first_name . ' ' . $user->last_name) . " ({$user->role})",
        ]);

        return redirect()
            ->route('admin.permissions.index', [
                'search' => $request->input('search'),
                'role'   => $request->input('role'),
                'page'   => $request->input('page'),
            ])
            ->with('success', 'Permissions updated successfully.');
    }
}
