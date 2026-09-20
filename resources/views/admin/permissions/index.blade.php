@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-permissions.css') }}">
@endsection

@section('content')

<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <div>
            <h2>Manage Permissions</h2>
            <div class="page-subtext">Control what each Doctor or Staff account can access.</div>
        </div>
    </div>

    <!-- ================= ALERTS ================= -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <!-- ================= SEARCH & FILTER ================= -->
    <form method="GET" action="{{ route('admin.permissions.index') }}" class="search-filter-bar">
        <input
            type="text"
            name="search"
            class="filter-input"
            value="{{ $search }}"
            placeholder="Search user by name, username, or email...">

        <select name="role" class="filter-input">
            <option value="">All Roles</option>
            <option value="Doctor" {{ $role == 'Doctor' ? 'selected' : '' }}>Doctor</option>
            <option value="Staff"  {{ $role == 'Staff'  ? 'selected' : '' }}>Staff</option>
        </select>

        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>

        @if($search !== '' || $role !== '')
            <a href="{{ route('admin.permissions.index') }}" class="btn-clear-filter">
                Clear
            </a>
        @endif
    </form>

    <!-- ================= TABLE ================= -->
    <div class="table-container">
        <table class="permissions-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Permissions</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php
                        $assignedSlugs = $userPermissionSlugs->get($user->id, collect());
                    @endphp
                    <tr>
                        <td data-label="User">
                            <div class="user-cell-name">{{ $user->first_name }} {{ $user->last_name }}</div>
                            <div class="user-cell-email">{{ $user->email }}</div>
                        </td>
                        <td data-label="Role">
                            <span class="role-badge {{ strtolower($user->role) }}">{{ $user->role }}</span>
                        </td>
                        <td data-label="Permissions">
                            <span class="perm-count">{{ $user->permissions_count }} permission{{ $user->permissions_count === 1 ? '' : 's' }}</span>
                        </td>
                        <td data-label="Action">
                            <button
                                type="button"
                                class="btn-manage"
                                onclick='openManageModal(
                                    {{ $user->id }},
                                    {{ json_encode(trim($user->first_name . " " . $user->last_name)) }},
                                    {{ json_encode($user->role) }},
                                    {{ json_encode($assignedSlugs->values()) }}
                                )'
                            >
                                Manage
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="no-data">
                            @if($search !== '' || $role !== '')
                                No users match your search/filters.
                            @else
                                No approved Doctor or Staff accounts to manage yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

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

</div>

<!-- ================= MANAGE PERMISSIONS MODAL ================= -->
<div id="permModal" class="modal-overlay" style="display:none;">
    <div class="modal-box perm-modal">
        <h3>Manage Permissions</h3>
        <div class="perm-modal-subject">
            <strong id="permModalUserName"></strong> — <span id="permModalUserRole"></span>
        </div>

        <form id="permModalForm" action="" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="role" value="{{ $role }}">
            <input type="hidden" name="page" value="{{ $users->currentPage() }}">

            <div class="perm-modules">
                @foreach($groupedPermissions as $module => $permissions)
                    <div class="perm-module">
                        <div class="perm-module-title">{{ $module }}</div>
                        <div class="perm-module-body">
                            @foreach($permissions as $permission)
                                <label class="perm-checkbox">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission->id }}"
                                        data-slug="{{ $permission->slug }}"
                                    >
                                    {{ $permission->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closePermModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
function openManageModal(userId, userName, userRole, assignedSlugs) {
    document.getElementById('permModalForm').action = `/admin/permissions/${userId}`;
    document.getElementById('permModalUserName').textContent = userName;
    document.getElementById('permModalUserRole').textContent = userRole;

    const assigned = new Set(assignedSlugs);
    document.querySelectorAll('#permModalForm input[type="checkbox"]').forEach(cb => {
        cb.checked = assigned.has(cb.dataset.slug);
    });

    document.getElementById('permModal').style.display = 'flex';
}

function closePermModal() {
    document.getElementById('permModal').style.display = 'none';
}

window.addEventListener('click', function (e) {
    const modal = document.getElementById('permModal');
    if (e.target === modal) modal.style.display = 'none';
});
</script>
@endsection
