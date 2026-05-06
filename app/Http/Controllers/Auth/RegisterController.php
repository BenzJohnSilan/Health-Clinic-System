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
            // PROFILE
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // PERSONAL INFO
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'birthdate'      => 'required|date|before:today',
            'gender'         => 'required|in:Male,Female,Other',
            'civil_status'   => 'required|in:Single,Married,Widowed,Separated',
            'address'        => 'required|string|max:255',

            'contact_number' => [
                'required',
                'unique:users,contact_number',
                'regex:/^09[0-9]{9}$/',
            ],

            // VERIFICATION
            'id_type'  => 'required|string|max:100',
            'valid_id' => 'required|image|mimes:jpg,jpeg,png|max:2048',

            // REASON
            'reason' => 'required|in:Check-up / Consultation,Appointment Booking,Medical Record Access,Others',

            // MEDICAL INFO
            'blood_type' => 'required|string',
            'allergies'  => 'nullable|string',

            // EMERGENCY CONTACT
            'emergency_name'           => 'required|string|max:255',
            'emergency_contact_number' => 'required|regex:/^09[0-9]{9}$/',
            'relationship'             => 'required|string|max:255',
            'emergency_address'        => 'required|string|max:255',

            // LOGIN INFO
            'username' => 'required|string|max:255|unique:users,username',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|confirmed|min:6',
        ], [
            'contact_number.regex' => 'Contact number must start with 09 and be exactly 11 digits.',
            'emergency_contact_number.regex' => 'Emergency contact must start with 09 and be exactly 11 digits.',
        ]);

        // Avatar upload
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // Valid ID upload
        $validIdPath = $request->file('valid_id')->store('valid_ids', 'public');

        $user = User::create([
            // PROFILE
            'avatar' => $avatarPath,

            // PERSONAL INFO
            'first_name'    => $request->first_name,
            'middle_name'   => $request->middle_name,
            'last_name'     => $request->last_name,
            'suffix'        => $request->suffix,
            'birthdate'     => $request->birthdate,
            'gender'        => $request->gender,
            'civil_status'  => $request->civil_status,
            'address'       => $request->address,
            'contact_number'=> $request->contact_number,

            // VERIFICATION
            'id_type'  => $request->id_type,
            'valid_id' => $validIdPath,

            // REASON
            'reason' => $request->reason,

            // MEDICAL INFO
            'blood_type' => $request->blood_type,
            'allergies'  => $request->allergies,

            // EMERGENCY CONTACT
            'emergency_name'           => $request->emergency_name,
            'emergency_contact_number' => $request->emergency_contact_number,
            'relationship'             => $request->relationship,
            'emergency_address'        => $request->emergency_address,

            // LOGIN INFO
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),

            // DEFAULT
            'role' => 'Patient',
            'approval_status' => 'Pending',
        ]);

        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Account created successfully! Please verify your email to continue.');
    }
}