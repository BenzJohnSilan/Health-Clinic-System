@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-appointments.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-pending-accounts.css') }}">
@endsection

@section('content')
<div class="container">

    <div class="page-header">
        <h1>Pending Accounts</h1>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- ================================================================
         PENDING ACCOUNTS TABLE
    ================================================================ -->
    <div class="pa-table-container">
    <table class="pending-accounts-table">
        <colgroup>
            <col class="pa-col-name">
            <col class="pa-col-email">
            <col class="pa-col-role">
            <col class="pa-col-verified">
            <col class="pa-col-action">
        </colgroup>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Email Verified</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($pendingUsers as $user)
            <tr>
                <td>
                    @if($user->avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}"
                             style="width:30px;height:30px;border-radius:50%;object-fit:cover;
                                    vertical-align:middle;margin-right:6px;">
                    @endif
                    {{ $user->first_name }} {{ $user->last_name }}
                </td>
                <td>{{ $user->email }}</td>
                <td>
                    <span class="badge badge--role badge--{{ strtolower($user->role) }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td>
                    @if($user->email_verified_at)
                        <span class="badge badge--verified">✓ Verified</span>
                    @else
                        <span class="badge badge--unverified">✗ Unverified</span>
                    @endif
                </td>
                <td>
                    <div class="pa-action-cell">
                        <button type="button" class="pa-btn-view"
                                onclick="openModal({{ $user->id }})">
                            View
                        </button>

                        <div class="pa-action-menu">
                            <button type="button" class="pa-action-menu-toggle"
                                    aria-haspopup="true" aria-expanded="false" aria-label="More actions">
                                <i class='bx bx-dots-vertical-rounded'></i>
                            </button>

                            <div class="pa-action-menu-dropdown">
                                <form action="{{ route('admin.approve', $user->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="pa-action-menu-item pa-action-menu-approve">
                                        <i class='bx bx-check'></i> Approve Account
                                    </button>
                                </form>

                                <button type="button" class="pa-action-menu-item pa-action-menu-reject"
                                        onclick="openRejectModal({{ $user->id }})">
                                    <i class='bx bx-x'></i> Reject Account
                                </button>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>

            {{-- ================= VIEW MODAL ================= --}}
            <div id="modal-{{ $user->id }}" class="modal">
                <div class="modal-content modal-content--review">

                    <!-- Sticky Header -->
                    <div class="pa-review-header">
                        <div>
                            <h3>Account Review</h3>
                            <p class="pa-review-subtext">Review the information submitted during registration.</p>
                        </div>
                        <button class="modal-close" type="button"
                                onclick="closeModal({{ $user->id }})">&times;</button>
                    </div>

                    <!-- Scrollable Body -->
                    <div class="pa-review-body">

                        <!-- Compact Applicant Identity Card -->
                        <div class="pa-review-identity">
                            @if($user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}"
                                     class="pa-review-avatar-img" alt="avatar">
                            @else
                                <div class="pa-review-avatar-initials">
                                    {{ strtoupper(substr($user->first_name,0,1)) }}{{ strtoupper(substr($user->last_name,0,1)) }}
                                </div>
                            @endif
                            <div class="pa-review-identity-info">
                                <p class="pa-review-name">
                                    {{ trim($user->first_name . ' ' . $user->middle_name . ' ' . $user->last_name . ' ' . $user->suffix) }}
                                </p>
                                <div class="pa-review-badges">
                                    <span class="badge badge--role badge--{{ strtolower($user->role) }}">
                                        {{ ucfirst($user->role) }}
                                    </span>
                                    @if($user->email_verified_at)
                                        <span class="badge badge--verified">✓ Verified</span>
                                    @else
                                        <span class="badge badge--unverified">✗ Unverified</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="form-section">
                            <div class="section-heading">
                                <span class="section-badge">Personal Information</span>
                            </div>
                            <div class="form-grid-2">
                                <div class="info-cell"><span class="info-label">First Name</span><p>{{ $user->first_name }}</p></div>
                                @if($user->middle_name)
                                <div class="info-cell"><span class="info-label">Middle Name</span><p>{{ $user->middle_name }}</p></div>
                                @endif
                                <div class="info-cell"><span class="info-label">Last Name</span><p>{{ $user->last_name }}</p></div>
                                @if($user->suffix)
                                <div class="info-cell"><span class="info-label">Suffix</span><p>{{ $user->suffix }}</p></div>
                                @endif
                                <div class="info-cell"><span class="info-label">Birthdate</span><p>{{ $user->birthdate ? \Carbon\Carbon::parse($user->birthdate)->format('M d, Y') : '—' }}</p></div>
                                <div class="info-cell"><span class="info-label">Gender</span><p>{{ $user->gender ?? '—' }}</p></div>
                                <div class="info-cell"><span class="info-label">Civil Status</span><p>{{ $user->civil_status ?? '—' }}</p></div>
                                <div class="info-cell"><span class="info-label">Contact Number</span><p>{{ $user->contact_number ?? '—' }}</p></div>
                                <div class="info-cell info-cell-full"><span class="info-label">Complete Address</span><p>{{ $user->address ?? '—' }}</p></div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="form-section">
                            <div class="section-heading">
                                <span class="section-badge">Account Information</span>
                            </div>
                            <div class="form-grid-2">
                                <div class="info-cell"><span class="info-label">Username</span><p>{{ $user->username }}</p></div>
                                <div class="info-cell"><span class="info-label">Email</span><p>{{ $user->email }}</p></div>
                                <div class="info-cell"><span class="info-label">Role</span><p>{{ ucfirst($user->role) }}</p></div>
                                <div class="info-cell">
                                    <span class="info-label">Email Verification</span>
                                    <p>
                                        @if($user->email_verified_at)
                                            <span class="badge badge--verified">✓ Verified</span>
                                        @else
                                            <span class="badge badge--unverified">✗ Unverified</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Identity Verification -->
                        <div class="form-section">
                            <div class="section-heading">
                                <span class="section-badge">Identity Verification</span>
                            </div>
                            <div class="pa-review-id-row">
                                <div class="info-cell">
                                    <span class="info-label">ID Type</span>
                                    <p>{{ $user->id_type ?? '—' }}</p>
                                </div>
                                @if($user->valid_id)
                                <div class="info-cell pa-id-cell">
                                    <span class="info-label">Valid ID</span>
                                    <div class="pa-id-thumb-row">
                                        <a href="{{ asset('storage/' . $user->valid_id) }}" target="_blank" rel="noopener" class="pa-id-thumb-link">
                                            <img src="{{ asset('storage/' . $user->valid_id) }}" alt="Valid ID" class="pa-id-thumb">
                                        </a>
                                        <a href="{{ asset('storage/' . $user->valid_id) }}" target="_blank" rel="noopener" class="pa-id-view-link">
                                            <i class='bx bx-expand'></i> View Full ID
                                        </a>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                    </div>

                    <!-- Sticky Footer Actions -->
                    <div class="pa-review-footer">
                        <button type="button" class="pa-btn-close" onclick="closeModal({{ $user->id }})">
                            Close
                        </button>
                        <form action="{{ route('admin.approve', $user->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn-primary pa-btn-approve">
                                Approve Account
                            </button>
                        </form>
                    </div>

                </div>
            </div>

            {{-- ================= REJECT MODAL ================= --}}
            <div id="reject-modal-{{ $user->id }}" class="modal">
                <div class="modal-content modal-content--sm">

                    <button class="modal-close" type="button"
                            onclick="closeRejectModal({{ $user->id }})">&times;</button>

                    <h3>Reject Account</h3>

                    <!-- Reject User Card -->
                    <div class="pending-reject-card">
                        <div class="pending-reject-avatar">
                            {{ strtoupper(substr($user->first_name,0,1)) }}{{ strtoupper(substr($user->last_name,0,1)) }}
                        </div>
                        <div>
                            <p class="pending-reject-name">{{ $user->first_name }} {{ $user->last_name }}</p>
                            <p class="pending-reject-email">{{ $user->email }}</p>
                        </div>
                        <span class="badge badge--role badge--{{ strtolower($user->role) }}" style="margin-left:auto;">
                            {{ ucfirst($user->role) }}
                        </span>
                    </div>

                    <form action="{{ route('admin.reject', $user->id) }}"
                          method="POST"
                          id="reject-form-{{ $user->id }}">
                        @csrf
                        <div class="form-section">
                            <div class="section-heading">
                                <span class="section-badge section-badge--reject">Reason for Rejection</span>
                            </div>
                            <div class="form-group">
                                <textarea name="reason"
                                          class="reject-textarea"
                                          placeholder="Provide a clear reason for rejecting this account. The applicant will be notified via email."
                                          required></textarea>
                            </div>
                            <div class="reject-warning-box">
                                ⚠ The applicant will receive an email notification with this reason.
                            </div>
                        </div>

                        <button type="submit" class="btn-danger btn-submit-sm">
                            Confirm Rejection
                        </button>
                    </form>

                </div>
            </div>

        @empty
            <tr>
                <td colspan="5" style="text-align:center;color:#6b7280;padding:30px;">
                    No pending accounts found.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    </div>

</div>

<script>
function openModal(id) {
    document.getElementById('modal-' + id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById('modal-' + id).style.display = 'none';
    document.body.style.overflow = '';
}
function openRejectModal(id) {
    document.getElementById('reject-modal-' + id).style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeRejectModal(id) {
    document.getElementById('reject-modal-' + id).style.display = 'none';
    document.body.style.overflow = '';
}

// ================= ACTION MENU (three-dot dropdown) =================
// While open, each dropdown is switched to position:fixed and placed via
// JS using the toggle button's screen position — this keeps it fully
// visible even though the table wrapper uses overflow-x:auto for
// horizontal scrolling (fixed elements aren't clipped by that).
document.addEventListener('DOMContentLoaded', function () {
    let openMenu = null;

    function closeActionMenu() {
        if (!openMenu) return;
        openMenu.dropdown.style.display = 'none';
        openMenu.dropdown.style.position = '';
        openMenu.dropdown.style.top = '';
        openMenu.dropdown.style.left = '';
        openMenu.toggle.setAttribute('aria-expanded', 'false');
        openMenu = null;
    }

    document.querySelectorAll('.pa-action-menu-toggle').forEach(function (toggle) {
        const wrapper  = toggle.closest('.pa-action-menu');
        const dropdown = wrapper.querySelector('.pa-action-menu-dropdown');

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();

            const alreadyOpenForThis = openMenu && openMenu.dropdown === dropdown;
            closeActionMenu();
            if (alreadyOpenForThis) return;

            const rect = toggle.getBoundingClientRect();
            const menuWidth = 190;

            dropdown.style.display = 'block';
            dropdown.style.position = 'fixed';
            dropdown.style.minWidth = menuWidth + 'px';

            // Keep the menu on-screen horizontally.
            let left = rect.right - menuWidth;
            if (left < 8) left = 8;
            if (left + menuWidth > window.innerWidth - 8) {
                left = window.innerWidth - menuWidth - 8;
            }

            // Flip above the button if there isn't room below.
            const estimatedMenuHeight = dropdown.offsetHeight || 90;
            let top = rect.bottom + 6;
            if (top + estimatedMenuHeight > window.innerHeight - 8) {
                top = rect.top - estimatedMenuHeight - 6;
            }

            dropdown.style.top  = top + 'px';
            dropdown.style.left = left + 'px';

            toggle.setAttribute('aria-expanded', 'true');
            openMenu = { dropdown: dropdown, toggle: toggle };
        });
    });

    document.addEventListener('click', closeActionMenu);
    window.addEventListener('scroll', closeActionMenu, true);
    window.addEventListener('resize', closeActionMenu);
});
</script>
@endsection