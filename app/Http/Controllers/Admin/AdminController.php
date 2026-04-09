<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountApproved;
use App\Mail\AccountRejected;

class AdminController extends Controller
{
    /**
     * ================= DASHBOARD =================
     */
    public function dashboard()
    {
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

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalDoctors',
            'totalPatients',
            'totalPending'
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

        // Update all fields
        $admin->first_name     = $request->first_name;
        $admin->middle_name    = $request->middle_name;
        $admin->last_name      = $request->last_name;
        $admin->suffix         = $request->suffix;
        $admin->gender         = $request->gender;
        $admin->civil_status   = $request->civil_status;
        $admin->address        = $request->address;
        $admin->contact_number = $request->contact_number;
        $admin->username       = $request->username;
        $admin->email          = $request->email;

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $admin->avatar = $path;
        }

        $admin->save();

        return redirect()->route('admin.profile')
                         ->with('success', 'Profile updated successfully.');
    }

    /**
     * ================= CHANGE PASSWORD =================
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

        return redirect()->route('admin.change-password')
                         ->with('success', 'Password updated successfully.');
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
        $user->status          = 'Active';
        $user->save();

        Mail::to($user->email)->send(new AccountApproved($user));

        return back()->with('success', 'User approved successfully.');
    }

    /**
     * ================= REJECT USER =================
     */
    public function rejectUser(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:1000', // reason for rejection
        ]);

        $user = User::findOrFail($id);

        // Send rejection email first
        Mail::to($user->email)->send(new AccountRejected($user, $request->reason));

        // Delete user from database so they can register again
        $user->delete();

        return back()->with('success', 'User rejected and deleted successfully.');
    }
}