<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class PatientController extends Controller
{
    /**
     * Show the patient dashboard.
     */
    public function dashboard()
    {
        $patient = auth()->user();

        // Total appointments
        $totalAppointments = Appointment::where('patient_id', $patient->id)
            ->count();

        // Upcoming appointments (only APPROVED)
        $upcomingAppointments = Appointment::where('patient_id', $patient->id)
            ->where('status', 'Approved')             // ✅ only approved appointments
            ->orderBy('appointment_date', 'asc')      // sorted by date
            ->get();

        // All doctors (for Add Appointment modal)
        $doctors = User::where('role', 'Doctor')->get();

        return view('patient.dashboard', compact(
            'patient',
            'totalAppointments',
            'upcomingAppointments',
            'doctors'
        ));
    }

    /**
     * Show the patient profile page.
     */
    public function profile()
    {
        $patient = auth()->user();
        return view('patient.profile', compact('patient'));
    }

    /**
     * Update the patient profile.
     */
    public function updateProfile(Request $request)
    {
        $patient = auth()->user();

        // Validate all profile fields
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'gender' => 'required|string|in:Male,Female,Other',
            'civil_status' => 'required|string|in:Single,Married,Widowed,Separated',
            'address' => 'required|string|max:500',
            'contact_number' => 'required|string|max:20',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($patient->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($patient->id)],
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Update all fields
        $patient->first_name = $validated['first_name'];
        $patient->middle_name = $validated['middle_name'] ?? null;
        $patient->last_name = $validated['last_name'];
        $patient->suffix = $validated['suffix'] ?? null;
        $patient->gender = $validated['gender'];
        $patient->civil_status = $validated['civil_status'];
        $patient->address = $validated['address'];
        $patient->contact_number = $validated['contact_number'];
        $patient->username = $validated['username'];
        $patient->email = $validated['email'];

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($patient->avatar && Storage::disk('public')->exists($patient->avatar)) {
                Storage::disk('public')->delete($patient->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $patient->avatar = $path;
        }

        $patient->save();

        return redirect()->route('patient.profile')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Show the change password page.
     */
    public function changePassword()
    {
        return view('patient.change-password');
    }

    /**
     * Update the patient's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|confirmed|min:6',
        ]);

        $patient = auth()->user();

        if (!Hash::check($request->current_password, $patient->password)) {
            return back()->withErrors([
                'current_password' => 'Current password does not match.'
            ]);
        }

        $patient->password = Hash::make($request->password);
        $patient->save();

        // ✅ Redirect back to the same page instead of dashboard
        return redirect()->route('patient.change-password')
            ->with('success', 'Password updated successfully.');
    }
}