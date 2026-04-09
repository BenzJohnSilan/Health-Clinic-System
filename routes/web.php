<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerificationController;

use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Patient\PatientAppointmentController;

use App\Http\Controllers\Doctor\DoctorController;
use App\Http\Controllers\Doctor\DoctorAppointmentController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminAppointmentController;

// ✅ ADD THIS
use App\Http\Controllers\Admin\AdminPatientController;


/*
|--------------------------------------------------------------------------
| LANDING PAGE
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

// Register
Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

// Login
Route::get('/login', [LoginController::class, 'show'])
    ->name('login')
    ->middleware('guest');

Route::post('/login', [LoginController::class, 'authenticate'])
    ->name('login.authenticate')
    ->middleware('guest');

// Logout
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


/*
|--------------------------------------------------------------------------
| EMAIL VERIFICATION
|--------------------------------------------------------------------------
*/
Route::get('/email/verify', [VerificationController::class, 'notice'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');

Route::post('/email/resend', [VerificationController::class, 'resend'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.resend');


/*
|--------------------------------------------------------------------------
| PATIENT PANEL
|--------------------------------------------------------------------------
*/
Route::prefix('patient')
    ->middleware(['auth', 'verified', 'prevent-back-history'])
    ->name('patient.')
    ->group(function () {

        Route::get('/dashboard', [PatientController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [PatientController::class, 'profile'])->name('profile');
        Route::post('/profile', [PatientController::class, 'updateProfile'])->name('profile.update');

        Route::get('/change-password', [PatientController::class, 'changePassword'])->name('change-password');
        Route::post('/change-password', [PatientController::class, 'updatePassword'])->name('change-password.update');

        Route::get('/appointments', [PatientAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [PatientAppointmentController::class, 'store'])->name('appointments.store');

        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });


/*
|--------------------------------------------------------------------------
| DOCTOR PANEL
|--------------------------------------------------------------------------
*/
Route::prefix('doctor')
    ->middleware(['auth', 'verified', 'prevent-back-history'])
    ->name('doctor.')
    ->group(function () {

        Route::get('/dashboard', [DoctorController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [DoctorController::class, 'profile'])->name('profile');
        Route::post('/profile', [DoctorController::class, 'updateProfile'])->name('profile.update');

        Route::get('/change-password', [DoctorController::class, 'changePassword'])->name('change-password');
        Route::post('/change-password', [DoctorController::class, 'updatePassword'])->name('change-password.update');

        Route::get('/appointments', [DoctorAppointmentController::class, 'index'])->name('appointments.index');

        Route::put('/appointments/{id}', [DoctorAppointmentController::class, 'update'])->name('appointments.update');

        Route::get('/appointments/{id}/view', [DoctorAppointmentController::class, 'show'])->name('appointments.show');

        Route::get('/appointments/{id}/report', [DoctorAppointmentController::class, 'report'])->name('appointments.report');

        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });


/*
|--------------------------------------------------------------------------
| ADMIN PANEL
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth', 'verified', 'prevent-back-history'])
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // ===============================
        // 🔥 NEW: PATIENTS (WALK-IN SYSTEM)
        // ===============================
        Route::get('/patients', [AdminPatientController::class, 'index'])->name('patients.index');
        Route::post('/patients/store', [AdminPatientController::class, 'store'])->name('patients.store');

        // (optional old view - pwede mo na alisin later)
        // Route::get('/patients', [AdminController::class, 'patients'])->name('patients.index');

        Route::get('/patients/{id}', [AdminController::class, 'showPatient'])->name('patients.show');

        // Appointments (Admin)
        Route::get('/appointments/create/{patient_id}', [AdminAppointmentController::class, 'create'])->name('appointments.create');

        Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AdminAppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{id}/edit', [AdminAppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('/appointments/{id}', [AdminAppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{id}', [AdminAppointmentController::class, 'destroy'])->name('appointments.destroy');

        Route::get('/pending-appointments', [AdminAppointmentController::class, 'pending'])->name('pending-appointments');
        Route::post('appointments/{id}/approve', [AdminAppointmentController::class, 'approve'])->name('appointments.approve');
        Route::post('appointments/{id}/reject', [AdminAppointmentController::class, 'reject'])->name('appointments.reject');

        // Profile
        Route::get('/profile', [AdminController::class, 'profile'])->name('profile');
        Route::post('/profile/update', [AdminController::class, 'updateProfile'])->name('profile.update');

        // Change Password
        Route::get('/change-password', [AdminController::class, 'changePassword'])->name('change-password');
        Route::post('/change-password', [AdminController::class, 'updatePassword'])->name('change-password.update');

        // Users Management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Pending Accounts
        Route::get('/pending-accounts', [AdminController::class, 'pendingAccounts'])->name('pending');
        Route::post('/approve/{id}', [AdminController::class, 'approveUser'])->name('approve');
        Route::post('/reject/{id}', [AdminController::class, 'rejectUser'])->name('reject');

        // Logout
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });