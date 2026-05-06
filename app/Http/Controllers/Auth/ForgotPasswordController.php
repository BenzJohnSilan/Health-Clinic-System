<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /**
     * Show forgot password form
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link to email
     */
    public function sendResetLink(Request $request)
    {
        // Validate email input
        $request->validate([
            'email' => 'required|email'
        ]);

        // Send reset link
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Check result
        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Password reset link sent to your email.')
            : back()->withErrors([
                'email' => 'We could not find that email address in our records.'
            ]);
    }
}