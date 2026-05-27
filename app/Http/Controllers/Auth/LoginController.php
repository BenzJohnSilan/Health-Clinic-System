<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function show()
    {
        return view('auth.login');
    }

    /**
     * Handle login form submission.
     */
    public function authenticate(Request $request)
    {
        // ================= VALIDATION =================
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        // ================= DETERMINE LOGIN TYPE =================
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';

        // ================= ATTEMPT LOGIN =================
        if (!Auth::attempt([
            $loginType => $request->login,
            'password' => $request->password
        ])) {
            return back()->with('error', 'Invalid credentials.')->withInput();
        }

        // ================= SESSION =================
        $request->session()->regenerate();
        $user = Auth::user();

        // ================= EMAIL VERIFICATION CHECK =================
        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        // ================= APPROVAL CHECK =================
        if ($user->approval_status !== 'Approved') {
            Auth::logout();
            return back()->with('error', 'Your account is waiting for admin approval.');
        }

        // ================= ACCOUNT STATUS CHECK =================
        if ($user->status === 'Inactive') {
            Auth::logout();
            return back()->with('error', 'Your account has been deactivated by admin.');
        }

        // ================= ROLE BASED REDIRECT =================
        switch ($user->role) {
            case 'Admin':
                return redirect()->route('admin.dashboard');

            case 'Doctor':
                return redirect()->route('doctor.dashboard');

            case 'Staff':
                return redirect()->route('staff.dashboard');

            case 'Patient':
            default:
                return redirect()->route('patient.dashboard');
        }
    }

    /**
     * Logout the user.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out.');
    }
}