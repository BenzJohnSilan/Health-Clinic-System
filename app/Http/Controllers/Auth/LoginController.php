<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Max login attempts before lockout.
     */
    protected int $maxAttempts = 5;

    /**
     * Lockout duration in seconds (5 minutes).
     */
    protected int $decaySeconds = 300;

    // =========================================================
    //  SHOW LOGIN FORM
    // =========================================================
    public function show()
    {
        return view('auth.login');
    }

    // =========================================================
    //  HANDLE LOGIN
    // =========================================================
    public function authenticate(Request $request)
    {
        // --------------- VALIDATION ---------------
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        // --------------- RATE LIMIT CHECK ---------------
        $this->ensureIsNotRateLimited($request);

        // --------------- DETERMINE LOGIN TYPE ---------------
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        // --------------- ATTEMPT LOGIN ---------------
        if (!Auth::attempt([
            $loginType => $request->login,
            'password' => $request->password,
        ], $request->boolean('remember'))) {

            RateLimiter::hit($this->throttleKey($request), $this->decaySeconds);

            $remaining = RateLimiter::remaining($this->throttleKey($request), $this->maxAttempts);

            throw ValidationException::withMessages([
                'login' => $remaining > 0
                    ? "Invalid credentials. {$remaining} attempt(s) remaining before lockout."
                    : 'Too many login attempts. Your account has been temporarily locked.',
            ]);
        }

        // --------------- CLEAR RATE LIMITER ON SUCCESS ---------------
        RateLimiter::clear($this->throttleKey($request));

        // --------------- SESSION REGENERATE ---------------
        $request->session()->regenerate();

        $user = Auth::user();

        // --------------- EMAIL VERIFICATION CHECK ---------------
        // NOTE: Hindi i-lo-logout — kailangan naka-login para makita ang OTP page
        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // --------------- APPROVAL CHECK ---------------
        if ($user->approval_status !== 'Approved') {
            Auth::logout();
            return back()->withErrors([
                'login' => 'Your account is waiting for admin approval.',
            ]);
        }

        // --------------- ACCOUNT STATUS CHECK ---------------
        if ($user->status === 'Inactive') {
            Auth::logout();
            return back()->withErrors([
                'login' => 'Your account has been deactivated by admin.',
            ]);
        }

        // --------------- ROLE-BASED REDIRECT ---------------
        return match ($user->role) {
            'Admin'  => redirect()->route('admin.dashboard'),
            'Doctor' => redirect()->route('doctor.dashboard'),
            'Staff'  => redirect()->route('staff.dashboard'),
            default  => redirect()->route('patient.dashboard'),
        };
    }

    // =========================================================
    //  LOGOUT
    // =========================================================
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out.');
    }

    // =========================================================
    //  RATE LIMITER HELPERS
    // =========================================================

    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('login')) . '|' . $request->ip();
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey($request), $this->maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));
        $minutes = ceil($seconds / 60);

        throw ValidationException::withMessages([
            'login' => "Too many login attempts. Please try again in {$minutes} minute(s).",
        ]);
    }
}