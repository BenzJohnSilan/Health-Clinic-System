<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class ResetPasswordController extends Controller
{
    public function showResetForm($token)
    {
        // Pass the email from the query string to the view
        // so it can be prefilled and locked in the form
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                // Must contain at least one uppercase letter
                'regex:/[A-Z]/',
                // Must contain at least one lowercase letter
                'regex:/[a-z]/',
                // Must contain at least one digit
                'regex:/[0-9]/',
                // Must contain at least one special character
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'password.regex' => 'Password must include uppercase, lowercase, a number, and a special character.',
            'password.min'   => 'Password must be at least 8 characters.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset successful!')
            : back()->withErrors(['email' => [__($status)]]);
    }
}