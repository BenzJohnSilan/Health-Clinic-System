<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $today = Carbon::today();

        // Same greeting logic/time bands as the Staff/Patient Dashboards
        // (StaffController::dashboard / PatientController::dashboard),
        // kept in sync intentionally.
        $hour = Carbon::now()->hour;
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good Morning';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Good Afternoon';
        } else {
            $greeting = 'Good Evening';
        }

        // Today's appointments / queue
        $todayAppointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $today)
            ->whereIn('status', [
                'Approved',
                'Rescheduled',
                'Checked In',
                'In Progress',
            ])
            ->orderBy('appointment_time')
            ->get();

        // Next patient for today
        $nextPatient = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $today)
            ->whereIn('status', [
                'Approved',
                'Rescheduled',
                'Checked In',
                'In Progress',
            ])
            ->orderBy('appointment_time')
            ->first();

        // Total appointments assigned to this doctor
        $totalAppointments = Appointment::where('doctor_id', $doctor->id)
            ->count();

        // Upcoming appointments from today onward
        $upcomingAppointments = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', '>=', $today)
            ->whereIn('status', [
                'Pending',
                'Approved',
                'Rescheduled',
                'Checked In',
                'In Progress',
            ])
            ->count();

        // Total unique registered patients
        $registeredPatients = Appointment::where('doctor_id', $doctor->id)
            ->whereNotNull('patient_id')
            ->distinct()
            ->count('patient_id');

        // Total unique walk-in patients
        $walkInPatients = Appointment::where('doctor_id', $doctor->id)
            ->whereNotNull('walkin_patient_id')
            ->distinct()
            ->count('walkin_patient_id');

        $totalPatients = $registeredPatients + $walkInPatients;

        // Pending appointments
        $pendingAppointments = Appointment::where('doctor_id', $doctor->id)
            ->where('status', 'Pending')
            ->count();

        // Completed appointments
        $completedAppointments = Appointment::where('doctor_id', $doctor->id)
            ->where('status', 'Completed')
            ->count();

        // Recent appointments
        $recentAppointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->take(5)
            ->get();

        // Calendar appointments
        $appointments = Appointment::with(['patient', 'walkinPatient'])
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', [
                'Pending',
                'Approved',
                'Rescheduled',
                'Checked In',
                'In Progress',
                'Completed',
            ])
            ->whereDate(
                'appointment_date',
                '>=',
                Carbon::now()->subMonths(6)
            )
            ->get();

        return view('doctor.dashboard', compact(
            'doctor',
            'greeting',
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
     * Doctor Account Settings (profile + password + signature tabs)
     */
    public function accountSettings()
    {
        $doctor = Auth::user();

        $profileComplete      = $doctor->computeProfileComplete();
        $missingProfileFields = $doctor->getMissingProfileFields();

        return view('doctor.account-settings', compact(
            'doctor',
            'profileComplete',
            'missingProfileFields'
        ));
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
     *
     * Covers Personal Details, Contact Details, Professional Information,
     * and Account Details. Avatar is handled independently by
     * updateAvatar()/removeAvatar() below, mirroring the Patient page.
     */
    public function updateProfile(Request $request)
    {
        $doctor = Auth::user();

        $validated = $request->validate([
            // Personal Details
            'first_name'     => 'required|string|max:50',
            'middle_name'    => 'nullable|string|max:50',
            'last_name'      => 'required|string|max:50',
            'suffix'         => 'nullable|string|max:10',
            'birthdate'      => 'required|date|before:today',
            'gender'         => 'required|string|in:Male,Female,Other',
            'civil_status'   => 'required|string|in:Single,Married,Widowed,Separated',

            // Contact Details
            'address'        => 'required|string|max:100',
            'contact_number' => 'required|string|max:20',

            // Professional Information
            'specialization' => 'required|string|max:50',
            'license_number' => 'required|string|max:20',

            // Account Details
            'username'       => ['required', 'string', 'max:50', Rule::unique('users')->ignore($doctor->id)],
            'email'          => ['required', 'email', 'max:255', Rule::unique('users')->ignore($doctor->id)],
        ]);

        $doctor->update([
            'first_name'     => $validated['first_name'],
            'middle_name'    => $validated['middle_name'] ?? null,
            'last_name'      => $validated['last_name'],
            'suffix'         => $validated['suffix'] ?? null,
            'birthdate'      => $validated['birthdate'],
            'gender'         => $validated['gender'],
            'civil_status'   => $validated['civil_status'],

            'address'        => $validated['address'],
            'contact_number' => $validated['contact_number'],

            'specialization' => $validated['specialization'],
            'license_number' => $validated['license_number'],

            'username'       => $validated['username'],
            'email'          => $validated['email'],
        ]);

        // Redirect back to account settings, profile tab
        return redirect()->route('doctor.account-settings')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Profile Picture — Save (independent from the profile form)
     * PUT target: doctor.profile.avatar.update  →  PUT /doctor/account-settings/avatar
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $doctor = Auth::user();

        if ($doctor->avatar && Storage::disk('public')->exists($doctor->avatar)) {
            Storage::disk('public')->delete($doctor->avatar);
        }

        $doctor->avatar = $request->file('avatar')->store('avatars', 'public');
        $doctor->save();

        return redirect()->route('doctor.account-settings')
            ->with('success', 'Profile picture updated successfully!');
    }

    /**
     * Profile Picture — Remove (independent from the profile form)
     * DELETE target: doctor.profile.avatar.remove  →  DELETE /doctor/account-settings/avatar
     */
    public function removeAvatar()
    {
        $doctor = Auth::user();

        if ($doctor->avatar) {
            if (Storage::disk('public')->exists($doctor->avatar)) {
                Storage::disk('public')->delete($doctor->avatar);
            }

            $doctor->avatar = null;
            $doctor->save();
        }

        return redirect()->route('doctor.account-settings')
            ->with('success', 'Profile picture removed successfully!');
    }

    /**
     * Upload / Replace Doctor Digital Signature
     * POST target: doctor.signature.update  →  PUT /doctor/account-settings/signature
     */
    public function updateSignature(Request $request)
    {
        $request->validate([
            'signature' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $doctor = Auth::user();

        if ($doctor->signature && Storage::disk('public')->exists($doctor->signature)) {
            Storage::disk('public')->delete($doctor->signature);
        }

        $doctor->signature = $request->file('signature')->store('signatures', 'public');
        $doctor->save();

        return redirect()->route('doctor.account-settings')
            ->with('success', 'Digital signature uploaded successfully!');
    }

    /**
     * Remove Doctor Digital Signature
     * DELETE target: doctor.signature.remove  →  /doctor/account-settings/signature
     */
    public function removeSignature()
    {
        $doctor = Auth::user();

        if ($doctor->signature) {
            if (Storage::disk('public')->exists($doctor->signature)) {
                Storage::disk('public')->delete($doctor->signature);
            }

            $doctor->signature = null;
            $doctor->save();
        }

        return redirect()->route('doctor.account-settings')
            ->with('success', 'Digital signature removed.');
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

        if (!Hash::check($request->current_password, $doctor->password)) {
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