<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\AccountApproved;
use App\Mail\AccountRejected;
use App\Services\AdminDashboardService;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * ================= DASHBOARD =================
     *
     * All metrics/lists are built in AdminDashboardService (read-only
     * queries over the existing appointment, patient, medicine, billing
     * and activity-log data). The controller only adds the greeting and
     * hands everything to the view.
     */
    public function dashboard(AdminDashboardService $dashboard)
    {
        // Same greeting time bands as the Staff/Patient dashboards.
        $hour = Carbon::now()->hour;
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good Morning';
        } elseif ($hour >= 12 && $hour < 18) {
            $greeting = 'Good Afternoon';
        } else {
            $greeting = 'Good Evening';
        }

        return view('admin.dashboard', array_merge(
            [
                'adminUser' => Auth::user(),
                'greeting'  => $greeting,
            ],
            $dashboard->build()
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
     * ================= ACCOUNT SETTINGS =================
     */
    public function settings()
    {
        $admin = Auth::user();
        return view('admin.account-settings', compact('admin'));
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
            'module'  => 'Account',
            'details' => 'Admin updated profile'
        ]);

        if ($request->hasFile('avatar')) {
            if ($admin->avatar && Storage::disk('public')->exists($admin->avatar)) {
                Storage::disk('public')->delete($admin->avatar);
            }
            $admin->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $admin->save();

        return redirect()->route('admin.settings')
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
            'module'  => 'Account',
            'details' => 'Admin removed profile picture'
        ]);

        return back()->with('success', 'Profile picture removed successfully.');
    }

    /**
     * ================= UPDATE AVATAR (independent from profile form) =================
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $admin = Auth::user();

        if ($admin->avatar && Storage::disk('public')->exists($admin->avatar)) {
            Storage::disk('public')->delete($admin->avatar);
        }

        $admin->avatar = $request->file('avatar')->store('avatars', 'public');
        $admin->save();

        UserLog::create([
            'user_id' => auth()->id(),
            'action'  => 'Updated Profile Picture',
            'module'  => 'Account',
            'details' => 'Admin updated profile picture'
        ]);

        return back()->with('success', 'Profile picture updated successfully.');
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
            'module'  => 'Account',
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
            'module'  => 'Account',
            'details' => $user->role . ' - ' . $user->username
        ]);

        Mail::to($user->email)->send(new AccountApproved($user));

        return redirect()->route('admin.dashboard')
            ->with('success', $request->role . ' account created successfully.');
    }

    /**
     * ================= PENDING ACCOUNTS =================
     */
    public function pendingAccounts(Request $request)
    {
        $pendingUsers = User::where('approval_status', 'Pending')
            ->where('role', '!=', 'Admin')
            ->latest()
            ->orderByDesc('id') // stable order so a row never jumps between pages
            ->paginate(10)
            ->withQueryString();

        // After approving/rejecting the last account on the last page, the
        // redirect-back lands on a page that no longer exists. Send the
        // admin to the new last page instead of showing an empty table.
        if ($pendingUsers->isEmpty() && $pendingUsers->currentPage() > 1) {
            return redirect()->route('admin.pending', ['page' => $pendingUsers->lastPage()]);
        }

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
            'module'  => 'Account',
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
            'module'  => 'Account',
            'details' => 'User ID: ' . $user->id . ' | Reason: ' . $request->reason
        ]);

        Mail::to($user->email)->send(new AccountRejected($user, $request->reason));

        // A rejected registration is deleted, so its Valid ID must not be
        // left behind in storage. Check both the private disk and the
        // pre-migration public location.
        $validId = $user->valid_id;

        $user->delete();

        if (is_string($validId) && preg_match('#^valid_ids/[A-Za-z0-9._-]+$#', $validId) === 1) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($validId);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($validId);
        }

        return back()->with('success', 'User rejected and deleted successfully.');
    }
}