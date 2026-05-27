<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Appointment;
use Carbon\Carbon;

class DoctorController extends Controller
{
    /**
     * Doctor Dashboard
     */
    public function dashboard()
    {
        $doctor = Auth::user();
        $today  = Carbon::today()->toDateString();

        // Today's queue
        $todayAppointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $today)
            ->where('status', 'Approved')
            ->orderBy('appointment_time')
            ->get();

        // Next patient up
        $nextPatient = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $today)
            ->where('status', 'Approved')
            ->orderBy('appointment_time')
            ->first();

        // Stats
        $totalAppointments = Appointment::where('doctor_id', $doctor->id)->count();

        $upcomingAppointments = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', '>=', $today)
            ->whereIn('status', ['Approved', 'Pending', 'Rescheduled'])
            ->count();

        $totalPatients = Appointment::where('doctor_id', $doctor->id)
            ->distinct('patient_id')
            ->count('patient_id');

        $pendingAppointments = Appointment::where('doctor_id', $doctor->id)
            ->where('status', 'Pending')
            ->count();

        $completedAppointments = Appointment::where('doctor_id', $doctor->id)
            ->where('status', 'Completed')
            ->count();

        // Recent appointments
        $recentAppointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->take(5)
            ->get();

        // Calendar data (last 6 months onward)
        $appointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['Pending', 'Approved', 'Rescheduled', 'Completed'])
            ->whereDate('appointment_date', '>=', Carbon::now()->subMonths(6))
            ->get();

        return view('doctor.dashboard', compact(
            'totalAppointments',
            'upcomingAppointments',
            'totalPatients',
            'pendingAppointments',
            'completedAppointments',
            'recentAppointments',
            'appointments',
            'todayAppointments',
            'nextPatient'
        ));
    }

    /**
     * Doctor Profile (read-only view)
     */
    public function profile()
    {
        $doctor = Auth::user();
        return view('doctor.profile', compact('doctor'));
    }

    /**
     * Doctor Account Settings (profile + password tabs)
     */
    public function accountSettings()
    {
        $doctor = Auth::user();
        return view('doctor.account-settings', compact('doctor'));
    }

    /**
     * Change Password Page (standalone, if still needed)
     */
    public function changePassword()
    {
        $doctor = Auth::user();
        return view('doctor.change-password', compact('doctor'));
    }

    /**
     * Update Doctor Profile
     * POST target: doctor.profile.update  →  PUT /doctor/account-settings/profile
     */
    public function updateProfile(Request $request)
    {
        $doctor = Auth::user();

        $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'gender'         => 'required|string|in:Male,Female,Other',
            'specialization' => 'required|string|max:255',   // ← matches blade field
            'address'        => 'required|string|max:500',
            'contact_number' => 'required|string|max:20',
            'username'       => 'required|string|max:255|unique:users,username,' . $doctor->id,
            'email'          => 'required|email|max:255|unique:users,email,' . $doctor->id,
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $doctor->first_name     = $request->first_name;
        $doctor->middle_name    = $request->middle_name;
        $doctor->last_name      = $request->last_name;
        $doctor->suffix         = $request->suffix;
        $doctor->gender         = $request->gender;
        $doctor->specialization = $request->specialization;  // ← matches blade field
        $doctor->address        = $request->address;
        $doctor->contact_number = $request->contact_number;
        $doctor->username       = $request->username;
        $doctor->email          = $request->email;

        if ($request->hasFile('avatar')) {
            $doctor->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $doctor->save();

        // Redirect back to account settings, profile tab
        return redirect()->route('doctor.account-settings')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Update Doctor Password
     * POST target: doctor.change-password.update  →  PUT /doctor/account-settings/password
     */
    public function updatePassword(Request $request)
    {
        $doctor = Auth::user();

        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:6',
        ]);

        if (!\Hash::check($request->current_password, $doctor->password)) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $doctor->password = bcrypt($request->password);
        $doctor->save();

        // Redirect back to account settings, password tab
        return redirect()->route('doctor.account-settings')
            ->with('success', 'Password updated successfully!');
    }
}