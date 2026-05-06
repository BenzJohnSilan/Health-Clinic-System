<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Review;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class PatientController extends Controller
{
    public function dashboard()
    {
        $patient = auth()->user();

        $totalAppointments = Appointment::where('patient_id', $patient->id)->count();

        $upcomingAppointments = Appointment::with('doctor')
            ->where('patient_id', $patient->id)
            ->where('status', 'Approved')
            ->orderBy('appointment_date', 'asc')
            ->orderBy('appointment_time', 'asc')
            ->get();

        $doctors = User::where('role', 'Doctor')->get();

        $bookedSlots = Appointment::where('status', '!=', 'Rejected')
            ->get(['doctor_id', 'appointment_date', 'appointment_time']);

        return view('patient.dashboard', compact(
            'patient',
            'totalAppointments',
            'upcomingAppointments',
            'doctors',
            'bookedSlots'
        ));
    }

    public function profile()
    {
        $patient = auth()->user();
        return view('patient.profile', compact('patient'));
    }

    public function updateProfile(Request $request)
    {
        $patient = auth()->user();

        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'gender'         => 'required|string|in:Male,Female,Other',
            'civil_status'   => 'required|string|in:Single,Married,Widowed,Separated',
            'address'        => 'required|string|max:500',
            'contact_number' => 'required|string|max:20',
            'username'       => ['required', 'string', 'max:255', Rule::unique('users')->ignore($patient->id)],
            'email'          => ['required', 'email', Rule::unique('users')->ignore($patient->id)],
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $patient->update([
            'first_name'     => $validated['first_name'],
            'middle_name'    => $validated['middle_name'] ?? null,
            'last_name'      => $validated['last_name'],
            'suffix'         => $validated['suffix'] ?? null,
            'gender'         => $validated['gender'],
            'civil_status'   => $validated['civil_status'],
            'address'        => $validated['address'],
            'contact_number' => $validated['contact_number'],
            'username'       => $validated['username'],
            'email'          => $validated['email'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($patient->avatar && Storage::disk('public')->exists($patient->avatar)) {
                Storage::disk('public')->delete($patient->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $patient->avatar = $path;
            $patient->save();
        }

        return redirect()->route('patient.profile')
            ->with('success', 'Profile updated successfully!');
    }

    public function changePassword()
    {
        return view('patient.change-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|confirmed|min:6',
        ]);

        $patient = auth()->user();

        if (!Hash::check($request->current_password, $patient->password)) {
            return back()->withErrors([
                'current_password' => 'Current password does not match.'
            ]);
        }

        $patient->password = Hash::make($request->password);
        $patient->save();

        return redirect()->route('patient.change-password')
            ->with('success', 'Password updated successfully.');
    }

    /**
     * Medical History (LIST)
     */
    public function medicalReport()
    {
        $patient = auth()->user();

        $appointments = Appointment::with([
                'doctor',
                'prescriptions.medicine',
                'review'
            ])
            ->where('patient_id', $patient->id)
            ->orderBy('appointment_date', 'desc')
            ->get();

        return view('patient.medical-report', compact('appointments'));
    }

    /**
     * Medical Report (SINGLE VIEW)
     */
    public function showMedicalReport($id)
    {
        $appointment = Appointment::with([
                'doctor',
                'prescriptions.medicine',
                'review'
            ])
            ->where('id', $id)
            ->where('patient_id', auth()->id()) // security
            ->firstOrFail();

        return view('patient.view-medical-report', compact('appointment'));
    }
}