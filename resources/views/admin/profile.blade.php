@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
<style>
    /* Optional: styling ng avatar preview */
    .avatar-preview {
        border-radius: 50%;
        object-fit: cover;
        width: 120px;
        height: 120px;
        display: block;
        margin: 0 auto 10px;
    }
</style>
@endsection

@section('content')

<div class="profile-page">

    <!-- Page Header -->
    <div class="page-header">
        <h1>Manage Profile</h1>
    </div>

    <!-- Success Alert -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <!-- PROFILE CARD -->
    <div class="profile-card">

        <!-- Avatar Section -->
        <div class="avatar-section">
            <img 
                src="{{ $admin->avatar ? asset('storage/' . $admin->avatar) : asset('images/default-avatar.png') }}" 
                alt="Avatar" 
                class="avatar-preview"
            >

            <div class="form-group">
                <input type="file" id="avatar" name="avatar" accept="image/*" form="profileForm">
            </div>
        </div>

        <!-- Profile Form -->
        <form id="profileForm" action="{{ route('admin.profile.update', $admin->id ?? 0) }}" method="POST" enctype="multipart/form-data" class="profile-form">
            @csrf
            @method('POST') <!-- or PUT if your route uses PUT -->

            <div class="form-grid">

                <!-- First Name -->
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $admin->first_name) }}" required>
                </div>

                <!-- Middle Name -->
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $admin->middle_name) }}">
                </div>

                <!-- Last Name -->
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $admin->last_name) }}" required>
                </div>

                <!-- Suffix -->
                <div class="form-group">
                    <label for="suffix">Suffix</label>
                    <input type="text" id="suffix" name="suffix" value="{{ old('suffix', $admin->suffix) }}" placeholder="Jr., Sr., III">
                </div>

                <!-- Gender -->
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" {{ $admin->gender == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ $admin->gender == 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other" {{ $admin->gender == 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <!-- Civil Status -->
                <div class="form-group">
                    <label for="civil_status">Civil Status</label>
                    <select id="civil_status" name="civil_status" required>
                        <option value="">Select Status</option>
                        <option value="Single" {{ $admin->civil_status == 'Single' ? 'selected' : '' }}>Single</option>
                        <option value="Married" {{ $admin->civil_status == 'Married' ? 'selected' : '' }}>Married</option>
                        <option value="Widowed" {{ $admin->civil_status == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                        <option value="Separated" {{ $admin->civil_status == 'Separated' ? 'selected' : '' }}>Separated</option>
                    </select>
                </div>

                <!-- Address -->
                <div class="form-group full">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" required>{{ old('address', $admin->address) }}</textarea>
                </div>

                <!-- Contact Number -->
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number', $admin->contact_number) }}" required>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $admin->username) }}" required>
                </div>

                <!-- Email -->
                <div class="form-group full">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $admin->email) }}" required>
                </div>

            </div>

            <!-- Submit -->
            <button type="submit" class="btn-primary">Update Profile</button>

        </form>

    </div>

</div>

@endsection

@section('scripts')
<script>
document.getElementById('avatar').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if(file && file.type.startsWith('image/')) {
        document.querySelector('.avatar-preview').src = URL.createObjectURL(file);
    }
});
</script>
@endsection