@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-users.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1>Manage Users</h1>
        <button id="openAddModal" class="btn-add-user" type="button">
            <i class="fa-solid fa-plus"></i> Add User
        </button>
    </div>

    <!-- ================= SEARCH & FILTER ================= -->
    <form method="GET" action="{{ route('admin.users.index') }}" class="filter-bar">
        <input
            type="text"
            name="search"
            class="filter-input"
            placeholder="Search name, username, or email..."
            value="{{ request('search') }}">

        <select name="role" class="filter-input">
            <option value="">All Roles</option>
            <option value="Admin"   {{ request('role') == 'Admin'   ? 'selected' : '' }}>Admin</option>
            <option value="Doctor"  {{ request('role') == 'Doctor'  ? 'selected' : '' }}>Doctor</option>
            <option value="Staff"   {{ request('role') == 'Staff'   ? 'selected' : '' }}>Staff</option>
            <option value="Patient" {{ request('role') == 'Patient' ? 'selected' : '' }}>Patient</option>
        </select>

        <select name="status" class="filter-input">
            <option value="">All Status</option>
            <option value="Active"   {{ request('status') == 'Active'   ? 'selected' : '' }}>Active</option>
            <option value="Inactive" {{ request('status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
        </select>

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        @if(request('search') || request('role') || request('status'))
            <a href="{{ route('admin.users.index') }}" class="btn-clear-filter">
                Clear
            </a>
        @endif
    </form>

    <!-- ================= SUCCESS MESSAGE ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <!-- ================================================================
         ADD USER MODAL
    ================================================================ -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">

            <!-- X close — ONLY way to close -->
            <button id="closeAddModal" class="modal-close" type="button">&times;</button>

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

            <!-- ===== STEP 1 : ROLE PICKER ===== -->
            <div id="rolePicker">
                <p class="step-hint">Select a role to continue</p>
                <div class="role-cards">
                    <button type="button" class="role-card" data-role="Admin">
                        <span class="role-icon">🛡️</span>
                        <span class="role-name">Admin</span>
                        <span class="role-desc">Manages users and system settings</span>
                    </button>
                    <button type="button" class="role-card" data-role="Doctor">
                        <span class="role-icon">🩺</span>
                        <span class="role-name">Doctor</span>
                        <span class="role-desc">Handles consultations and prescriptions</span>
                    </button>
                    <button type="button" class="role-card" data-role="Staff">
                        <span class="role-icon">🗂️</span>
                        <span class="role-name">Staff</span>
                        <span class="role-desc">Front desk, billing and inventory</span>
                    </button>
                </div>
            </div>

            <!-- ===== STEP 2 : FORM (hidden until role chosen) ===== -->
            <form id="addUserForm" action="{{ route('admin.users.store') }}" method="POST"
                  enctype="multipart/form-data" style="display:none;">
                @csrf

                <!-- Hidden role value filled by JS -->
                <input type="hidden" name="role" id="selectedRole">

                <!-- ── ROLE BREADCRUMB BAR ── -->
                <div class="role-breadcrumb">
                    <button type="button" id="backToRolePicker" class="btn-back-role">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Change Role
                    </button>

                    <div class="role-breadcrumb-right">
                        <span class="role-breadcrumb-dot" id="roleDotColor"></span>
                        <span class="role-breadcrumb-label">Creating:&nbsp;</span>
                        <strong id="selectedRoleLabel" class="role-breadcrumb-value"></strong>
                    </div>
                </div>

                <!-- === ACCOUNT === -->
                <div class="form-section">
                    <div class="section-heading">
                        <span class="section-badge">Account</span>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Avatar</label>
                            <input type="file" name="avatar" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" value="{{ old('username') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="status" required>
                                <option value="Active"   {{ old('status','Active') == 'Active'   ? 'selected' : '' }}>Active</option>
                                <option value="Inactive" {{ old('status')          == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- === PERSONAL INFORMATION === -->
                <div class="form-section">
                    <div class="section-heading">
                        <span class="section-badge">Personal Information</span>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" value="{{ old('middle_name') }}">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" name="suffix" value="{{ old('suffix') }}" placeholder="Jr., Sr., III">
                        </div>
                        <div class="form-group">
                            <label>Birthdate <span class="required">*</span></label>
                            <input type="date" name="birthdate" value="{{ old('birthdate') }}"
                                   max="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="form-group">
                            <label>Gender <span class="required">*</span></label>
                            <select name="gender" required>
                                <option value="">-- Select --</option>
                                <option value="Male"   {{ old('gender') == 'Male'   ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                <option value="Other"  {{ old('gender') == 'Other'  ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Civil Status <span class="required">*</span></label>
                            <select name="civil_status" required>
                                <option value="">-- Select --</option>
                                <option value="Single"    {{ old('civil_status') == 'Single'    ? 'selected' : '' }}>Single</option>
                                <option value="Married"   {{ old('civil_status') == 'Married'   ? 'selected' : '' }}>Married</option>
                                <option value="Widowed"   {{ old('civil_status') == 'Widowed'   ? 'selected' : '' }}>Widowed</option>
                                <option value="Separated" {{ old('civil_status') == 'Separated' ? 'selected' : '' }}>Separated</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Contact Number <span class="required">*</span></label>
                            <input type="text" name="contact_number" value="{{ old('contact_number') }}"
                                   maxlength="11" pattern="\d{11}"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                                   placeholder="09XXXXXXXXX" required>
                        </div>
                        <div class="form-group form-group-full">
                            <label>Address <span class="required">*</span></label>
                            <input type="text" name="address" value="{{ old('address') }}" required>
                        </div>
                    </div>
                </div>

                <!-- === DOCTOR INFO (Doctor only) === -->
                <div class="form-section role-section" data-for="Doctor" style="display:none;">
                    <div class="section-heading">
                        <span class="section-badge section-badge--doctor">Doctor Information</span>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Specialization <span class="required">*</span></label>
                            <input type="text" name="specialization" value="{{ old('specialization') }}"
                                   placeholder="e.g. Cardiology, Pediatrics">
                        </div>
                        <div class="form-group">
                            <label>PRC License Number <span class="required">*</span></label>
                            <input type="text" name="license_number" value="{{ old('license_number') }}"
                                   placeholder="e.g. 0012345">
                        </div>
                    </div>
                </div>

                <!-- === STAFF INFO (Staff only) === -->
                <div class="form-section role-section" data-for="Staff" style="display:none;">
                    <div class="section-heading">
                        <span class="section-badge section-badge--staff">Staff Information</span>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Employee ID</label>
                            <div class="input-with-badge">
                                <input type="text" id="employee_id_field" name="employee_id"
                                       value="{{ old('employee_id') }}"
                                       placeholder="EMP-0001" readonly
                                       class="input-readonly">
                                <span class="input-badge">Auto-generated</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Position <span class="required">*</span></label>
                            <select id="position_field" name="position">
                                <option value="">-- Select Position --</option>
                                <option value="Nurse"   {{ old('position') == 'Nurse'   ? 'selected' : '' }}>Nurse</option>
                                <option value="Cashier" {{ old('position') == 'Cashier' ? 'selected' : '' }}>Cashier</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Permission Preset</label>
                            <select id="permission_preset_field" name="permission_preset">
                                @foreach(\App\Models\Permission::presets() as $presetKey => $preset)
                                    <option value="{{ $presetKey }}"
                                            {{ old('permission_preset', 'standard') === $presetKey ? 'selected' : '' }}>
                                        {{ $preset['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <small style="color:#6b7280; font-size:12px;">
                                The Position is only a label. Access comes from the permissions given here;
                                you can fine-tune them later in Manage Permissions.
                            </small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary btn-submit">Create User</button>

            </form><!-- /addUserForm -->

        </div>
    </div><!-- /addUserModal -->

    <!-- ================================================================
         EDIT USER MODAL
    ================================================================ -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <button id="closeEditModal" class="modal-close" type="button">&times;</button>
            <h3>Edit User</h3>

            <form id="editUserForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="form-section">
                    <div class="section-heading"><span class="section-badge">Account</span></div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Avatar</label>
                            <input type="file" name="avatar" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" id="edit_username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="edit_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select id="edit_role" name="role" required>
                                <option value="Admin">Admin</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Staff">Staff</option>
                                <option value="Patient">Patient</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select id="edit_status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-heading"><span class="section-badge">Personal Information</span></div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" id="edit_first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" id="edit_middle_name" name="middle_name">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" id="edit_last_name" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Suffix</label>
                            <input type="text" id="edit_suffix" name="suffix">
                        </div>
                        <div class="form-group">
                            <label>Birthdate</label>
                            <input type="date" id="edit_birthdate" name="birthdate" required>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select id="edit_gender" name="gender" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Civil Status</label>
                            <select id="edit_civil_status" name="civil_status" required>
                                <option value="">-- Select --</option>
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" id="edit_contact_number" name="contact_number"
                                   maxlength="11" pattern="\d{11}"
                                   oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                        </div>
                        <div class="form-group form-group-full">
                            <label>Address</label>
                            <input type="text" id="edit_address" name="address" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary btn-submit">Update User</button>
            </form>
        </div>
    </div><!-- /editUserModal -->

    <!-- ================================================================
         USERS TABLE
    ================================================================ -->
    <div class="table-container">
    <table class="users-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Verified</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>{{ $user->id }}</td>
                <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                <td>{{ $user->username }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    @if($user->email_verified_at)
                        <span class="badge badge--verified">✓ Verified</span>
                    @else
                        <span class="badge badge--unverified">✗ Unverified</span>
                    @endif
                </td>
                <td><span class="badge badge--role badge--{{ strtolower($user->role) }}">{{ $user->role }}</span></td>
                <td><span class="badge badge--{{ strtolower($user->status) }}">{{ $user->status }}</span></td>
                <td class="action-cell">
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

                    {{-- =====================================================
                         DELETE BUTTON
                         Disabled + tooltip kung Admin ang role ng user.
                         Ito ay frontend-only protection. Para sa mas
                         mataas na seguridad, i-check din sa Controller.
                    ====================================================== --}}
                    @if($user->role === 'Admin')
                        <button type="button"
                                class="btn-delete-disabled"
                                disabled
                                title="Admin accounts cannot be archived">
                            Archive
                        </button>
                    @else
                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" onclick="return confirm('Archive this account? It will be deactivated and moved to the Archive. Appointments, medical records and billing are kept, and you can restore it any time.')">Archive</button>
                        </form>
                    @endif

                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align:center;color:#6b7280;padding:30px;">
                    @if(request('search') || request('role') || request('status'))
                        No users match your search/filters.
                    @else
                        No approved users found.
                    @endif
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div><!-- /table-container -->

    <!-- ================= PAGINATION ================= -->
    <div class="pagination-wrapper">
        <div class="pagination-info">
            @if($users->total() > 0)
                Showing <strong>{{ $users->firstItem() }}–{{ $users->lastItem() }}</strong>
                of <strong>{{ $users->total() }}</strong> result{{ $users->total() !== 1 ? 's' : '' }}
            @else
                No results found
            @endif
        </div>

        <nav class="pagination-nav" aria-label="Pagination">

            {{-- Previous --}}
            @if($users->onFirstPage())
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-left"></i>
                </span>
            @else
                <a class="page-btn" href="{{ $users->previousPageUrl() }}">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            @endif

            {{-- Page Numbers --}}
            @php
                $currentPage = $users->currentPage();
                $lastPage    = $users->lastPage();

                $pages = collect(range(1, $lastPage))->filter(function ($p) use ($currentPage, $lastPage) {
                    return $p === 1
                        || $p === $lastPage
                        || abs($p - $currentPage) <= 2;
                })->values();
            @endphp

            @php $prev = null; @endphp
            @foreach($pages as $page)
                @if($prev !== null && $page - $prev > 1)
                    <span class="page-ellipsis">…</span>
                @endif

                @if($page === $currentPage)
                    <span class="page-btn active">{{ $page }}</span>
                @else
                    <a class="page-btn" href="{{ $users->url($page) }}">{{ $page }}</a>
                @endif

                @php $prev = $page; @endphp
            @endforeach

            {{-- Next --}}
            @if($users->hasMorePages())
                <a class="page-btn" href="{{ $users->nextPageUrl() }}">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            @else
                <span class="page-btn disabled">
                    <i class="fa-solid fa-chevron-right"></i>
                </span>
            @endif

        </nav>
    </div>

</div><!-- /container -->

<!-- ================================================================
     JAVASCRIPT
================================================================ -->
<script>
/* ------------------------------------------------------------------
   ELEMENTS
------------------------------------------------------------------ */
const addModal       = document.getElementById('addUserModal');
const editModal      = document.getElementById('editUserModal');
const rolePicker     = document.getElementById('rolePicker');
const addUserForm    = document.getElementById('addUserForm');
const selectedRoleEl = document.getElementById('selectedRole');
const roleLabelEl    = document.getElementById('selectedRoleLabel');
const roleDotEl      = document.getElementById('roleDotColor');

/* ------------------------------------------------------------------
   ROLE META  –  color dots + labels
------------------------------------------------------------------ */
const ROLE_META = {
    Admin:   { dot: '#6366f1', label: 'Admin'   },
    Doctor:  { dot: '#22c55e', label: 'Doctor'  },
    Staff:   { dot: '#eab308', label: 'Staff'   },
};

/* ------------------------------------------------------------------
   HELPERS
------------------------------------------------------------------ */
function showRolePicker() {
    rolePicker.style.display  = 'block';
    addUserForm.style.display = 'none';
}

function showFormForRole(role) {
    selectedRoleEl.value    = role;
    roleLabelEl.textContent = role;

    // Update colour dot
    const meta = ROLE_META[role] ?? {};
    if (roleDotEl) roleDotEl.style.backgroundColor = meta.dot ?? '#6b7280';

    // Show/hide role-specific sections
    document.querySelectorAll('#addUserForm .role-section').forEach(sec => {
        sec.style.display = sec.dataset.for === role ? 'block' : 'none';
    });

    // Auto-generate Employee ID when Staff is selected
    if (role === 'Staff') generateEmployeeId();

    rolePicker.style.display  = 'none';
    addUserForm.style.display = 'block';
}

function resetAddModal() {
    addUserForm.reset();
    showRolePicker();
}

/* ------------------------------------------------------------------
   AUTO-GENERATE EMPLOYEE ID
   Format: EMP-XXXX  (padded 4-digit number)
   Fetches the latest count from the server so the ID is always fresh.
------------------------------------------------------------------ */
function generateEmployeeId() {
    const field = document.getElementById('employee_id_field');
    if (!field || field.value) return;   // already has an old('employee_id') value

    fetch('/admin/users/next-employee-id')
        .then(r => r.json())
        .then(data => {
            field.value = data.employee_id ?? 'EMP-0001';
        })
        .catch(() => {
            // Fallback: count existing staff rows on this page
            const staffCount = document.querySelectorAll('.badge--staff').length;
            const next = String(staffCount + 1).padStart(4, '0');
            field.value = `EMP-${next}`;
        });
}

/* ------------------------------------------------------------------
   OPEN  –  always start fresh at Step 1
------------------------------------------------------------------ */
document.getElementById('openAddModal').onclick = () => {
    resetAddModal();
    addModal.style.display = 'flex';
};

/* ------------------------------------------------------------------
   CLOSE  –  X BUTTON ONLY
------------------------------------------------------------------ */
document.getElementById('closeAddModal').onclick = () => {
    resetAddModal();
    addModal.style.display = 'none';
};

document.getElementById('closeEditModal').onclick = () => {
    editModal.style.display = 'none';
};

/* ===========================================================
   POSITION -> PRESET (convenience only)
   Choosing "Cashier" as the Position pre-selects the Cashier
   permission preset (Nurse pre-selects Standard Staff) so it is not forgotten. The Position never
   grants access by itself — whatever preset is selected in the
   dropdown (and later edited in Manage Permissions) is what the
   account actually receives.
   =========================================================== */
(function () {
    const positionField = document.getElementById('position_field');
    const presetField   = document.getElementById('permission_preset_field');
    if (!positionField || !presetField) return;

    positionField.addEventListener('change', () => {
        const position = positionField.value.trim().toLowerCase();
        if (position === 'cashier') presetField.value = 'cashier';
        if (position === 'nurse')   presetField.value = 'standard';
    });
})();

/* ------------------------------------------------------------------
   ROLE CARDS  –  clicking a card moves to Step 2
------------------------------------------------------------------ */
document.querySelectorAll('#rolePicker .role-card').forEach(card => {
    card.addEventListener('click', () => showFormForRole(card.dataset.role));
});

/* ------------------------------------------------------------------
   BACK BUTTON
------------------------------------------------------------------ */
document.getElementById('backToRolePicker').onclick = () => {
    addUserForm.reset();
    showRolePicker();
};

/* ------------------------------------------------------------------
   AUTO-RESTORE after Laravel validation failure
------------------------------------------------------------------ */
@if(old('role'))
(function () {
    const oldRole = @json(old('role'));
    showFormForRole(oldRole);
    addModal.style.display = 'flex';
})();
@endif

/* ------------------------------------------------------------------
   EDIT MODAL
------------------------------------------------------------------ */
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.onclick = () => {
        document.getElementById('edit_first_name').value     = btn.dataset.first     ?? '';
        document.getElementById('edit_middle_name').value    = btn.dataset.middle    ?? '';
        document.getElementById('edit_last_name').value      = btn.dataset.last      ?? '';
        document.getElementById('edit_suffix').value         = btn.dataset.suffix    ?? '';
        document.getElementById('edit_birthdate').value      = btn.dataset.birthdate ?? '';
        document.getElementById('edit_gender').value         = btn.dataset.gender    ?? '';
        document.getElementById('edit_civil_status').value   = btn.dataset.civil     ?? '';
        document.getElementById('edit_address').value        = btn.dataset.address   ?? '';
        document.getElementById('edit_contact_number').value = btn.dataset.contact   ?? '';
        document.getElementById('edit_username').value       = btn.dataset.username  ?? '';
        document.getElementById('edit_email').value          = btn.dataset.email     ?? '';
        document.getElementById('edit_role').value           = btn.dataset.role      ?? '';
        document.getElementById('edit_status').value         = btn.dataset.status    ?? '';

        document.getElementById('editUserForm').action = `/admin/users/${btn.dataset.id}`;
        editModal.style.display = 'flex';
    };
});

</script>

@endsection