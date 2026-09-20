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
    // =========================================================
    //  LIST  –  only approved users
    //  Server-side search (name/username/email) + Role/Status
    //  filters, combined with pagination — mirrors the pattern used
    //  by Admin/StaffAppointmentController@index.
    // =========================================================
    public function index(Request $request)
    {
        $query = User::where('approval_status', 'Approved');

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
    //  STORE  –  role-based validation + conditional approval
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
            'role'           => 'required|in:Admin,Doctor,Staff,Patient',
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
                'position'    => 'required|string|max:50',
            ],
            'Patient' => [
                'blood_type'               => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                'allergies'                => 'nullable|string|max:255',
                'id_type'                  => 'nullable|string|max:50',
                'valid_id'                 => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
                'reason'                   => 'nullable|in:Check-up / Consultation,Appointment Booking,Medical Record Access,Others',
                'emergency_name'           => 'nullable|string|max:100',
                'relationship'             => 'nullable|string|max:50',
                'emergency_contact_number' => 'nullable|digits:11',
                'emergency_address'        => 'nullable|string|max:100',
            ],
            default => [],
        };

        $request->validate(array_merge($rules, $roleRules));

        // --- 3. Handle file uploads ---
        $avatarPath  = null;
        $validIdPath = null;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('valid_id')) {
            $validIdPath = $request->file('valid_id')->store('valid_ids', 'public');
        }

        // --- 4. Auto-generated password ---
        $plainPassword = Str::random(10);

        // --- 5. Conditional approval (Patients wait for review) ---
        $approvalStatus = ($role === 'Patient') ? 'Pending' : 'Approved';

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
            'Patient' => [
                'blood_type'               => $request->blood_type,
                'allergies'                => $request->allergies,
                'id_type'                  => $request->id_type,
                'valid_id'                 => $validIdPath,
                'reason'                   => $request->reason,
                'emergency_name'           => $request->emergency_name,
                'relationship'             => $request->relationship,
                'emergency_contact_number' => $request->emergency_contact_number,
                'emergency_address'        => $request->emergency_address,
            ],
            default => [],
        });

        // --- 8. Create user ---
        $user = User::create($data);

        // --- 8b. Default feature permissions (Doctor gets none; Staff
        // gets the default Medicine CRUD set). Admin can adjust these
        // afterwards from Manage Permissions. Patient is never
        // considered here — Permission::defaultSlugsForRole() returns
        // nothing for it.
        \App\Models\Permission::assignDefaultsTo($user);

        // --- 9. Send email verification & credentials ---
        event(new Registered($user));
        Mail::to($user->email)->send(new AccountCreated($user, $plainPassword));

        $message = $approvalStatus === 'Pending'
            ? 'Patient account created and is pending approval.'
            : "User ({$role}) created successfully. Login credentials sent to email.";

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
    //  DESTROY  –  Admin accounts are protected from deletion
    //  Ang cascade delete ay hawak ng User model boot() event
    // =========================================================
    public function destroy(User $user)
    {
        if ($user->role === 'Admin') {
            return redirect()->route('admin.users.index')
                             ->with('error', 'Admin accounts cannot be deleted.');
        }

        // $user->delete() na ang mag-trigger ng cascade
        // sa boot() ng User model — lahat ng appointments
        // at related records ay matatanggal din
        $user->delete();

        return redirect()->route('admin.users.index')
                         ->with('success', 'User and all related records deleted successfully.');
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