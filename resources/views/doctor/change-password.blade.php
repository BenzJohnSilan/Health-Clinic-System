@extends('layouts.patient')

@section('head')
<link rel="stylesheet" href="{{ asset('css/change-password.css') }}">
@endsection

@section('content')
<div class="profile-page">

    <!-- Page Header -->
    <div class="page-header">
        <h1>Change Password</h1>
    </div>

    <!-- Success Alert -->
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    <!-- Error Alerts -->
    @if($errors->any())
        <div class="alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- PROFILE CARD -->
    <div class="profile-card">

        <!-- Change Password Form -->
        <form id="changePasswordForm" action="{{ route('patient.change-password.update') }}" method="POST" class="profile-form">
            @csrf

            <div class="form-grid">

                <!-- Current Password -->
                <div class="form-group full">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <!-- New Password -->
                <div class="form-group full">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <!-- Confirm New Password -->
                <div class="form-group full">
                    <label for="password_confirmation">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required>
                </div>

            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-primary">Update Password</button>

        </form>

    </div>
</div>
@endsection