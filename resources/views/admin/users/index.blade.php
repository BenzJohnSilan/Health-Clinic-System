@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-users.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1>Manage Users</h1>
        <button id="openAddModal" class="btn-primary">+ Add User</button>
    </div>

    <!-- ================= SEARCH & FILTER ================= -->
    <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="Search user...">

        <select id="roleFilter">
            <option value="">All Roles</option>
            <option value="Admin">Admin</option>
            <option value="Doctor">Doctor</option>
            <option value="Patient">Patient</option>
        </select>

        <select id="statusFilter">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>
    </div>

    <!-- ================= SUCCESS MESSAGE ================= -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- ================= ADD USER MODAL ================= -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <button id="closeAddModal" class="modal-close">&times;</button>

            <h3>Create New User</h3>

            @if ($errors->any())
                <div class="alert-error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <label>Avatar</label>
                <input type="file" name="avatar">

                <label>First Name</label>
                <input type="text" name="first_name" required>

                <label>Middle Name</label>
                <input type="text" name="middle_name">

                <label>Last Name</label>
                <input type="text" name="last_name" required>

                <label>Suffix</label>
                <input type="text" name="suffix">

                <label>Birthday</label>
                <input type="date" name="birthdate" max="{{ date('Y-m-d') }}" required>

                <label>Gender</label>
                <select name="gender" required>
                    <option value="">-- Select --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>

                <label>Civil Status</label>
                <select name="civil_status" required>
                    <option value="">-- Select --</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                </select>

                <label>Address</label>
                <input type="text" name="address" required>

                <label>Contact Number</label>
                <input type="text" name="contact_number" required maxlength="11"
                       pattern="\d{11}" oninput="this.value=this.value.replace(/[^0-9]/g,'')">

                <label>Username</label>
                <input type="text" name="username" required>

                <label>Email</label>
                <input type="email" name="email" required>

                <label>Role</label>
                <select name="role" required>
                    <option value="Admin">Admin</option>
                    <option value="Doctor">Doctor</option>
                    <option value="Patient">Patient</option>
                </select>

                <label>Status</label>
                <select name="status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>

                <button type="submit" class="btn-primary">Create User</button>
            </form>
        </div>
    </div>

    <!-- ================= EDIT USER MODAL ================= -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <button id="closeEditModal" class="modal-close">&times;</button>

            <h3>Edit User</h3>

            <form id="editUserForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <label>Avatar</label>
                <input type="file" name="avatar">

                <label>First Name</label>
                <input type="text" id="edit_first_name" name="first_name" required>

                <label>Middle Name</label>
                <input type="text" id="edit_middle_name" name="middle_name">

                <label>Last Name</label>
                <input type="text" id="edit_last_name" name="last_name" required>

                <label>Suffix</label>
                <input type="text" id="edit_suffix" name="suffix">

                <label>Birthday</label>
                <input type="date" id="edit_birthdate" name="birthdate" required>

                <label>Gender</label>
                <select id="edit_gender" name="gender" required>
                    <option value="">-- Select --</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>

                <label>Civil Status</label>
                <select id="edit_civil_status" name="civil_status" required>
                    <option value="">-- Select --</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Separated">Separated</option>
                </select>

                <label>Address</label>
                <input type="text" id="edit_address" name="address" required>

                <label>Contact Number</label>
                <input type="text" id="edit_contact_number" name="contact_number" required maxlength="11"
                       pattern="\d{11}" oninput="this.value=this.value.replace(/[^0-9]/g,'')">

                <label>Username</label>
                <input type="text" id="edit_username" name="username" required>

                <label>Email</label>
                <input type="email" id="edit_email" name="email" required>

                <label>Role</label>
                <select id="edit_role" name="role" required>
                    <option value="Admin">Admin</option>
                    <option value="Doctor">Doctor</option>
                    <option value="Patient">Patient</option>
                </select>

                <label>Status</label>
                <select id="edit_status" name="status" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>

                <button type="submit" class="btn-primary">Update User</button>
            </form>
        </div>
    </div>

    <!-- ================= USERS TABLE ================= -->
    <table class="users-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Email Verified</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($users as $user)
            <tr class="user-row"
                data-name="{{ strtolower($user->first_name . ' ' . $user->last_name) }}"
                data-username="{{ strtolower($user->username) }}"
                data-email="{{ strtolower($user->email) }}"
                data-role="{{ $user->role }}"
                data-status="{{ $user->status }}"
            >
                <td>{{ $user->id }}</td>
                <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                <td>{{ $user->username }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    @if($user->email_verified_at)
                        <span style="color:green;">Verified</span>
                    @else
                        <span style="color:red;">Unverified</span>
                    @endif
                </td>
                <td>{{ $user->role }}</td>
                <td>{{ $user->status }}</td>
                <td>
                    <button type="button" class="editBtn"
                        data-id="{{ $user->id }}"
                        data-first="{{ $user->first_name }}"
                        data-middle="{{ $user->middle_name }}"
                        data-last="{{ $user->last_name }}"
                        data-suffix="{{ $user->suffix }}"
                        data-birthdate="{{ \Carbon\Carbon::parse($user->birthdate)->format('Y-m-d') }}"
                        data-gender="{{ $user->gender }}"
                        data-civil="{{ $user->civil_status }}"
                        data-address="{{ $user->address }}"
                        data-contact="{{ $user->contact_number }}"
                        data-username="{{ $user->username }}"
                        data-email="{{ $user->email }}"
                        data-role="{{ $user->role }}"
                        data-status="{{ $user->status }}">
                        Edit
                    </button>

                    <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button onclick="return confirm('Delete user?')">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

</div>

<!-- ================= JAVASCRIPT ================= -->
<script>
const addModal  = document.getElementById('addUserModal');
const editModal = document.getElementById('editUserModal');

document.getElementById('openAddModal').onclick  = () => addModal.style.display = 'flex';
document.getElementById('closeAddModal').onclick = () => addModal.style.display = 'none';
document.getElementById('closeEditModal').onclick = () => editModal.style.display = 'none';

/* ================= EDIT ================= */
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.onclick = () => {
        document.getElementById('edit_first_name').value = btn.dataset.first;
        document.getElementById('edit_middle_name').value = btn.dataset.middle;
        document.getElementById('edit_last_name').value = btn.dataset.last;
        document.getElementById('edit_suffix').value = btn.dataset.suffix;
        document.getElementById('edit_birthdate').value = btn.dataset.birthdate;
        document.getElementById('edit_gender').value = btn.dataset.gender;
        document.getElementById('edit_civil_status').value = btn.dataset.civil;
        document.getElementById('edit_address').value = btn.dataset.address;
        document.getElementById('edit_contact_number').value = btn.dataset.contact;
        document.getElementById('edit_username').value = btn.dataset.username;
        document.getElementById('edit_email').value = btn.dataset.email;
        document.getElementById('edit_role').value = btn.dataset.role;
        document.getElementById('edit_status').value = btn.dataset.status;

        document.getElementById('editUserForm').action = `/admin/users/${btn.dataset.id}`;
        editModal.style.display = 'flex';
    };
});

/* ================= SEARCH + FILTER ================= */
const searchInput = document.getElementById('searchInput');
const roleFilter  = document.getElementById('roleFilter');
const statusFilter = document.getElementById('statusFilter');

const rows = document.querySelectorAll('.user-row');

function filterUsers() {
    const search = searchInput.value.toLowerCase();
    const role   = roleFilter.value;
    const status = statusFilter.value;

    rows.forEach(row => {
        const name = row.dataset.name;
        const username = row.dataset.username;
        const email = row.dataset.email;
        const userRole = row.dataset.role;
        const userStatus = row.dataset.status;

        let matchSearch = name.includes(search) || username.includes(search) || email.includes(search);
        let matchRole = role === "" || userRole === role;
        let matchStatus = status === "" || userStatus === status;

        row.style.display = (matchSearch && matchRole && matchStatus) ? "" : "none";
    });
}

searchInput.addEventListener('input', filterUsers);
roleFilter.addEventListener('change', filterUsers);
statusFilter.addEventListener('change', filterUsers);

/* ================= CLOSE MODAL ================= */
window.onclick = e => {
    if (e.target === addModal)  addModal.style.display = 'none';
    if (e.target === editModal) editModal.style.display = 'none';
};
</script>

@endsection