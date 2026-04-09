<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Auth;

class VerificationController extends Controller
{
    /**
     * Show the email verification notice.
     */
    public function notice()
    {
        return view('auth.verify-email');
    }

    /**
     * Handle the email verification.
     */
    public function verify(EmailVerificationRequest $request)
    {
        // Mark email as verified
        $request->fulfill();

        // Logout the user after verification
        Auth::logout();

        // Redirect to login page with success message
        return redirect()->route('login')
            ->with('success', 'Email verified successfully! Please wait for admin approval.');
    }

    /**
     * Resend the verification email.
     */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('login')
                ->with('success', 'Your email is already verified. Please wait for admin approval.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Verification link sent to your email!');
    }
}