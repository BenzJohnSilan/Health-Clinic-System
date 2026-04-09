<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * If a user is already logged in, redirect them to the appropriate dashboard
     * based on their role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if any user is logged in via default 'web' guard
        if (Auth::check()) {
            $role = Auth::user()->role;

            // Redirect based on role
            return match($role) {
                'Admin' => redirect()->route('admin.dashboard'),
                'Doctor' => redirect()->route('doctor.dashboard'),
                'Patient' => redirect()->route('patient.dashboard'),
                default => redirect('/'),
            };
        }

        // If not logged in, continue to the requested page (e.g., login form)
        return $next($request);
    }
}