<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use App\Mail\AccountCreated;
use App\Mail\AccountApproved;
use App\Mail\AccountRejected;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * List all approved users
     */
    public function index()
    {
        $users = User::where('approval_status', 'Approved')
                     ->orderBy('role', 'asc')
                     ->orderBy('first_name', 'asc')
                     ->get();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Store a new user (AUTO PASSWORD + CONDITIONAL APPROVAL)
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'birthdate'      => 'required|date|before:today',
            'gender'         => 'required|in:Male,Female,Other',
            'civil_status'   => 'required|in:Single,Married,Widowed,Separated',
            'address'        => 'required|string|max:255',
            'contact_number' => 'required|digits:11|unique:users',
            'username'       => 'required|string|max:255|unique:users',
            'email'          => 'required|email|max:255|unique:users',
            'role'           => 'required|in:Admin,Doctor,Patient',
            'status'         => 'required|in:Active,Inactive',
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Generate random password
        $plainPassword = Str::random(8);

        // Upload avatar
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // Conditional approval
        $approvalStatus = ($request->role === 'Patient') ? 'Pending' : 'Approved';

        // Create user
        $user = User::create([
            'avatar'         => $avatarPath,
            'first_name'     => $request->first_name,
            'middle_name'    => $request->middle_name,
            'last_name'      => $request->last_name,
            'suffix'         => $request->suffix,
            'birthdate'      => $request->birthdate,
            'gender'         => $request->gender,
            'civil_status'   => $request->civil_status,
            'address'        => $request->address,
            'contact_number' => $request->contact_number,
            'username'       => $request->username,
            'email'          => $request->email,
            'password'       => Hash::make($plainPassword),
            'role'           => $request->role,
            'status'         => $request->status,
            'approval_status'=> $approvalStatus,
        ]);

        // Send email verification
        event(new Registered($user));

        // Send credentials email
        Mail::to($user->email)->send(new AccountCreated($user, $plainPassword));

        return redirect()
            ->route('admin.users.index')
            ->with('success',
                $approvalStatus === 'Pending'
                ? 'Patient created. Waiting for approval.'
                : 'User created. Credentials sent to email.'
            );
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'first_name'     => 'required|string|max:255',
            'middle_name'    => 'nullable|string|max:255',
            'last_name'      => 'required|string|max:255',
            'suffix'         => 'nullable|string|max:50',
            'birthdate'      => 'required|date|before:today',
            'gender'         => ['required', Rule::in(['Male', 'Female', 'Other'])],
            'civil_status'   => ['required', Rule::in(['Single', 'Married', 'Widowed', 'Separated'])],
            'address'        => 'required|string|max:255',
            'contact_number' => ['required','digits:11', Rule::unique('users')->ignore($user->id)],
            'username'       => ['required','string','max:255', Rule::unique('users')->ignore($user->id)],
            'email'          => ['required','email','max:255', Rule::unique('users')->ignore($user->id)],
            'role'           => ['required', Rule::in(['Admin','Doctor','Patient'])],
            'status'         => ['required', Rule::in(['Active','Inactive'])],
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Update avatar if uploaded
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }

        // Update all fields
        $user->update([
            'first_name'     => $request->first_name,
            'middle_name'    => $request->middle_name,
            'last_name'      => $request->last_name,
            'suffix'         => $request->suffix,
            'birthdate'      => $request->birthdate,
            'gender'         => $request->gender,
            'civil_status'   => $request->civil_status,
            'address'        => $request->address,
            'contact_number' => $request->contact_number,
            'username'       => $request->username,
            'email'          => $request->email,
            'role'           => $request->role,
            'status'         => $request->status,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Approve user
     */
    public function approveUser($id)
    {
        $user = User::findOrFail($id);

        $user->approval_status = 'Approved';
        $user->status = 'Active';
        $user->save();

        Mail::to($user->email)->send(new AccountApproved($user));

        return back()->with('success', 'User approved successfully.');
    }

    /**
     * Reject user
     */
    public function rejectUser($id)
    {
        $user = User::findOrFail($id);

        Mail::to($user->email)->send(new AccountRejected($user));

        $user->delete();

        return back()->with('success', 'User rejected and deleted successfully.');
    }
}