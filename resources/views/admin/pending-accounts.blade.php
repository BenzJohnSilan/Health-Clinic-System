@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/admin-pending-accounts.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-appointments.css') }}">
<style>
/* ================= MODAL BACKGROUND ================= */
.custom-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    transition: all 0.3s ease;
}

/* ================= MODAL CONTENT ================= */
.modal-content {
    background-color: #fff;
    margin: 5% auto;
    border-radius: 12px;
    width: 520px;
    max-width: 95%;
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
    animation: slideDown 0.4s ease;
    overflow: hidden;
    font-family: "Segoe UI", sans-serif;
}

/* ================= MODAL HEADER ================= */
.modal-header {
    background: #3490dc;
    color: #fff;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.modal-header h2 {
    font-size: 20px;
    margin: 0;
}

/* ================= CLOSE ICON ================= */
.close {
    color: #fff;
    font-size: 26px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.2s;
}

.close:hover {
    color: #ddd;
}

/* ================= MODAL BODY ================= */
.modal-body {
    padding: 22px 24px;
    font-size: 14px;
    color: #333;
}

/* User Info */
.user-info {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.avatar img {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #3490dc;
}

.details p {
    margin: 6px 0;
    line-height: 1.5;
}

/* ================= MODAL FOOTER ================= */
.modal-footer {
    padding: 16px 20px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* ================= BUTTONS ================= */
button {
    font-family: "Segoe UI", sans-serif;
    font-size: 14px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

/* View Button */
.btn-view {
    background-color: #3490dc;
    color: #fff;
}

.btn-view:hover {
    background-color: #2779bd;
}

/* Approve Button */
.btn-approve {
    background-color: #38c172;
    color: #fff;
}

.btn-approve:hover {
    background-color: #2f9e5d;
}

/* Reject Button */
.btn-reject {
    background-color: #e3342f;
    color: #fff;
}

.btn-reject:hover {
    background-color: #cc1f1a;
}

/* Footer Close Button */
.btn-close-footer {
    background-color: #6c757d;
    color: #fff;
}

.btn-close-footer:hover {
    background-color: #5a6268;
}

/* Reject Modal Buttons */
.reject-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 15px;
}

.btn-reject-confirm {
    background-color: #e3342f;
    color: #fff;
}

.btn-reject-confirm:hover {
    background-color: #cc1f1a;
}

.btn-reject-cancel {
    background-color: #6c757d;
    color: #fff;
}

.btn-reject-cancel:hover {
    background-color: #5a6268;
}

/* ================= TEXTAREA ================= */
textarea {
    width: 100%;
    min-height: 80px;
    padding: 8px;
    margin-top: 5px;
    border-radius: 6px;
    border: 1px solid #ccc;
    resize: vertical;
    font-family: "Segoe UI", sans-serif;
    font-size: 14px;
}

/* ================= SLIDE ANIMATION ================= */
@keyframes slideDown {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* ================= RESPONSIVE ================= */
@media (max-width: 550px) {
    .modal-content {
        width: 95%;
    }

    .user-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .details p {
        margin: 5px 0;
    }

    .modal-footer, .reject-buttons {
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
    }
}
</style>
@endsection

@section('content')
<div class="container">

    <!-- ================= PAGE HEADER ================= -->
    <div class="page-header">
        <h1 class="page-title">Pending Accounts</h1>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- ================= TABLE ================= -->
    <div class="table-container">
        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Email Verified</th>
                    <th style="width:240px;">Action</th>
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
                            <span style="color:green; font-weight:bold;">Verified</span>
                        @else
                            <span style="color:red; font-weight:bold;">Unverified</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn-view" onclick="openModal({{ $user->id }})">View</button>
                        <form action="{{ route('admin.approve', $user->id) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="btn-approve">Approve</button>
                        </form>
                        <button class="btn-reject" onclick="openRejectModal({{ $user->id }})">Reject</button>
                    </td>
                </tr>

                <!-- ================= VIEW MODAL ================= -->
                <div id="modal-{{ $user->id }}" class="custom-modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal({{ $user->id }})">&times;</span>
                        <div class="modal-header">
                            <h2>User Details</h2>
                        </div>
                        <div class="modal-body">
                            <div class="user-info">
                                @if($user->avatar)
                                <div class="avatar">
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="Avatar">
                                </div>
                                @endif
                                <div class="details">
                                    <p><strong>Name:</strong> {{ $user->first_name }} {{ $user->middle_name ?? '' }} {{ $user->last_name }} {{ $user->suffix ?? '' }}</p>
                                    <p><strong>Age:</strong> {{ $user->age }}</p>
                                    <p><strong>Gender:</strong> {{ $user->gender }}</p>
                                    <p><strong>Civil Status:</strong> {{ $user->civil_status }}</p>
                                    <p><strong>Address:</strong> {{ $user->address }}</p>
                                    <p><strong>Contact Number:</strong> {{ $user->contact_number }}</p>
                                    <p><strong>Username:</strong> {{ $user->username }}</p>
                                    <p><strong>Email:</strong> {{ $user->email }}</p>
                                    <p><strong>Email Verified:</strong> 
                                        @if($user->email_verified_at)
                                        <span style="color:green; font-weight:bold;">Yes</span>
                                        @else
                                        <span style="color:red; font-weight:bold;">No</span>
                                        @endif
                                    </p>
                                    <p><strong>Role:</strong> {{ ucfirst($user->role) }}</p>
                                    <p><strong>Status:</strong> {{ $user->status }}</p>
                                    <p><strong>Approval Status:</strong> {{ $user->approval_status }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-close-footer" onclick="closeModal({{ $user->id }})">Close</button>
                        </div>
                    </div>
                </div>

                <!-- ================= REJECT MODAL ================= -->
                <div id="reject-modal-{{ $user->id }}" class="custom-modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeRejectModal({{ $user->id }})">&times;</span>
                        <div class="modal-header">
                            <h2>Reject User Account</h2>
                        </div>
                        <div class="modal-body">
                            <form action="{{ route('admin.reject', $user->id) }}" method="POST">
                                @csrf
                                <p><strong>Name:</strong> {{ $user->first_name }} {{ $user->last_name }}</p>
                                <p><strong>Email:</strong> {{ $user->email }}</p>
                                <p><strong>Contact Number:</strong> {{ $user->contact_number }}</p>
                                <div>
                                    <label for="reason-{{ $user->id }}"><strong>Reason for rejection:</strong></label>
                                    <textarea name="reason" id="reason-{{ $user->id }}" required></textarea>
                                </div>
                                <div class="reject-buttons">
                                    <button type="submit" class="btn-reject-confirm">Confirm Reject</button>
                                    <button type="button" class="btn-reject-cancel" onclick="closeRejectModal({{ $user->id }})">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @empty
                <tr>
                    <td colspan="5" style="text-align:center; font-style:italic; color:#555;">No pending accounts.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById('modal-' + id).style.display = 'block';
}

function closeModal(id) {
    document.getElementById('modal-' + id).style.display = 'none';
}

function openRejectModal(id) {
    document.getElementById('reject-modal-' + id).style.display = 'block';
}

function closeRejectModal(id) {
    document.getElementById('reject-modal-' + id).style.display = 'none';
}

// Close modal if clicked outside
window.onclick = function(event) {
    let modals = document.querySelectorAll('.custom-modal');
    modals.forEach(function(modal) {
        if(event.target == modal) {
            modal.style.display = 'none';
        }
    });
}
</script>
@endsection