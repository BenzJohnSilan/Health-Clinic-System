@extends('layouts.staff')

@section('head')
<link rel="stylesheet" href="{{ asset('css/change-password.css') }}">
@endsection

@section('content')
<div class="profile-page">

    <div class="page-header">
        <h1>Change Password</h1>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="profile-card">

        <form id="changePasswordForm"
              action="{{ route('staff.change-password.update') }}"
              method="POST"
              class="profile-form">

            @csrf

            <div class="form-grid">

                <div class="form-group full">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>

                <div class="form-group full">
                    <label>New Password</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group full">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirmation" required>
                </div>

            </div>

            <button type="submit" class="btn-primary">Update Password</button>

        </form>

    </div>
</div>
@endsection