<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Patient\PatientAppointmentController;
use App\Http\Controllers\Patient\MedicalCertificateController as PatientMedicalCertificateController;
use App\Http\Controllers\Patient\PatientNotificationController;

use App\Http\Controllers\Doctor\DoctorController;
use App\Http\Controllers\Doctor\DoctorAppointmentController;
use App\Http\Controllers\Doctor\DoctorPatientController;
use App\Http\Controllers\Doctor\PrescriptionController;
use App\Http\Controllers\Doctor\MedicalCertificateController as DoctorMedicalCertificateController;
use App\Http\Controllers\Doctor\DoctorScheduleController;
use App\Http\Controllers\Doctor\DoctorBillingController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminAppointmentController;
use App\Http\Controllers\Admin\AdminPatientController;
use App\Http\Controllers\MedicineController as SharedMedicineController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserLogController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminNotificationController;

use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffAppointmentController;
use App\Http\Controllers\Staff\StaffPatientController;
use App\Http\Controllers\Staff\StaffBillingController;
use App\Http\Controllers\Staff\StaffNotificationController;
use App\Http\Controllers\Staff\StaffActivityLogController;
use App\Http\Controllers\Staff\StaffPrescriptionController;

use App\Http\Controllers\Patient\PatientBillingController;

/**
 * Registers the full Medicine Inventory route set for whichever role
 * group (Admin/Doctor/Staff) calls it. Defining this once instead of
 * copy-pasting it into all three ->group() closures below keeps the
 * shared module to a single source of truth for its URLs, and avoids
 * the three copies drifting out of sync as the module grows.
 *
 * Each action is gated by its own permission slug, enforced here on
 * the backend regardless of what the UI shows/hides — Admin bypasses
 * all of them via User::hasPermission(). "reports" is registered
 * before the "{medicine}" routes so it can never be misread as a
 * medicine ID by route model binding.
 */
if (!function_exists('registerMedicineInventoryRoutes')) {
function registerMedicineInventoryRoutes(): void
{
    Route::middleware('permission:view_inventory_reports')
        ->get('/medicines/reports', [SharedMedicineController::class, 'reports'])
        ->name('medicines.reports');

    Route::middleware('permission:view_medicine_inventory')
        ->get('/medicines', [SharedMedicineController::class, 'index'])
        ->name('medicines.index');

    Route::middleware('permission:add_medicine')
        ->post('/medicines', [SharedMedicineController::class, 'store'])
        ->name('medicines.store');

    Route::middleware('permission:view_medicine_inventory')
        ->get('/medicines/{medicine}', [SharedMedicineController::class, 'show'])
        ->name('medicines.show');

    Route::middleware('permission:edit_medicine')
        ->put('/medicines/{medicine}', [SharedMedicineController::class, 'update'])
        ->name('medicines.update');

    Route::middleware('permission:delete_medicine')
        ->delete('/medicines/{medicine}', [SharedMedicineController::class, 'destroy'])
        ->name('medicines.destroy');

    Route::middleware('permission:stock_in')
        ->post('/medicines/{medicine}/stock-in', [SharedMedicineController::class, 'stockIn'])
        ->name('medicines.stock-in');

    Route::middleware('permission:stock_out')
        ->post('/medicines/{medicine}/stock-out', [SharedMedicineController::class, 'stockOut'])
        ->name('medicines.stock-out');

    Route::middleware('permission:adjust_stock')
        ->post('/medicines/{medicine}/adjust-stock', [SharedMedicineController::class, 'adjustStock'])
        ->name('medicines.adjust-stock');

    Route::middleware('permission:view_stock_history')
        ->get('/medicines/{medicine}/stock-history', [SharedMedicineController::class, 'stockHistory'])
        ->name('medicines.stock-history');

    Route::middleware('permission:view_medicine_inventory')
        ->get('/medicines/{medicine}/batches', [SharedMedicineController::class, 'batches'])
        ->name('medicines.batches');

    Route::middleware('permission:edit_medicine')
        ->put('/medicines/{medicine}/batches/{batch}', [SharedMedicineController::class, 'updateBatch'])
        ->name('medicines.batches.update');
}
}

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

Route::post('/email/verify', [VerificationController::class, 'verify'])
    ->middleware('auth')
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

        // Notifications (read/unread persistence — AJAX)
        Route::post('/notifications/read', [PatientNotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/read-all', [PatientNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

        Route::get('/account-settings', [PatientController::class, 'settings'])
            ->name('settings');

        Route::put('/profile', [PatientController::class, 'updateProfile'])
            ->name('profile.update');

        // ✅ NEW — Separate avatar routes
        Route::put('/profile/avatar', [PatientController::class, 'updateAvatar'])
            ->name('profile.avatar.update');

        Route::delete('/profile/avatar', [PatientController::class, 'removeAvatar'])
            ->name('profile.avatar.remove');

        Route::put('/change-password', [PatientController::class, 'updatePassword'])
            ->name('change-password.update');

        Route::get('/appointments', [PatientAppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [PatientAppointmentController::class, 'store'])->name('appointments.store');

        Route::get('/appointments/{id}', [PatientAppointmentController::class, 'show'])
            ->name('appointments.show');

        Route::delete('/appointments/{id}/cancel', [PatientAppointmentController::class, 'cancel'])
            ->name('appointments.cancel');

        Route::get('/appointment-history', [PatientController::class, 'medicalReport'])
            ->name('appointment.history');

        Route::get('/medical-report/{id}', [PatientController::class, 'showMedicalReport'])
            ->name('medical-report.show');

        Route::get('/prescription/{id}', [PatientController::class, 'showPrescription'])
            ->name('prescription.show');

        // Medical Certificates (request → pending → issued/rejected workflow)
        Route::get('/medical-certificates', [PatientMedicalCertificateController::class, 'index'])
            ->name('medical-certificates.index');

        Route::get('/medical-certificates/request', [PatientMedicalCertificateController::class, 'create'])
            ->name('medical-certificates.request');

        // Used by the "Request Medical Certificate" modal to check
        // eligibility (completed appointment + no pending request for
        // that doctor) before showing the request form.
        Route::get('/medical-certificates/eligibility', [PatientMedicalCertificateController::class, 'eligibility'])
            ->name('medical-certificates.eligibility');

        Route::post('/medical-certificates', [PatientMedicalCertificateController::class, 'store'])
            ->name('medical-certificates.store');

        Route::get('/medical-certificates/{medicalCertificate}', [PatientMedicalCertificateController::class, 'show'])
            ->name('medical-certificates.show');

        Route::get('/medical-certificates/{medicalCertificate}/print', [PatientMedicalCertificateController::class, 'print'])
            ->name('medical-certificates.print');

        Route::post('/medical-certificates/{medicalCertificate}/correction', [PatientMedicalCertificateController::class, 'requestCorrection'])
            ->name('medical-certificates.correction.request');

        Route::get('/activity-logs', [PatientController::class, 'activityLogs'])
            ->name('activity.logs');

        // Billing & Payments (view-only — no Pay Now, no way to mark paid).
        // Ownership (patient_id === auth()->id()) is still enforced in
        // PatientBillingController itself — this middleware only gates
        // whether the module is reachable at all.
        Route::get('/billing', [PatientBillingController::class, 'index'])
            ->middleware('permission:view_billing')
            ->name('billing.index');
        Route::get('/billing/{invoice}', [PatientBillingController::class, 'show'])
            ->middleware('permission:view_billing')
            ->name('billing.show');
        Route::get('/billing/{invoice}/receipt/{payment}', [PatientBillingController::class, 'receipt'])
            ->middleware('permission:view_payment_history')
            ->name('billing.receipt');

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

        // Profile (read-only view)
        Route::get('/profile', [DoctorController::class, 'profile'])->name('profile');

        // Account Settings (combined profile + password page)
        Route::get('/account-settings', [DoctorController::class, 'accountSettings'])
            ->name('account-settings');

        Route::put('/account-settings/profile', [DoctorController::class, 'updateProfile'])
            ->name('profile.update');

        Route::put('/account-settings/avatar', [DoctorController::class, 'updateAvatar'])
            ->name('profile.avatar.update');

        Route::delete('/account-settings/avatar', [DoctorController::class, 'removeAvatar'])
            ->name('profile.avatar.remove');

        Route::put('/account-settings/password', [DoctorController::class, 'updatePassword'])
            ->name('change-password.update');

        // Appointments
        Route::get('/appointments', [DoctorAppointmentController::class, 'index'])->name('appointments.index');
        Route::put('/appointments/{id}', [DoctorAppointmentController::class, 'update'])->name('appointments.update');
        Route::get('/appointments/{id}/view', [DoctorAppointmentController::class, 'show'])->name('appointments.show');
        Route::get('/appointments/{id}/report', [DoctorAppointmentController::class, 'report'])->name('appointments.report');

        // Reschedule
        Route::patch('/appointments/{id}/reschedule', [DoctorAppointmentController::class, 'reschedule'])
            ->name('appointments.reschedule');

        // Consultation workflow: Checked In -> In Progress -> Completed
        Route::post('/appointments/{id}/start-consultation', [DoctorAppointmentController::class, 'startConsultation'])
            ->name('appointments.startConsultation');

        Route::patch('/appointments/{id}/save-draft', [DoctorAppointmentController::class, 'saveDraft'])
            ->name('appointments.saveDraft');

        // Remembers which consultation step the Doctor is currently on
        // (session only — never written to the medical record) so a
        // reload/F5 or any save/redirect keeps the same step active.
        Route::patch('/appointments/{id}/step', [DoctorAppointmentController::class, 'setStep'])
            ->name('appointments.setStep');

        Route::patch('/appointments/{id}/complete-consultation', [DoctorAppointmentController::class, 'completeConsultation'])
            ->name('appointments.completeConsultation');

        // Billing — tag the applicable service/charge (never records payment).
        // This single action both creates the invoice (first save) and
        // edits it (subsequent saves). The middleware below only checks
        // that the Doctor holds AT LEAST ONE of create_invoice/edit_invoice
        // (CheckPermission treats a comma list as ANY-of) so the route is
        // reachable at all — the controller (saveCharges) then determines
        // whether this specific call is a CREATE or an EDIT based on
        // whether the appointment already has an invoice, and requires
        // the matching permission for that operation. See
        // DoctorAppointmentController::saveCharges() for the enforcement.
        Route::patch('/appointments/{id}/charges', [DoctorAppointmentController::class, 'saveCharges'])
            ->middleware('permission:create_invoice,edit_invoice')
            ->name('appointments.saveCharges');

        // Doctor Billing — invoice/charge monitoring for the Doctor's own
        // appointments. Shares the same Invoice/Payment models and
        // BillingService as Admin/Staff/Patient Billing (see
        // DoctorBillingController). Record Payment is NOT granted by
        // default — it only opens up if Admin explicitly assigns
        // `record_payment` to this Doctor via Manage Permissions.
        Route::get('/billing', [DoctorBillingController::class, 'index'])
            ->middleware('permission:view_billing')
            ->name('billing.index');

        Route::get('/billing/{invoice}', [DoctorBillingController::class, 'show'])
            ->middleware('permission:view_billing')
            ->name('billing.show');

        Route::post('/billing/{invoice}/payments', [DoctorBillingController::class, 'storePayment'])
            ->middleware('permission:record_payment')
            ->name('billing.payments.store');

        Route::get('/billing/{invoice}/receipt/{payment}', [DoctorBillingController::class, 'receipt'])
            ->middleware('permission:view_payment_history')
            ->name('billing.receipt');

        // My Schedule (weekly availability + schedule exceptions)
        Route::get('/schedule', [DoctorScheduleController::class, 'index'])->name('schedule.index');
        Route::put('/schedule/weekly', [DoctorScheduleController::class, 'updateWeekly'])->name('schedule.weekly.update');
        Route::delete('/schedule/weekly/{day}', [DoctorScheduleController::class, 'destroyWeekly'])->name('schedule.weekly.destroy');
        Route::post('/schedule/exceptions', [DoctorScheduleController::class, 'storeException'])->name('schedule.exceptions.store');
        Route::put('/schedule/exceptions/{exception}', [DoctorScheduleController::class, 'updateException'])->name('schedule.exceptions.update');
        Route::delete('/schedule/exceptions/{exception}', [DoctorScheduleController::class, 'destroyException'])->name('schedule.exceptions.destroy');

        // Patients
        Route::get('/patient', [DoctorPatientController::class, 'index'])->name('patient');
        Route::get('/patient/{id}/records', [DoctorPatientController::class, 'showRecords'])->name('patient.records');

        // Review
        Route::post('/review/store', [DoctorAppointmentController::class, 'storeReview'])->name('review.store');

        // Medical Records
        Route::get('/medical-records', [DoctorAppointmentController::class, 'medicalRecordsIndex'])
            ->name('medical-records.index');
        Route::get('/medical-records/{appointment}', [DoctorAppointmentController::class, 'showMedicalRecord'])
            ->name('medical-records.show');

        // ================= MEDICINE INVENTORY (shared module) =================
        // Doctor access depends entirely on what Admin granted via
        // Manage Permissions — direct URL access is blocked the same
        // as the hidden sidebar link by the permission middleware below.
        // This reuses the exact same Staff/Admin controller, views, and
        // routes so there is exactly one Medicine Inventory implementation.
        registerMedicineInventoryRoutes();

        // Prescriptions
        Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        Route::delete('/prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');
        Route::get('/prescription/{appointment}/print', [PrescriptionController::class, 'print'])
            ->name('prescription.print');

        // Digital Signature (Account Settings)
        Route::put('/account-settings/signature', [DoctorController::class, 'updateSignature'])
            ->name('signature.update');
        Route::delete('/account-settings/signature', [DoctorController::class, 'removeSignature'])
            ->name('signature.remove');

        // Medical Certificates (request → review → issue workflow)
        Route::get('/medical-certificates', [DoctorMedicalCertificateController::class, 'index'])
            ->name('medical-certificates.index');

        Route::get('/medical-certificates/{medicalCertificate}', [DoctorMedicalCertificateController::class, 'show'])
            ->name('medical-certificates.show');

        Route::get('/medical-certificates/{medicalCertificate}/create', [DoctorMedicalCertificateController::class, 'create'])
            ->name('medical-certificates.create');

        Route::post('/medical-certificates/{medicalCertificate}/issue', [DoctorMedicalCertificateController::class, 'issue'])
            ->name('medical-certificates.issue');

        Route::post('/medical-certificates/{medicalCertificate}/reject', [DoctorMedicalCertificateController::class, 'reject'])
            ->name('medical-certificates.reject');

        // Direct creation for walk-in / face-to-face patients
        // (placed before the {medicalCertificate} routes below so
        // "create-direct" isn't captured as a certificate ID)
        Route::get('/medical-certificates-direct/create', [DoctorMedicalCertificateController::class, 'directIndex'])
            ->name('medical-certificates.direct.index');

        Route::post('/medical-certificates-direct', [DoctorMedicalCertificateController::class, 'directStore'])
            ->name('medical-certificates.direct.store');

        // Correction requests
        Route::get('/medical-certificates/{medicalCertificate}/correction', [DoctorMedicalCertificateController::class, 'reviewCorrection'])
            ->name('medical-certificates.correction.review');

        Route::post('/medical-certificates/{medicalCertificate}/correction/reject', [DoctorMedicalCertificateController::class, 'rejectCorrection'])
            ->name('medical-certificates.correction.reject');

        // Print
        Route::get('/medical-certificates/{medicalCertificate}/print', [DoctorMedicalCertificateController::class, 'print'])
            ->name('medical-certificates.print');

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

        // Notifications (read/unread persistence — AJAX). Mirrors the
        // Staff notification routes below, but scoped entirely to
        // admin_notification_reads via AdminNotificationController.
        Route::post('/notifications/read', [AdminNotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

        Route::get('/patients', [AdminPatientController::class, 'index'])->name('patients.index');
        Route::post('/patients/store', [AdminPatientController::class, 'store'])->name('patients.store');

        // Edit an existing patient (registered or walk-in), reached from
        // Patients → View Patient → Edit Patient — mirrors the Staff route.
        Route::put('/patients/{type}/{id}', [AdminPatientController::class, 'update'])
            ->whereIn('type', ['user', 'patient'])
            ->where('id', '[0-9]+')
            ->name('patients.update');

        // Read-only appointment history for a single patient (registered or
        // walk-in), opened from the ⋮ menu on the Patients page.
        Route::get('/patients/{type}/{id}/appointments', [AdminPatientController::class, 'appointments'])
            ->whereIn('type', ['user', 'patient'])
            ->where('id', '[0-9]+')
            ->name('patients.appointments');

        // Only reached from the Patients page's "Schedule Appointment"
        // action. This is the ONLY way an Admin creates an appointment —
        // the Admin Appointments page below is view + manage only and has
        // no "Add Appointment" action of its own.
        Route::post('/patients/appointments', [AdminPatientController::class, 'scheduleAppointment'])
            ->name('patients.appointments.store');

        Route::get('/patients/{id}', [AdminController::class, 'showPatient'])->name('patients.show');

        // Admin's single, centralized Appointments page: view + manage ALL
        // appointments (every status, via the tabs/filter below). Creation
        // stays on Patient List -> Schedule Appointment
        // (AdminPatientController::scheduleAppointment / patients.appointments.store
        // above) — there is no Add/Edit Appointment workflow here anymore.
        Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/{id}', [AdminAppointmentController::class, 'show'])->name('appointments.show');
        Route::delete('/appointments/{id}', [AdminAppointmentController::class, 'destroy'])->name('appointments.destroy');

        // Legacy URL — Pending is now a filter inside the main Appointments
        // page, not a separate sidebar page. Kept alive (bookmarks, the
        // dashboard's "Pending Appointments" alert/notification links)
        // but redirects straight to the filtered list.
        Route::get('/pending-appointments', [AdminAppointmentController::class, 'pending'])->name('pending-appointments');

        Route::post('/appointments/{id}/approve', [AdminAppointmentController::class, 'approve'])->name('appointments.approve');
        Route::post('/appointments/{id}/reject', [AdminAppointmentController::class, 'reject'])->name('appointments.reject');
        Route::post('/appointments/{id}/cancel', [AdminAppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('/appointments/{id}/reschedule', [AdminAppointmentController::class, 'reschedule'])->name('appointments.reschedule');
        Route::post('/appointments/{id}/no-show', [AdminAppointmentController::class, 'noShow'])->name('appointments.noShow');
        Route::post('/appointments/{id}/check-in', [AdminAppointmentController::class, 'checkIn'])->name('appointments.checkIn');

        Route::get('/account-settings', [AdminController::class, 'settings'])->name('settings');

        Route::get('/profile', [AdminController::class, 'profile'])->name('profile');
        Route::post('/profile/update', [AdminController::class, 'updateProfile'])->name('profile.update');

        Route::delete('/profile/remove-avatar', [AdminController::class, 'removeAvatar'])
            ->name('profile.remove-avatar');

        // Dedicated avatar-only endpoint for the Account Settings page
        // (independent from the profile form, same pattern as Patient).
        Route::put('/profile/avatar', [AdminController::class, 'updateAvatar'])
            ->name('profile.avatar.update');

        Route::get('/change-password', [AdminController::class, 'changePassword'])->name('change-password');
        Route::post('/change-password', [AdminController::class, 'updatePassword'])->name('change-password.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/pending-accounts', [AdminController::class, 'pendingAccounts'])->name('pending');
        Route::post('/approve/{id}', [AdminController::class, 'approveUser'])->name('approve');
        Route::post('/reject/{id}', [AdminController::class, 'rejectUser'])->name('reject');

        // Medicine Inventory — shared module (Admin + authorized Staff).
        // Admin bypasses these permission checks entirely (see
        // User::hasPermission()), but the middleware stays in place so
        // the same routes work identically for both panels.
        registerMedicineInventoryRoutes();

        // Manage Permissions (Admin-only — controls Doctor/Staff access)
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::put('/permissions/{user}', [PermissionController::class, 'update'])->name('permissions.update');

        Route::get('/user-logs', [UserLogController::class, 'index'])->name('user-logs');

        // Billing & Payments (monitoring — read only)
        Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing.index');
        Route::get('/billing/{invoice}', [AdminBillingController::class, 'show'])->name('billing.show');
        Route::get('/billing/{invoice}/receipt/{payment}', [AdminBillingController::class, 'receipt'])->name('billing.receipt');

        // Services / Fee structure used by Billing
        Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
        Route::post('/services', [AdminServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [AdminServiceController::class, 'update'])->name('services.update');
        Route::delete('/services/{service}', [AdminServiceController::class, 'destroy'])->name('services.destroy');

        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    });


/*
|--------------------------------------------------------------------------
| STAFF PANEL — palitan ang buong staff group mo sa web.php nito
|--------------------------------------------------------------------------
*/
Route::prefix('staff')
    ->middleware(['auth', 'verified', 'role:Staff', 'prevent-back-history'])
    ->name('staff.')
    ->group(function () {

        Route::get('/dashboard', [StaffController::class, 'dashboard'])
            ->name('dashboard');

        Route::post('/logout', [LoginController::class, 'logout'])
            ->name('logout');

        // Notifications (read/unread persistence — AJAX)
        Route::post('/notifications/read', [StaffNotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/read-all', [StaffNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

        // Every Staff Appointment route below is gated by its own
        // permission slug (Admin → Manage Permissions), enforced here on
        // the backend via the existing CheckPermission middleware —
        // regardless of what the Blade view shows/hides. Admin bypasses
        // all of them via User::hasPermission(); this group is Staff-only
        // (role:Staff above), so that bypass never leaks these routes to
        // any other role. `view_appointments` is the basic/default
        // permission every Staff account always has (see
        // Permission::defaultSlugsForRole()), so this gate never
        // actually blocks a normal Staff account — it exists so a
        // manually-crafted request still can't bypass it.
        Route::get('/appointments', [StaffAppointmentController::class, 'index'])
            ->middleware('permission:view_appointments')
            ->name('appointments.index');

        // Only reached from the Patients page's "Schedule Appointment"
        // action (never from the general Appointments page, which is
        // read-only management of existing appointments), so gating it on
        // `schedule_patient_appointment` here is safe and correct.
        Route::post('/appointments', [StaffAppointmentController::class, 'store'])
            ->middleware('permission:schedule_patient_appointment')
            ->name('appointments.store');

        Route::get('/appointments/{appointment}', [StaffAppointmentController::class, 'show'])
            ->middleware('permission:view_appointment_details')
            ->name('appointments.show');

        // Legacy URL — redirects to Appointments filtered by Pending.
        // Kept so old bookmarks/links/notifications don't break. Gated
        // the same as the page it redirects to.
        Route::get('/pending-appointments', [StaffAppointmentController::class, 'pending'])
            ->middleware('permission:view_appointments')
            ->name('pending-appointments');
        Route::post('/appointments/{id}/approve', [StaffAppointmentController::class, 'approve'])
            ->middleware('permission:approve_appointment')
            ->name('appointments.approve');
        Route::post('/appointments/{id}/reject', [StaffAppointmentController::class, 'reject'])
            ->middleware('permission:reject_appointment')
            ->name('appointments.reject');
        Route::post('/appointments/{id}/cancel', [StaffAppointmentController::class, 'cancel'])
            ->middleware('permission:cancel_appointment')
            ->name('appointments.cancel');
        Route::post('/appointments/{id}/reschedule', [StaffAppointmentController::class, 'reschedule'])
            ->middleware('permission:reschedule_appointment')
            ->name('appointments.reschedule');
        Route::post('/appointments/{id}/no-show', [StaffAppointmentController::class, 'noShow'])
            ->middleware('permission:mark_no_show_appointment')
            ->name('appointments.noShow');
        Route::post('/appointments/{id}/check-in', [StaffAppointmentController::class, 'checkIn'])
            ->middleware('permission:check_in_appointment')
            ->name('appointments.checkIn');

        Route::patch('/prescriptions/{prescription}/dispense', [StaffPrescriptionController::class, 'dispense'])
            ->middleware('permission:dispense_prescription')
            ->name('prescriptions.dispense');

        // Every Patient Management route below is gated by its own
        // permission slug (Admin → Manage Permissions), enforced here on
        // the backend via the existing CheckPermission middleware —
        // regardless of what the Blade view shows/hides. Admin bypasses
        // all of them via User::hasPermission(); this group is Staff-only
        // (role:Staff above), so that bypass never leaks these routes to
        // any other role.
        Route::get('/patients', [StaffPatientController::class, 'index'])
            ->middleware('permission:view_patients')
            ->name('patients.index');

        Route::post('/patients/store', [StaffPatientController::class, 'store'])
            ->middleware('permission:add_walk_in_patient')
            ->name('patients.store');

        // Edit an existing patient (registered or walk-in), reached from
        // Patients → View Patient → Edit Patient. Scoped the same way as
        // the appointments route below so a Staff member can never edit a
        // record outside the Patients list.
        Route::put('/patients/{type}/{id}', [StaffPatientController::class, 'update'])
            ->whereIn('type', ['user', 'patient'])
            ->where('id', '[0-9]+')
            ->middleware('permission:edit_patient')
            ->name('patients.update');

        // Read-only appointment history for a single patient (registered or
        // walk-in), opened from the ⋮ menu on the Patients page.
        Route::get('/patients/{type}/{id}/appointments', [StaffPatientController::class, 'appointments'])
            ->whereIn('type', ['user', 'patient'])
            ->where('id', '[0-9]+')
            ->middleware('permission:view_patient_appointments')
            ->name('patients.appointments');

        // ================= ACTIVITY LOGS =================
        // Staff-only, and scoped to the authenticated Staff account in the
        // controller itself (never trust the URL/query string for this).
        Route::get('/activity-logs', [StaffActivityLogController::class, 'index'])
            ->name('activity-logs');

        Route::get('/account-settings', [StaffController::class, 'settings'])
            ->name('settings');

                // ================= PROFILE =================
        Route::get('/profile', [StaffController::class, 'profile'])
            ->name('profile');

        Route::put('/profile', [StaffController::class, 'updateProfile'])
            ->name('profile.update');

        Route::get('/change-password', [StaffController::class, 'changePassword'])
            ->name('change-password');

        Route::put('/change-password', [StaffController::class, 'updatePassword'])
            ->name('change-password.update');

        // ================= BILLING & PAYMENTS =================
        Route::get('/billing', [StaffBillingController::class, 'index'])
            ->middleware('permission:view_billing')
            ->name('billing.index');

        Route::get('/billing/{invoice}', [StaffBillingController::class, 'show'])
            ->middleware('permission:view_billing')
            ->name('billing.show');

        Route::post('/billing/{invoice}/payments', [StaffBillingController::class, 'storePayment'])
            ->middleware('permission:record_payment')
            ->name('billing.payments.store');

        Route::get('/billing/{invoice}/receipt/{payment}', [StaffBillingController::class, 'receipt'])
            ->middleware('permission:view_payment_history')
            ->name('billing.receipt');

        // ================= MEDICINE INVENTORY (shared module) =================
        // Staff access depends entirely on what Admin granted via
        // Manage Permissions — direct URL access is blocked the same
        // as hidden buttons by the permission middleware below.
        registerMedicineInventoryRoutes();
    });