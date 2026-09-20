<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Enforce a single feature permission on a route.
     *
     * Usage:
     *   Route::middleware(['auth', 'permission:view_medicine_inventory'])
     *
     * Multiple allowed permissions (any one of them passes) can be
     * given comma-separated:
     *   'permission:add_medicine,edit_medicine'
     *
     * This middleware is reusable by any future module — it only
     * needs the permission slug(s) to check, nothing module-specific.
     */
    public function handle(Request $request, Closure $next, string $permissions)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        // Admin is the top-level administrator and is never blocked by
        // feature-level permissions. Existing role middleware upstream
        // (role:Admin / role:Staff / etc.) already prevents a Patient
        // or any other role from reaching this point unless the route
        // is meant for them, so this bypass cannot leak Admin-only
        // routes to non-Admins.
        if ($user->role === 'Admin') {
            return $next($request);
        }

        $slugs = array_map('trim', explode(',', $permissions));

        if (!$user->hasAnyPermission($slugs)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
