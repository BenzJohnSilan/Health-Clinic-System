<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\OtpVerificationNotification;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class VerificationController extends Controller
{
    // Wrong-code limits (a legitimate typo or two never comes close).
    private const VERIFY_PER_ACCOUNT = 10;  // per account, per window
    private const VERIFY_PER_IP      = 30;  // per IP, per window
    private const VERIFY_WINDOW_SEC  = 900; // 15 minutes

    // "Resend code" limits.
    private const RESEND_COOLDOWN_SEC = 60; // one email per minute per account
    private const RESEND_PER_HOUR     = 5;  // per account
    private const RESEND_PER_IP_HOUR  = 10; // per IP

    public function __construct(private EmailOtpService $otp)
    {
    }

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

        // ── Brute-force protection (account + IP) ────────────────────────────────────
        $accountKey = 'otp-verify:user:' . $user->id;
        $ipKey      = 'otp-verify:ip:' . $request->ip();

        foreach ([[$accountKey, self::VERIFY_PER_ACCOUNT], [$ipKey, self::VERIFY_PER_IP]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

                return back()->with('error', "Too many attempts. Please try again in {$minutes} minute(s).");
            }
        }

        RateLimiter::hit($accountKey, self::VERIFY_WINDOW_SEC);
        RateLimiter::hit($ipKey, self::VERIFY_WINDOW_SEC);

        // ── Check the code (hashed compare, expiry, attempt cap, single use) ─────────
        $result = $this->otp->verify($user, (string) $request->input('otp'));

        if ($result === EmailOtpService::VERIFIED) {
            RateLimiter::clear($accountKey);

            Log::info('otp.verified', ['user_id' => $user->id, 'ip' => $request->ip()]);

            Auth::logout();

            return redirect()->route('login')
                ->with('success', 'Email verified successfully! Please wait for admin approval.');
        }

        Log::warning('otp.failed', ['user_id' => $user->id, 'ip' => $request->ip(), 'reason' => $result]);

        return back()->with('error', match ($result) {
            EmailOtpService::EXPIRED => 'Your OTP has expired. Please request a new one.',
            EmailOtpService::LOCKED  => 'Too many incorrect attempts. Please request a new code.',
            default                  => 'Invalid OTP. Please try again.',
        });
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

        $cooldownKey = 'otp-resend:cooldown:' . $user->id;
        $hourKey     = 'otp-resend:hour:' . $user->id;
        $ipKey       = 'otp-resend:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)
            || RateLimiter::tooManyAttempts($hourKey, self::RESEND_PER_HOUR)
            || RateLimiter::tooManyAttempts($ipKey, self::RESEND_PER_IP_HOUR)) {
            return back()->with('error', 'Please wait before requesting another code.');
        }

        RateLimiter::hit($cooldownKey, self::RESEND_COOLDOWN_SEC);
        RateLimiter::hit($hourKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        // Issuing a new code replaces (invalidates) the previous one.
        $otp = $this->otp->issue($user);

        try {
            $user->notify(new OtpVerificationNotification($otp));
        } catch (Throwable $e) {
            report($e);
            RateLimiter::clear($cooldownKey);

            return back()->with('error', 'We could not send the email right now. Please try again in a moment.');
        }

        return back()->with('success', 'A new OTP has been sent to your email.');
    }
}
