<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    // ================= DASHBOARD =================
    public function dashboard()
    {
        $today = Carbon::today();

        $totalAppointments     = Appointment::count();
        $pendingAppointments   = Appointment::where('status', 'Pending')->count();
        $approvedAppointments  = Appointment::where('status', 'Approved')->count();
        $completedAppointments = Appointment::where('status', 'Completed')->count();
        $cancelledAppointments = Appointment::where('status', 'Cancelled')->count();

        $todayAppointments = Appointment::with(['patient', 'doctor'])
            ->whereDate('appointment_date', $today)
            ->latest()
            ->get();

        $pendingList = Appointment::with(['patient', 'doctor'])
            ->where('status', 'Pending')
            ->latest()
            ->take(5)
            ->get();

        return view('staff.dashboard', compact(
            'totalAppointments',
            'pendingAppointments',
            'approvedAppointments',
            'completedAppointments',
            'cancelledAppointments',
            'todayAppointments',
            'pendingList'
        ));
    }

    // ================= ACCOUNT SETTINGS =================
    public function settings()
    {
        $staff = auth()->user();

        return view('staff.account-settings', compact('staff'));
    }

    // ================= PROFILE =================
    public function profile()
    {
        $staff = auth()->user();

        return view('staff.profile', compact('staff'));
    }

    // ================= CHANGE PASSWORD PAGE =================
    public function changePassword()
    {
        return view('staff.change-password');
    }

    // ================= UPDATE PROFILE =================
    public function updateProfile(Request $request)
    {
        $staff = auth()->user();

        $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'gender'         => 'nullable|string|max:50',
            'civil_status'   => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:500',
            'contact_number' => 'nullable|string|max:20',
            'username'       => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'avatar'         => 'nullable|image|max:2048',
        ]);

        $data = $request->only([
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'gender',
            'civil_status',
            'address',
            'contact_number',
            'username',
            'email'
        ]);

        // ================= HANDLE AVATAR UPLOAD =================
        if ($request->hasFile('avatar')) {

            // Delete old avatar
            if ($staff->avatar && Storage::disk('public')->exists($staff->avatar)) {
                Storage::disk('public')->delete($staff->avatar);
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');

            $data['avatar'] = $path;
        }

        $staff->update($data);

        return back()->with('success', 'Profile updated successfully!');
    }

    // ================= UPDATE PASSWORD =================
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:6|confirmed',
        ]);

        $staff = auth()->user();

        // Check current password
        if (!Hash::check($request->current_password, $staff->password)) {

            return back()->withErrors([
                'current_password' => 'Incorrect current password.'
            ]);
        }

        // Update password
        $staff->update([
            'password' => Hash::make($request->password)
        ]);

        return back()->with('success', 'Password updated successfully!');
    }
}