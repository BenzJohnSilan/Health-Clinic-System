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
    <table class="users-table">
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
                    <button type="button" class="btn-view-pending"
                            onclick="openModal({{ $user->id }})">
                        View
                    </button>
                    <form action="{{ route('admin.approve', $user->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn-approve-pending">Approve</button>
                    </form>
                    <button type="button" class="btn-reject-pending"
                            onclick="openRejectModal({{ $user->id }})">
                        Reject
                    </button>
                </td>
            </tr>

            {{-- ================= VIEW MODAL ================= --}}
            <div id="modal-{{ $user->id }}" class="modal">
                <div class="modal-content">

                    <button class="modal-close" type="button"
                            onclick="closeModal({{ $user->id }})">&times;</button>

                    <h3>User Information</h3>

                    <!-- User Hero -->
                    <div class="pending-user-hero">
                        @if($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}"
                                 class="pending-avatar-img" alt="avatar">
                        @else
                            <div class="pending-avatar-initials">
                                {{ strtoupper(substr($user->first_name,0,1)) }}{{ strtoupper(substr($user->last_name,0,1)) }}
                            </div>
                        @endif
                        <div class="pending-hero-info">
                            <p class="pending-hero-name">
                                {{ $user->first_name }} {{ $user->middle_name }} {{ $user->last_name }} {{ $user->suffix }}
                            </p>
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

                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="section-heading">
                            <span class="section-badge">Personal Information</span>
                        </div>
                        <div class="form-grid-2">
                            <div class="info-cell"><span class="info-label">Birthdate</span><p>{{ $user->birthdate ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Age</span><p>{{ $user->age ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Gender</span><p>{{ $user->gender ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Civil Status</span><p>{{ $user->civil_status ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Contact</span><p>{{ $user->contact_number ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Blood Type</span><p>{{ $user->blood_type ?? 'N/A' }}</p></div>
                            <div class="info-cell info-cell-full"><span class="info-label">Address</span><p>{{ $user->address ?? '—' }}</p></div>
                            <div class="info-cell info-cell-full"><span class="info-label">Allergies</span><p>{{ $user->allergies ?? 'None' }}</p></div>
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
                            <div class="info-cell"><span class="info-label">Status</span><p>{{ $user->status }}</p></div>
                            <div class="info-cell"><span class="info-label">Approval</span><p>{{ $user->approval_status }}</p></div>
                        </div>
                    </div>

                    <!-- Identity Verification -->
                    <div class="form-section">
                        <div class="section-heading">
                            <span class="section-badge">Identity Verification</span>
                        </div>
                        <div class="form-grid-2">
                            <div class="info-cell"><span class="info-label">ID Type</span><p>{{ $user->id_type ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Reason for Registration</span><p>{{ $user->reason ?? '—' }}</p></div>
                            @if($user->valid_id)
                            <div class="info-cell info-cell-full">
                                <span class="info-label">Valid ID</span>
                                <img src="{{ asset('storage/' . $user->valid_id) }}"
                                     alt="Valid ID"
                                     style="margin-top:8px;width:100%;max-width:280px;border-radius:8px;
                                            border:1px solid #d1d5db;display:block;">
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="form-section">
                        <div class="section-heading">
                            <span class="section-badge">Emergency Contact</span>
                        </div>
                        <div class="form-grid-2">
                            <div class="info-cell"><span class="info-label">Name</span><p>{{ $user->emergency_name ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Relationship</span><p>{{ $user->relationship ?? '—' }}</p></div>
                            <div class="info-cell"><span class="info-label">Contact Number</span><p>{{ $user->emergency_contact_number ?? '—' }}</p></div>
                            <div class="info-cell info-cell-full"><span class="info-label">Address</span><p>{{ $user->emergency_address ?? '—' }}</p></div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="pending-modal-footer">
                        <form action="{{ route('admin.approve', $user->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn-primary btn-submit-sm">
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
</script>
@endsection