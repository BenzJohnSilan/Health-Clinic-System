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
use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveService;

class UserController extends Controller
{
    // =========================================================
    //  LIST  –  only approved users
    //  Server-side search (name/username/email) + Role/Status
    //  filters, combined with pagination — mirrors the pattern used
    //  by Admin/StaffAppointmentController@index.
    // =========================================================
    public function index(Request $request)
    {
        // Archived accounts are listed in Admin > Archive instead.
        $query = User::where('approval_status', 'Approved')->notArchived();

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $users = $query->orderBy('role')
                        ->orderBy('first_name')
                        ->paginate(15)
                        ->appends($request->query());

        return view('admin.users.index', compact('users'));
    }

    // =========================================================
    //  STORE  –  role-based validation
    //  Admin can only create Admin / Doctor / Staff accounts.
    //  Patients register themselves through the public registration page.
    // =========================================================
    public function store(Request $request)
    {
        $role = $request->input('role');

        // --- 1. Shared rules (every role) ---
        $rules = [
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'first_name'     => 'required|string|max:50',
            'middle_name'    => 'nullable|string|max:50',
            'last_name'      => 'required|string|max:50',
            'suffix'         => 'nullable|string|max:10',
            'birthdate'      => 'required|date|before:today',
            'gender'         => 'required|in:Male,Female,Other',
            'civil_status'   => 'required|in:Single,Married,Widowed,Separated',
            'address'        => 'required|string|max:100',
            'contact_number' => 'required|digits:11|unique:users,contact_number',
            'username'       => 'required|string|max:50|unique:users,username',
            'email'          => 'required|email|max:255|unique:users,email',
            'role'           => 'required|in:Admin,Doctor,Staff',
            'status'         => 'required|in:Active,Inactive',
        ];

        // --- 2. Role-specific rules ---
        $roleRules = match ($role) {
            'Doctor'  => [
                'specialization' => 'required|string|max:50',
                'license_number' => 'required|string|max:20',
            ],
            'Staff'   => [
                'employee_id' => 'required|string|max:20|unique:users,employee_id',
                'position'    => ['required', Rule::in(['Nurse', 'Cashier'])],
                // Optional starting permission set (e.g. Cashier). The
                // Position text above is only a label — it never grants
                // access; the permissions assigned below do.
                'permission_preset' => ['nullable', Rule::in(array_keys(\App\Models\Permission::presets()))],
            ],
            default => [],
        };

        $request->validate(array_merge($rules, $roleRules));

        // --- 3. Handle file uploads ---
        $avatarPath = null;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // --- 4. Auto-generated password ---
        $plainPassword = Str::random(10);

        // --- 5. Admin-created accounts are approved right away ---
        $approvalStatus = 'Approved';

        // --- 6. Build data array (shared fields) ---
        $data = [
            'avatar'          => $avatarPath,
            'first_name'      => $request->first_name,
            'middle_name'     => $request->middle_name,
            'last_name'       => $request->last_name,
            'suffix'          => $request->suffix,
            'birthdate'       => $request->birthdate,
            'gender'          => $request->gender,
            'civil_status'    => $request->civil_status,
            'address'         => $request->address,
            'contact_number'  => $request->contact_number,
            'username'        => $request->username,
            'email'           => $request->email,
            'password'        => Hash::make($plainPassword),
            'role'            => $role,
            'status'          => $request->status,
            'approval_status' => $approvalStatus,
        ];

        // --- 7. Merge role-specific data ---
        $data = array_merge($data, match ($role) {
            'Doctor'  => [
                'specialization' => $request->specialization,
                'license_number' => $request->license_number,
            ],
            'Staff'   => [
                'employee_id' => $request->employee_id,
                'position'    => $request->position,
            ],
            default => [],
        });

        // --- 8. Create user ---
        $user = User::create($data);

        // --- 8b. Default feature permissions. Staff gets the standard
        // default set, or the preset Admin picked on the form (e.g.
        // 'cashier' = billing permissions only). Admin can adjust these
        // afterwards from Manage Permissions. The preset is only read for
        // Staff — Permission::assignDefaultsTo() ignores it for every
        // other role.
        \App\Models\Permission::assignDefaultsTo(
            $user,
            $role === 'Staff' ? $request->input('permission_preset') : null
        );

        // --- 9. Send email verification & credentials ---
        event(new Registered($user));
        Mail::to($user->email)->send(new AccountCreated($user, $plainPassword));

        $message = "User ({$role}) created successfully. Login credentials sent to email.";

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    // =========================================================
    //  UPDATE  –  basic fields only (extend as needed per role)
    // =========================================================
    public function update(Request $request, User $user)
    {
        $request->validate([
            'first_name'     => 'required|string|max:50',
            'middle_name'    => 'nullable|string|max:50',
            'last_name'      => 'required|string|max:50',
            'suffix'         => 'nullable|string|max:10',
            'birthdate'      => 'required|date|before:today',
            'gender'         => ['required', Rule::in(['Male','Female','Other'])],
            'civil_status'   => ['required', Rule::in(['Single','Married','Widowed','Separated'])],
            'address'        => 'required|string|max:100',
            'contact_number' => ['required','digits:11', Rule::unique('users')->ignore($user->id)],
            'username'       => ['required','string','max:50', Rule::unique('users')->ignore($user->id)],
            'email'          => ['required','email','max:255', Rule::unique('users')->ignore($user->id)],
            'role'           => ['required', Rule::in(['Admin','Doctor','Staff','Patient'])],
            'status'         => ['required', Rule::in(['Active','Inactive'])],
            'avatar'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

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

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    // =========================================================
    //  DESTROY  –  now ARCHIVES the account (deactivates it).
    //  Nothing is deleted: appointments, medical records and billing
    //  stay intact. Restore or permanently delete it from Admin > Archive
    //  (permanent delete is refused while any history is linked).
    // =========================================================
    public function destroy(User $user, ArchiveService $archive)
    {
        if ($user->role === 'Admin') {
            return redirect()->route('admin.users.index')
                             ->with('error', 'Admin accounts cannot be archived or deleted.');
        }

        try {
            $archive->archive('user', $user->id, auth()->user());
        } catch (ArchiveException $e) {
            return redirect()->route('admin.users.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.users.index')
                         ->with('success', $user->full_name . ' was moved to the Archive and can no longer log in. All records were kept.');
    }

    // =========================================================
    //  APPROVE
    // =========================================================
    public function approveUser($id)
    {
        $user = User::findOrFail($id);
        $user->approval_status = 'Approved';
        $user->status          = 'Active';
        $user->save();

        Mail::to($user->email)->send(new AccountApproved($user));

        return back()->with('success', 'User approved successfully.');
    }

    // =========================================================
    //  REJECT  –  email muna bago i-delete para ma-trigger
    //  ang cascade delete ng User model
    // =========================================================
    public function rejectUser($id)
    {
        $user = User::findOrFail($id);

        // Siguraduhing napadala ang email bago matanggal ang user
        Mail::to($user->email)->send(new AccountRejected($user));

        // Ang delete() ay mag-ti-trigger ng boot() cascade
        $user->delete();

        return back()->with('success', 'User rejected and removed.');
    }
}