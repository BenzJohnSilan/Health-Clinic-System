<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Patient\PatientAppointmentController;

use App\Http\Controllers\Doctor\DoctorController;
use App\Http\Controllers\Doctor\DoctorAppointmentController;
use App\Http\Controllers\Doctor\DoctorPatientController;
use App\Http\Controllers\Doctor\MedicineController;
use App\Http\Controllers\Doctor\PrescriptionController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminAppointmentController;
use App\Http\Controllers\Admin\AdminPatientController;
use App\Http\Controllers\Admin\AdminMedicineController;
use App\Http\Controllers\Admin\UserLogController;


/*
|--------------------------------------------------------------------------
| LANDING PAGE
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('landing');
})->name('landing');


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::get('/login', [LoginController::class, 'show'])
    ->name('login')
    ->middleware('guest');

Route::post('/login', [LoginController::class, 'authenticate'])
    ->name('login.authenticate')
    ->middleware('guest');


/*
|--------------------------------------------------------------------------
| FORGOT PASSWORD
|--------------------------------------------------------------------------
*/
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForgotForm'])
    ->name('password.request');

Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
    ->name('password.email');

/*
|--------------------------------------------------------------------------
| RESET PASSWORD
|--------------------------------------------------------------------------
*/
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
    ->name('password.reset');

Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
    ->name('password.update');

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
    ->middleware(['auth', 'verified', 'role:Patient', 'prevent-back-history'])
    ->name('patient.')
    ->group(function () {

        Route::get('/dashboard', [PatientController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [PatientController::class, 'profile'])->name('profile');
        Route::post('/profile', [PatientController::class, 'updateProfile'])->name('profile.update');

        Route::get('/change-password', [PatientController::class, 'changePassword'])->name('change-password');
        Route::post('/change-password', [PatientController::class, 'updatePassword'])->name('change-password.update');

        Route::get('/appointments', [PatientAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [PatientAppointmentController::class, 'store'])->name('appointments.store');

        // Cancel appointment
        Route::delete('/appointments/{id}/cancel', [PatientAppointmentController::class, 'cancel'])
            ->name('appointments.cancel');

        Route::get('/medical-report', [PatientController::class, 'medicalReport'])
            ->name('medical.report');
        
        Route::get('/medical-report/{id}', [PatientController::class, 'showMedicalReport'])
            ->name('medical-report.show');

        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });


/*
|--------------------------------------------------------------------------
| DOCTOR PANEL
|--------------------------------------------------------------------------
*/
Route::prefix('doctor')
    ->middleware(['auth', 'verified', 'role:Doctor', 'prevent-back-history'])
    ->name('doctor.')
    ->group(function () {

        Route::get('/dashboard', [DoctorController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [DoctorController::class, 'profile'])->name('profile');
        Route::post('/profile', [DoctorController::class, 'updateProfile'])->name('profile.update');

        Route::get('/change-password', [DoctorController::class, 'changePassword'])->name('change-password');
        Route::post('/change-password', [DoctorController::class, 'updatePassword'])->name('change-password.update');

        // Appointments
        Route::get('/appointments', [DoctorAppointmentController::class, 'index'])->name('appointments.index');
        Route::put('/appointments/{id}', [DoctorAppointmentController::class, 'update'])->name('appointments.update');
        Route::get('/appointments/{id}/view', [DoctorAppointmentController::class, 'show'])->name('appointments.show');
        Route::get('/appointments/{id}/report', [DoctorAppointmentController::class, 'report'])->name('appointments.report');

        // Reschedule
        Route::patch('/appointments/{id}/reschedule', [DoctorAppointmentController::class, 'reschedule'])
            ->name('appointments.reschedule');

        // 🩺 Diagnosis
        Route::patch('/appointments/{id}/diagnosis', [DoctorAppointmentController::class, 'saveDiagnosis'])
            ->name('appointments.saveDiagnosis');

        Route::get('/patient', [DoctorPatientController::class, 'index'])
            ->name('patient');
        
        // View specific patient medical records
        Route::get('/patient/{id}/records', [DoctorPatientController::class, 'showRecords'])
            ->name('patient.records');

        // Review
        Route::post('/review/store', [DoctorAppointmentController::class, 'storeReview'])
            ->name('review.store');

        // Medicine Inventory (View Only)
        Route::get('/medicines', [MedicineController::class, 'index'])->name('medicines.index');

        // Prescriptions
        Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');

        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });


/*
|--------------------------------------------------------------------------
| ADMIN PANEL
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth', 'verified', 'role:Admin', 'prevent-back-history'])
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        Route::get('/patients', [AdminPatientController::class, 'index'])->name('patients.index');
        Route::post('/patients/store', [AdminPatientController::class, 'store'])->name('patients.store');

        Route::get('/patients/{id}', [AdminController::class, 'showPatient'])->name('patients.show');

        Route::get('/appointments/create/{patient_id}', [AdminAppointmentController::class, 'create'])->name('appointments.create');

        Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AdminAppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{id}/edit', [AdminAppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('/appointments/{id}', [AdminAppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{id}', [AdminAppointmentController::class, 'destroy'])->name('appointments.destroy');

        Route::get('/pending-appointments', [AdminAppointmentController::class, 'pending'])->name('pending-appointments');
        Route::post('/appointments/{id}/approve', [AdminAppointmentController::class, 'approve'])->name('appointments.approve');
        Route::post('/appointments/{id}/reject', [AdminAppointmentController::class, 'reject'])->name('appointments.reject');

        Route::get('/profile', [AdminController::class, 'profile'])->name('profile');
        Route::post('/profile/update', [AdminController::class, 'updateProfile'])->name('profile.update');

        Route::delete('/profile/remove-avatar', [AdminController::class, 'removeAvatar'])
            ->name('profile.remove-avatar');

        Route::get('/change-password', [AdminController::class, 'changePassword'])->name('change-password');
        Route::post('/change-password', [AdminController::class, 'updatePassword'])->name('change-password.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/pending-accounts', [AdminController::class, 'pendingAccounts'])->name('pending');
        Route::post('/approve/{id}', [AdminController::class, 'approveUser'])->name('approve');
        Route::post('/reject/{id}', [AdminController::class, 'rejectUser'])->name('reject');

        // Medicine Inventory (Full CRUD)
        Route::get('/medicines', [AdminMedicineController::class, 'index'])->name('medicines.index');
        Route::post('/medicines', [AdminMedicineController::class, 'store'])->name('medicines.store');
        Route::put('/medicines/{medicine}', [AdminMedicineController::class, 'update'])->name('medicines.update');
        Route::delete('/medicines/{medicine}', [AdminMedicineController::class, 'destroy'])->name('medicines.destroy');

        Route::get('/user-logs', [UserLogController::class, 'index'])->name('user-logs');

        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });