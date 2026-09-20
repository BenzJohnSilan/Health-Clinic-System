<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\UserLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StaffController extends Controller
{
    // ================= DASHBOARD =================
    public function dashboard()
    {
        $staff = auth()->user();
        $today = Carbon::today();

        $totalAppointments     = Appointment::count();
        $pendingAppointments   = Appointment::where('status', 'Pending')->count();
        $approvedAppointments  = Appointment::where('status', 'Approved')->count();
        $completedAppointments = Appointment::where('status', 'Completed')->count();
        $cancelledAppointments = Appointment::where('status', 'Cancelled')->count();

        // Today's appointments, earliest first — only the next 5 are shown
        // on the dashboard card so it stays compact; the total is fetched
        // separately (count only) so the "Showing X of Y" text underneath
        // stays accurate even though the display list itself is limited.
        $totalTodayAppointments = Appointment::whereDate('appointment_date', $today)->count();

        $todayAppointments = Appointment::with(['patient', 'walkinPatient', 'doctor'])
            ->whereDate('appointment_date', $today)
            ->orderBy('appointment_time', 'asc')
            ->take(5)
            ->get();

        // Same greeting logic/time bands as the Patient Dashboard
        // (PatientController::dashboard), kept in sync intentionally.
        $hour = Carbon::now()->hour;
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good Morning';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Good Afternoon';
        } else {
            $greeting = 'Good Evening';
        }

        return view('staff.dashboard', compact(
            'staff',
            'greeting',
            'totalAppointments',
            'pendingAppointments',
            'approvedAppointments',
            'completedAppointments',
            'cancelledAppointments',
            'todayAppointments',
            'totalTodayAppointments'
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

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Profile',
            'module'  => 'Account',
            'details' => 'Updated account profile information.',
        ]);

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

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Changed Password',
            'module'  => 'Account',
            'details' => 'Changed account password.',
        ]);

        return back()->with('success', 'Password updated successfully!');
    }
}