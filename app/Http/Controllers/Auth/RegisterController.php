<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $request->validate([
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'birthdate' => 'required|date|before:today',
            'gender' => 'required|in:Male,Female,Other',

            // ✅ FIXED
            'civil_status' => 'required|in:Single,Married,Widowed,Separated',

            'address' => 'required|string|max:255',

            // ✅ IMPROVED
            'contact_number' => 'required|string|max:20|unique:users',

            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|confirmed|min:6',
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'avatar' => $avatarPath,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'suffix' => $request->suffix,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
            'civil_status' => $request->civil_status,
            'address' => $request->address,
            'contact_number' => $request->contact_number,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'Patient',
        ]);

        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Account created successfully! Please verify your email to continue.');
    }
}