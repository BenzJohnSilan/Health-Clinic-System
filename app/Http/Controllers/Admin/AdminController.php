<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Medicine;
use App\Models\UserLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\AccountApproved;
use App\Mail\AccountRejected;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * ================= DASHBOARD =================
     */
    public function dashboard()
    {
        $today = Carbon::today();

        // ===== USER STATS =====
        $totalUsers = User::where('approval_status', 'Approved')
            ->where('status', 'Active')
            ->count();

        $totalDoctors = User::where('role', 'Doctor')
            ->where('approval_status', 'Approved')
            ->where('status', 'Active')
            ->count();

        $totalPatients = User::where('role', 'Patient')
            ->where('approval_status', 'Approved')
            ->where('status', 'Active')
            ->count();

        $totalPending = User::where('approval_status', 'Pending')
            ->where('role', '!=', 'Admin')
            ->count();

        // ===== APPOINTMENT STATS =====
        $todaysAppointments = Appointment::whereDate('appointment_date', $today)->count();

        $pendingAppointments = Appointment::where('status', 'Pending')->count();

        $approvedAppointments = Appointment::where('status', 'Approved')->count();

        $completedAppointments = Appointment::where('status', 'Completed')->count();

        $cancelledAppointments = Appointment::where('status', 'Cancelled')->count();

        // Today's appointment list (for table preview)
        $todaysAppointmentList = Appointment::with(['patient', 'doctor'])
            ->whereDate('appointment_date', $today)
            ->orderBy('appointment_time')
            ->take(5)
            ->get();

        // ===== MEDICINE / INVENTORY ALERTS =====
        $lowStockMedicines = Medicine::where('status', 'Low Stock')->count();

        $outOfStockMedicines = Medicine::where('status', 'Out of Stock')->count();

        // Expiring within 30 days
        $expiringMedicines = Medicine::whereBetween('expiration_date', [
            $today,
            $today->copy()->addDays(30),
        ])->count();

        // Expired already
        $expiredMedicines = Medicine::where('expiration_date', '<', $today)->count();

        // Medicine alert list (low stock + expiring soon)
        $medicineAlertList = Medicine::where(function ($q) use ($today) {
            $q->whereIn('status', ['Low Stock', 'Out of Stock'])
              ->orWhereBetween('expiration_date', [$today, $today->copy()->addDays(30)])
              ->orWhere('expiration_date', '<', $today);
        })
        ->orderBy('expiration_date')
        ->take(5)
        ->get();

        return view('admin.dashboard', compact(
            // User stats
            'totalUsers',
            'totalDoctors',
            'totalPatients',
            'totalPending',
            // Appointment stats
            'todaysAppointments',
            'pendingAppointments',
            'approvedAppointments',
            'completedAppointments',
            'cancelledAppointments',
            'todaysAppointmentList',
            // Medicine alerts
            'lowStockMedicines',
            'outOfStockMedicines',
            'expiringMedicines',
            'expiredMedicines',
            'medicineAlertList'
        ));
    }

    /**
     * ================= PATIENT LIST =================
     */
    public function patients(Request $request)
    {
        $query = User::where('role', 'Patient')
            ->where('approval_status', 'Approved')
            ->where('status', 'Active');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('username', 'like', '%' . $request->search . '%');
            });
        }

        $patients = $query->latest()->paginate(10);

        return view('admin.patients.index', compact('patients'));
    }

    /**
     * ================= PROFILE =================
     */
    public function profile()
    {
        $admin = Auth::user();
        return view('admin.profile', compact('admin'));
    }

    public function updateProfile(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'first_name'      => 'required|string|max:255',
            'middle_name'     => 'nullable|string|max:255',
            'last_name'       => 'required|string|max:255',
            'suffix'          => 'nullable|string|max:255',
            'gender'          => 'required|in:Male,Female,Other',
            'civil_status'    => 'required|in:Single,Married,Widowed,Separated',
            'address'         => 'required|string|max:1000',
            'contact_number'  => 'required|string|max:20',
            'username'        => 'required|string|max:255|unique:users,username,' . $admin->id,
            'email'           => 'required|email|max:255|unique:users,email,' . $admin->id,
            'avatar'          => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $admin->fill($request->only([
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
        ]));

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Profile',
            'details' => 'Admin updated profile'
        ]);

        if ($request->hasFile('avatar')) {
            if ($admin->avatar && Storage::disk('public')->exists($admin->avatar)) {
                Storage::disk('public')->delete($admin->avatar);
            }
            $admin->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $admin->save();

        return redirect()->route('admin.profile')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * ================= REMOVE AVATAR =================
     */
    public function removeAvatar()
    {
        $admin = Auth::user();

        if ($admin->avatar && Storage::disk('public')->exists($admin->avatar)) {
            Storage::disk('public')->delete($admin->avatar);
        }

        $admin->avatar = null;
        $admin->save();

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Removed Avatar',
            'details' => 'Admin removed profile picture'
        ]);

        return back()->with('success', 'Profile picture removed successfully.');
    }

    /**
     * ================= PASSWORD =================
     */
    public function changePassword()
    {
        return view('admin.change-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'min:6'],
        ]);

        $admin = Auth::user();
        $admin->password = Hash::make($request->password);
        $admin->save();

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Changed Password',
            'details' => 'Admin changed password'
        ]);


        return back()->with('success', 'Password updated successfully.');
    }

    /**
     * ================= CREATE USER =================
     */
    public function createUser()
    {
        return view('admin.create-user');
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'username'   => 'required|string|unique:users',
            'email'      => 'required|email|unique:users',
            'password'   => 'required|confirmed|min:6',
            'role'       => 'required|in:Doctor,Patient',
        ]);

        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'username'        => $request->username,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'role'            => $request->role,
            'status'          => 'Active',
            'approval_status' => 'Approved',
        ]);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Created User',
            'details' => $user->role . ' - ' . $user->username
        ]);

        Mail::to($user->email)->send(new AccountApproved($user));

        return redirect()->route('admin.dashboard')
            ->with('success', $request->role . ' account created successfully.');
    }

    /**
     * ================= PENDING ACCOUNTS =================
     */
    public function pendingAccounts()
    {
        $pendingUsers = User::where('approval_status', 'Pending')
            ->where('role', '!=', 'Admin')
            ->latest()
            ->get();

        return view('admin.pending-accounts', compact('pendingUsers'));
    }

    public function approveUser($id)
    {
        $user = User::findOrFail($id);

        $user->approval_status = 'Approved';
        $user->status = 'Active';
        $user->save();

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Approved User',
            'details' => 'User ID: ' . $user->id
        ]);

        Mail::to($user->email)->send(new AccountApproved($user));

        return back()->with('success', 'User approved successfully.');
    }

    /**
     * ================= REJECT USER =================
     */
    public function rejectUser(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $user = User::findOrFail($id);

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Rejected User',
            'details' => 'User ID: ' . $user->id . ' | Reason: ' . $request->reason
        ]);

        Mail::to($user->email)->send(new AccountRejected($user, $request->reason));

        $user->delete();

        return back()->with('success', 'User rejected and deleted successfully.');
    }
}