<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        // ── Pre-store uploaded valid ID BEFORE validation ────────────────────────────
        // Browsers cannot re-populate <input type="file"> for security reasons.
        // We temporarily save the upload so it is never lost between attempts,
        // then remember its path in the session for the next attempt.

        if ($request->hasFile('valid_id')) {
            // Delete previous temp valid_id before storing the new one
            if (session('temp_valid_id')) {
                Storage::disk('public')->delete(session('temp_valid_id'));
            }
            // Store with an explicit "valid_id_" prefix so it is always distinguishable
            $ext  = $request->file('valid_id')->getClientOriginalExtension();
            $name = 'valid_id_' . Str::uuid() . '.' . $ext;
            $request->file('valid_id')->storeAs('temp_uploads', $name, 'public');
            session(['temp_valid_id' => 'temp_uploads/' . $name]);
        }

        // ── Validation ───────────────────────────────────────────────────────────────
        $request->validate([
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

            // VERIFICATION — required only when no temp file exists yet
            'id_type'  => 'required|string|max:100',
            'valid_id' => [
                session('temp_valid_id') ? 'nullable' : 'required',
                'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048',
            ],

            // NOTE: Profile Picture (avatar) and Reason for Registration are
            // intentionally NOT collected during registration. Avatar can be
            // added later by the patient via the Patient Profile (Account
            // Settings) page. See PatientController::updateProfile().

            // NOTE: Medical Information (blood type, allergies) and Emergency
            // Contact are also intentionally NOT collected during registration.
            // The patient completes those later, after admin approval, via
            // the Patient Profile (Account Settings) page before booking an
            // appointment. See PatientController::updateProfile().

            // LOGIN INFO
            'username' => 'required|string|max:255|unique:users,username',
            'email'    => 'required|email|max:255|unique:users,email',

            // BASE PASSWORD RULES (length + confirmation)
            // Per-character-type checks run in the after() hook below so each
            // requirement produces its own separate error message.
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'contact_number.regex'           => 'Contact number must start with 09 and be exactly 11 digits.',
            'valid_id.required'              => 'Please upload a valid ID.',
            'password.required'              => 'Password is required.',
            'password.confirmed'             => 'Password confirmation does not match.',
            'password.min'                   => 'Password must be at least 8 characters long.',
        ]);

        // ── Per-requirement password checks (each produces its own message) ──────────
        // Runs only after the base rules pass (field exists, min length met, confirmed).
        // Using a separate Validator with after() ensures Laravel redirects back with
        // all old input intact — exactly like a normal validation failure.
        $pw = $request->input('password', '');

        $passwordValidator = Validator::make($request->all(), [])
            ->after(function ($v) use ($pw) {
                if (!preg_match('/[A-Z]/', $pw)) {
                    $v->errors()->add('password', 'Password must contain at least one uppercase letter (A-Z).');
                }
                if (!preg_match('/[a-z]/', $pw)) {
                    $v->errors()->add('password', 'Password must contain at least one lowercase letter (a-z).');
                }
                if (!preg_match('/[0-9]/', $pw)) {
                    $v->errors()->add('password', 'Password must contain at least one number (0-9).');
                }
                if (!preg_match('/[@$!%*#?&]/', $pw)) {
                    $v->errors()->add('password', 'Password must contain at least one special character (@$!%*#?&).');
                }
            });

        if ($passwordValidator->fails()) {
            throw new \Illuminate\Validation\ValidationException($passwordValidator);
        }

        // ── Validation passed — move temp valid ID to its permanent folder ───────────
        // valid_id is guaranteed to exist at this point (passed validation above)
        $filename    = basename(session('temp_valid_id')); // e.g. valid_id_<uuid>.jpg
        $validIdPath = 'valid_ids/' . $filename;
        Storage::disk('public')->move(session('temp_valid_id'), $validIdPath);
        session()->forget('temp_valid_id');

        // ── Create the user ──────────────────────────────────────────────────────────
        $user = User::create([
            'first_name'     => $request->first_name,
            'middle_name'    => $request->middle_name,
            'last_name'      => $request->last_name,
            'suffix'         => $request->suffix,
            'birthdate'      => $request->birthdate,
            'gender'         => $request->gender,
            'civil_status'   => $request->civil_status,
            'address'        => $request->address,
            'contact_number' => $request->contact_number,

            'id_type'        => $request->id_type,
            'valid_id'       => $validIdPath,  // always in valid_ids/, never avatars/

            // Profile Picture (avatar) & Reason are completed later by the
            // patient via the Patient Profile page (not collected at
            // registration).

            // Medical Information & Emergency Contact are also completed
            // later by the patient via the Patient Profile page (not
            // collected at registration — see PatientController::updateProfile()).

            'username'       => $request->username,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),

            'role'            => 'Patient',
            'approval_status' => 'Pending',
        ]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->email_otp      = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();
        $user->notify(new \App\Notifications\OtpVerificationNotification($otp));

        // Same mechanism Admin\UserController::store() already uses for
        // Doctor/Staff accounts it creates — grants this brand-new
        // Patient the Billing & Payments read-only defaults
        // (view_billing, view_payment_history) so they can see their
        // own invoices/receipts once approved. Never touches any other
        // permission and grants nothing beyond Permission::
        // defaultSlugsForRole('Patient').
        Permission::assignDefaultsTo($user);

        Auth::login($user);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Account created successfully! Please verify your email to continue.');
    }
}