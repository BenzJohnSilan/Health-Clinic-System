@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
<style>
    /* Avatar styling similar to doctor profile */
    .avatar-section {
        text-align: center;
        margin-bottom: 20px;
    }

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
                src="{{ $patient->avatar ? asset('storage/' . $patient->avatar) : asset('images/default-avatar.png') }}" 
                alt="Avatar" 
                class="avatar-preview"
            >
            <input type="file" id="avatar" name="avatar" accept="image/*" form="profileForm">
        </div>

        <!-- Profile Form -->
        <form id="profileForm" action="{{ route('patient.profile.update') }}" method="POST" enctype="multipart/form-data" class="profile-form">
            @csrf

            <div class="form-grid">

                <!-- First Name -->
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $patient->first_name) }}" required>
                </div>

                <!-- Middle Name -->
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name', $patient->middle_name) }}">
                </div>

                <!-- Last Name -->
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $patient->last_name) }}" required>
                </div>

                <!-- Suffix -->
                <div class="form-group">
                    <label>Suffix</label>
                    <input type="text" name="suffix" value="{{ old('suffix', $patient->suffix) }}" placeholder="Jr., Sr., III">
                </div>

                <!-- Gender -->
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" {{ $patient->gender == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ $patient->gender == 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other" {{ $patient->gender == 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <!-- Civil Status -->
                <div class="form-group">
                    <label>Civil Status</label>
                    <select name="civil_status" required>
                        <option value="">Select Status</option>
                        <option value="Single" {{ $patient->civil_status == 'Single' ? 'selected' : '' }}>Single</option>
                        <option value="Married" {{ $patient->civil_status == 'Married' ? 'selected' : '' }}>Married</option>
                        <option value="Widowed" {{ $patient->civil_status == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                        <option value="Separated" {{ $patient->civil_status == 'Separated' ? 'selected' : '' }}>Separated</option>
                    </select>
                </div>

                <!-- Address -->
                <div class="form-group full">
                    <label>Address</label>
                    <textarea name="address" rows="3" required>{{ old('address', $patient->address) }}</textarea>
                </div>

                <!-- Contact Number -->
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" value="{{ old('contact_number', $patient->contact_number) }}" required>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="{{ old('username', $patient->username) }}" required>
                </div>

                <!-- Email -->
                <div class="form-group full">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email', $patient->email) }}" required>
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