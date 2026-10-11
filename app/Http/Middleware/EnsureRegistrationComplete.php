<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Guards the Patient panel with the same gates the login form applies:
 * verified email, Admin-approved, not deactivated.
 *
 * Needed because a newly registered patient is signed in immediately (so the
 * OTP page works) and the framework's `verified` middleware does nothing for
 * this app (User does not implement MustVerifyEmail). Without this, a Pending
 * patient could type /patient/dashboard and skip both OTP and Admin approval.
 */
class EnsureRegistrationComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->approval_status !== 'Approved') {
            return $this->logoutWith($request, 'Your account is waiting for admin approval.');
        }

        if ($user->status === 'Inactive') {
            return $this->logoutWith($request, 'Your account has been deactivated by admin.');
        }

        return $next($request);
    }

    private function logoutWith(Request $request, string $message)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
