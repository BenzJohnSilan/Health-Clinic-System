<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\OtpVerificationNotification;

class VerificationController extends Controller
{
    /**
     * Show the OTP verification page.
     */
    public function notice()
    {
        if (Auth::user()->email_verified_at) {
            Auth::logout();
            return redirect()->route('login')
                ->with('success', 'Email already verified. Please wait for admin approval.');
        }

        return view('auth.verify-email');
    }

    /**
     * Handle OTP verification.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'required|digits:6',
        ]);

        $user = Auth::user();

        if ($user->email_verified_at) {
            Auth::logout();
            return redirect()->route('login')
                ->with('success', 'Email already verified. Please wait for admin approval.');
        }

        if (!$user->otp_expires_at || now()->isAfter($user->otp_expires_at)) {
            return back()->with('error', 'Your OTP has expired. Please request a new one.');
        }

        if ($request->otp !== $user->email_otp) {
            return back()->with('error', 'Invalid OTP. Please try again.');
        }

        $user->email_verified_at = now();
        $user->email_otp         = null;
        $user->otp_expires_at    = null;
        $user->save();

        Auth::logout();

        return redirect()->route('login')
            ->with('success', 'Email verified successfully! Please wait for admin approval.');
    }

    /**
     * Resend OTP.
     */
    public function resend(Request $request)
    {
        $user = Auth::user();

        if ($user->email_verified_at) {
            Auth::logout();
            return redirect()->route('login')
                ->with('success', 'Your email is already verified. Please wait for admin approval.');
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->email_otp      = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        $user->notify(new OtpVerificationNotification($otp));

        return back()->with('success', 'A new OTP has been sent to your email.');
    }
}