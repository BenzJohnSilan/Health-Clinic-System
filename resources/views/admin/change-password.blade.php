@extends('layouts.admin')

@section('head')
<link rel="stylesheet" href="{{ asset('css/change-password.css') }}">
@endsection

@section('content')
<div class="change-password-page">

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

    <!-- Change Password Form -->
    <form id="changePasswordForm" action="{{ route('admin.change-password.update') }}" method="POST" class="change-password-form">
        @csrf

        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>

        <!-- Submit Button at the bottom -->
        <button type="submit" class="btn-submit">Update Password</button>
    </form>

</div>
@endsection
