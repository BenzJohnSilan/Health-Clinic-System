@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-appointments.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-pending-accounts.css') }}">
@endsection

@section('content')
<div class="container">

    <div class="page-header">
        <h1 class="page-title">Pending Accounts</h1>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <div class="table-container">
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Email Verified</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            @forelse($pendingUsers as $user)
                <tr>
                    <td>{{ $user->first_name }} {{ $user->last_name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ ucfirst($user->role) }}</td>
                    <td>
                        @if($user->email_verified_at)
                            <span class="badge-verified">Verified</span>
                        @else
                            <span class="badge-unverified">Unverified</span>
                        @endif
                    </td>
                    <td class="action-btns">
                        <button class="btn-view" onclick="openModal({{ $user->id }})">
                            <i class='bx bx-show'></i> View
                        </button>
                        <form action="{{ route('admin.approve', $user->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button class="btn-approve">
                                <i class='bx bx-check'></i> Approve
                            </button>
                        </form>
                        <button class="btn-reject" onclick="openRejectModal({{ $user->id }})">
                            <i class='bx bx-x'></i> Reject
                        </button>
                    </td>
                </tr>

                {{-- ================= VIEW MODAL ================= --}}
                <div id="modal-{{ $user->id }}" class="custom-modal">
                    <div class="modal-overlay" onclick="closeModal({{ $user->id }})"></div>
                    <div class="modal-content view-modal">

                        <div class="modal-header view-header">
                            <div class="modal-header-left">
                                <div class="modal-header-icon">
                                    <i class='bx bxs-user-detail'></i>
                                </div>
                                <div>
                                    <h2>User Information</h2>
                                    <p class="modal-header-sub">Full account details &amp; verification</p>
                                </div>
                            </div>
                            <button class="modal-close-btn" onclick="closeModal({{ $user->id }})">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>

                        <div class="modal-body">

                            <div class="user-hero">
                                @if($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" class="user-avatar-img" alt="avatar">
                                @else
                                    <div class="user-avatar-initials">
                                        {{ strtoupper(substr($user->first_name,0,1)) }}{{ strtoupper(substr($user->last_name,0,1)) }}
                                    </div>
                                @endif
                                <div class="user-hero-info">
                                    <p class="user-hero-name">{{ $user->first_name }} {{ $user->middle_name }} {{ $user->last_name }} {{ $user->suffix }}</p>
                                    <span class="role-badge">{{ ucfirst($user->role) }}</span>
                                    @if($user->email_verified_at)
                                        <span class="verified-pill"><i class='bx bxs-badge-check'></i> Email Verified</span>
                                    @else
                                        <span class="unverified-pill"><i class='bx bx-error-circle'></i> Unverified</span>
                                    @endif
                                </div>
                            </div>

                            <div class="section-label"><i class='bx bxs-id-card'></i> Personal Information</div>
                            <div class="info-grid">
                                <div class="info-cell"><span>Birthdate</span><p>{{ $user->birthdate ?? '—' }}</p></div>
                                <div class="info-cell"><span>Age</span><p>{{ $user->age ?? '—' }}</p></div>
                                <div class="info-cell"><span>Gender</span><p>{{ $user->gender ?? '—' }}</p></div>
                                <div class="info-cell"><span>Civil Status</span><p>{{ $user->civil_status ?? '—' }}</p></div>
                                <div class="info-cell"><span>Contact</span><p>{{ $user->contact_number ?? '—' }}</p></div>
                                <div class="info-cell"><span>Blood Type</span><p>{{ $user->blood_type ?? 'N/A' }}</p></div>
                                <div class="info-cell full"><span>Address</span><p>{{ $user->address ?? '—' }}</p></div>
                                <div class="info-cell full"><span>Allergies</span><p>{{ $user->allergies ?? 'None' }}</p></div>
                            </div>

                            <div class="section-label"><i class='bx bxs-lock-alt'></i> Account Information</div>
                            <div class="info-grid">
                                <div class="info-cell"><span>Username</span><p>{{ $user->username }}</p></div>
                                <div class="info-cell"><span>Email</span><p>{{ $user->email }}</p></div>
                                <div class="info-cell"><span>Status</span><p>{{ $user->status }}</p></div>
                                <div class="info-cell"><span>Approval</span><p>{{ $user->approval_status }}</p></div>
                            </div>

                            <div class="section-label"><i class='bx bxs-shield-check'></i> Identity Verification</div>
                            <div class="info-grid">
                                <div class="info-cell"><span>ID Type</span><p>{{ $user->id_type ?? '—' }}</p></div>
                                <div class="info-cell"><span>Reason for Registration</span><p>{{ $user->reason ?? '—' }}</p></div>
                                @if($user->valid_id)
                                <div class="info-cell full id-preview">
                                    <span>Valid ID</span>
                                    <img src="{{ asset('storage/' . $user->valid_id) }}" alt="Valid ID">
                                </div>
                                @endif
                            </div>

                            <div class="section-label"><i class='bx bxs-phone-call'></i> Emergency Contact</div>
                            <div class="info-grid">
                                <div class="info-cell"><span>Name</span><p>{{ $user->emergency_name ?? '—' }}</p></div>
                                <div class="info-cell"><span>Relationship</span><p>{{ $user->relationship ?? '—' }}</p></div>
                                <div class="info-cell"><span>Contact Number</span><p>{{ $user->emergency_contact_number ?? '—' }}</p></div>
                                <div class="info-cell full"><span>Address</span><p>{{ $user->emergency_address ?? '—' }}</p></div>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button class="btn-footer-close" onclick="closeModal({{ $user->id }})">
                                <i class='bx bx-x'></i> Close
                            </button>
                            <form action="{{ route('admin.approve', $user->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <button class="btn-footer-approve">
                                    <i class='bx bx-check'></i> Approve Account
                                </button>
                            </form>
                        </div>

                    </div>
                </div>

                {{-- ================= REJECT MODAL ================= --}}
                <div id="reject-modal-{{ $user->id }}" class="custom-modal">
                    <div class="modal-overlay" onclick="closeRejectModal({{ $user->id }})"></div>
                    <div class="modal-content reject-modal">

                        <div class="modal-header reject-header">
                            <div class="modal-header-left">
                                <div class="modal-header-icon">
                                    <i class='bx bxs-error-alt'></i>
                                </div>
                                <div>
                                    <h2>Reject Account</h2>
                                    <p class="modal-header-sub">This action cannot be undone</p>
                                </div>
                            </div>
                            <button class="modal-close-btn" onclick="closeRejectModal({{ $user->id }})">
                                <i class='bx bx-x'></i>
                            </button>
                        </div>

                        <div class="modal-body">

                            <div class="reject-user-card">
                                <div class="reject-user-avatar">
                                    {{ strtoupper(substr($user->first_name,0,1)) }}{{ strtoupper(substr($user->last_name,0,1)) }}
                                </div>
                                <div>
                                    <p class="reject-user-name">{{ $user->first_name }} {{ $user->last_name }}</p>
                                    <p class="reject-user-email">{{ $user->email }}</p>
                                </div>
                                <span class="reject-role-pill">{{ ucfirst($user->role) }}</span>
                            </div>

                            <form action="{{ route('admin.reject', $user->id) }}" method="POST" id="reject-form-{{ $user->id }}">
                                @csrf
                                <label class="reason-label">
                                    <i class='bx bx-edit-alt'></i> Reason for Rejection
                                </label>
                                <textarea name="reason" class="reason-textarea" placeholder="Provide a clear reason for rejecting this account. The applicant will be notified via email." required></textarea>
                                <div class="reject-warning">
                                    <i class='bx bxs-info-circle'></i>
                                    The applicant will receive an email notification with this reason.
                                </div>
                            </form>

                        </div>

                        <div class="modal-footer">
                            <button class="btn-footer-close" onclick="closeRejectModal({{ $user->id }})">
                                <i class='bx bx-x'></i> Cancel
                            </button>
                            <button class="btn-footer-reject" onclick="document.getElementById('reject-form-{{ $user->id }}').submit()">
                                <i class='bx bx-trash'></i> Confirm Rejection
                            </button>
                        </div>

                    </div>
                </div>

            @empty
                <tr>
                    <td colspan="5" class="empty-row">
                        <div class="empty-state-box">
                            <div class="empty-state-icon">
                                <i class='bx bx-user-x'></i>
                            </div>
                            <h4>No Pending Accounts</h4>
                            <p>All accounts have been reviewed.<br>New registrations will appear here.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById('modal-' + id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById('modal-' + id).classList.remove('active');
    document.body.style.overflow = '';
}
function openRejectModal(id) {
    document.getElementById('reject-modal-' + id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeRejectModal(id) {
    document.getElementById('reject-modal-' + id).classList.remove('active');
    document.body.style.overflow = '';
}
</script>
@endsection