@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">

<style>
/* ALERT ANIMATION */
.alert-success,
.alert-error {
    padding: 12px 16px;
    margin-bottom: 15px;
    border-radius: 8px;
    transition: opacity 0.5s ease, transform 0.5s ease;
    opacity: 1;
}

/* Colors */
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #34d399;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #f87171;
}

/* Fade out class */
.fade-out {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
@endsection

@section('content')

<div class="profile-page">

    <!-- Page Header -->
    <div class="page-header">
        <h1>Manage Profile</h1>
    </div>

    <!-- SUCCESS MESSAGE -->
    @if(session('success'))
        <div class="alert-success auto-hide">
            {{ session('success') }}
        </div>
    @endif

    <!-- ERROR MESSAGES -->
    @if($errors->any())
        <div class="alert-error auto-hide">
            <ul style="margin:0; padding-left:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- PROFILE CARD -->
    <div class="profile-card">

        <!-- Avatar Section -->
        <div class="avatar-section">

            <img 
                src="{{ ($admin->avatar && file_exists(storage_path('app/public/' . $admin->avatar))) 
                    ? asset('storage/' . $admin->avatar) 
                    : asset('images/default-avatar.jpg') }}" 
                alt="Avatar" 
                class="avatar-preview"
            />

            <div class="avatar-actions">

                <input 
                    type="file" 
                    id="avatar" 
                    name="avatar" 
                    accept="image/*" 
                    form="profileForm"
                >

                @if($admin->avatar)
                    <form action="{{ route('admin.profile.remove-avatar', $admin->id) }}" method="POST">
                        @csrf
                        @method('DELETE')

                        <button type="submit"
                            class="btn-danger"
                            onclick="return confirm('Remove profile picture?')">
                            Remove Avatar
                        </button>
                    </form>
                @endif

            </div>
        </div>

        <!-- Profile Form -->
        <form id="profileForm"
              action="{{ route('admin.profile.update', $admin->id ?? 0) }}"
              method="POST"
              enctype="multipart/form-data"
              class="profile-form">

            @csrf
            @method('POST')

            <div class="form-grid">

                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $admin->first_name) }}" required>
                </div>

                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name', $admin->middle_name) }}">
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $admin->last_name) }}" required>
                </div>

                <div class="form-group">
                    <label>Suffix</label>
                    <input type="text" name="suffix" value="{{ old('suffix', $admin->suffix) }}">
                </div>

                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" {{ $admin->gender == 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ $admin->gender == 'Female' ? 'selected' : '' }}>Female</option>
                        <option value="Other" {{ $admin->gender == 'Other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Civil Status</label>
                    <select name="civil_status" required>
                        <option value="">Select Status</option>
                        <option value="Single" {{ $admin->civil_status == 'Single' ? 'selected' : '' }}>Single</option>
                        <option value="Married" {{ $admin->civil_status == 'Married' ? 'selected' : '' }}>Married</option>
                        <option value="Widowed" {{ $admin->civil_status == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                        <option value="Separated" {{ $admin->civil_status == 'Separated' ? 'selected' : '' }}>Separated</option>
                    </select>
                </div>

                <div class="form-group full">
                    <label>Address</label>
                    <textarea name="address" rows="3" required>{{ old('address', $admin->address) }}</textarea>
                </div>

                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" value="{{ old('contact_number', $admin->contact_number) }}" required>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="{{ old('username', $admin->username) }}" required>
                </div>

                <div class="form-group full">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email', $admin->email) }}" required>
                </div>

            </div>

            <button type="submit" class="btn-primary">Update Profile</button>

        </form>

    </div>
</div>

@endsection

@section('scripts')
<script>
/* Avatar preview */
document.getElementById('avatar').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
        document.querySelector('.avatar-preview').src = URL.createObjectURL(file);
    }
});

/* AUTO FADE ALERTS */
document.addEventListener("DOMContentLoaded", function () {
    const alerts = document.querySelectorAll(".auto-hide");

    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add("fade-out");

            // remove element after fade animation
            setTimeout(() => {
                alert.remove();
            }, 500);

        }, 3000); // 3 seconds before fade
    });
});
</script>
@endsection