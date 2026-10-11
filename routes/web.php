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
use App\Http\Controllers\Patient\LaboratoryResultController as PatientLaboratoryResultController;

use App\Http\Controllers\Doctor\DoctorController;
use App\Http\Controllers\Doctor\DoctorAppointmentController;
use App\Http\Controllers\Doctor\DoctorPatientController;
use App\Http\Controllers\Doctor\PrescriptionController;
use App\Http\Controllers\Doctor\MedicalCertificateController as DoctorMedicalCertificateController;
use App\Http\Controllers\Staff\StaffMedicalCertificateController;
use App\Http\Controllers\Doctor\DoctorScheduleController;
use App\Http\Controllers\Doctor\DoctorBillingController;
use App\Http\Controllers\Doctor\DoctorNotificationController;
use App\Http\Controllers\Doctor\LaboratoryRequestController as DoctorLaboratoryRequestController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminAppointmentController;
use App\Http\Controllers\Admin\AdminPatientController;
use App\Http\Controllers\MedicineController as SharedMedicineController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BackupRestoreController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminBillingController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\ValidIdController;

use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Staff\StaffAppointmentController;
use App\Http\Controllers\Staff\StaffPatientController;
use App\Http\Controllers\Staff\StaffBillingController;
use App\Http\Controllers\Staff\StaffNotificationController;
use App\Http\Controllers\Staff\StaffActivityLogController;
use App\Http\Controllers\Staff\StaffPrescriptionController;
use App\Http\Controllers\Staff\StaffLaboratoryRequestController;

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
Route::get('/', [\App\Http\Controllers\LandingController::class, 'index'])->name('landing');


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

// Registration is for visitors only (signed-in users are sent to their dashboard).
// The POST is rate limited per IP — see RateLimiter::for('register') in AppServiceProvider.
Route::get('/register', [RegisterController::class, 'show'])
    ->middleware('guest')
    ->name('register');

Route::post('/register', [RegisterController::class, 'store'])
    ->middleware(['guest', 'throttle:register'])
    ->name('register.store');

// Preview of the Valid ID this visitor already uploaded in their own session
// (private disk; no id/path in the URL).
Route::get('/register/valid-id-preview', [RegisterController::class, 'stagedValidIdPreview'])
    ->middleware(['guest', 'throttle:30,1'])
    ->name('register.valid-id-preview');

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
    ->middleware(['auth', 'verified', 'registration.complete', 'role:Patient', 'prevent-back-history'])
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

        // Consultation Summary — Appointment History → View. Patient-facing
        // view of one of the patient's OWN Completed consultations;
        // ownership + Completed status are enforced in the controller.
        Route::get('/appointment-history/{id}', [PatientController::class, 'showConsultationSummary'])
            ->whereNumber('id')
            ->name('appointment.show');

        // Legacy URL — the separate Patient "Medical Report" page no longer
        // exists. Kept so old bookmarks don't 404: the controller redirects
        // straight to the Consultation Summary above.
        Route::get('/medical-report/{id}', [PatientController::class, 'showMedicalReport'])
            ->name('medical-report.show');

        Route::get('/medical-report/{id}/print', [PatientController::class, 'printMedicalReport'])
            ->name('medical-report.print');

        Route::get('/prescription/{id}', [PatientController::class, 'showPrescription'])
            ->name('prescription.show');

        // Medical Certificates — READ-ONLY history. A Medical Certificate is
        // never requested online: the Doctor creates it at the clinic, the
        // patient pays at the counter and receives the signed paper copy
        // from Staff. Only certificates already RELEASED are listed here;
        // there is no request, correction or print route for the patient.
        Route::get('/medical-certificates', [PatientMedicalCertificateController::class, 'index'])
            ->name('medical-certificates.index');

        Route::get('/medical-certificates/{medicalCertificate}', [PatientMedicalCertificateController::class, 'show'])
            ->name('medical-certificates.show');

        // Laboratory Results (Section 6A of the Laboratory workflow
        // spec) — Patient can only ever see/upload against their OWN
        // laboratory requests; ownership is enforced in the controller
        // itself, this route just makes the module reachable.
        Route::get('/laboratory-results', [PatientLaboratoryResultController::class, 'index'])
            ->name('laboratory-results.index');
        Route::post('/laboratory-results/{laboratoryRequest}/upload', [PatientLaboratoryResultController::class, 'upload'])
            ->name('laboratory-results.upload');
        Route::delete('/laboratory-results/{laboratoryRequest}/results/{result}', [PatientLaboratoryResultController::class, 'removeResult'])
            ->name('laboratory-results.remove');
        // Replace an already-uploaded result for a specific requested test
        // (Section 4 of the Laboratory Result workflow spec) — a single
        // action that uploads the correct file and retires the wrong one,
        // without the Patient ever seeing both as "current" at once.
        Route::post('/laboratory-results/{laboratoryRequest}/results/{result}/replace', [PatientLaboratoryResultController::class, 'replaceResult'])
            ->name('laboratory-results.replace');

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

        // Notifications (read/unread persistence — AJAX). Mirrors the
        // Admin/Staff/Patient notification routes, scoped entirely to
        // doctor_notification_reads via DoctorNotificationController.
        Route::post('/notifications/read', [DoctorNotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notifications/read-all', [DoctorNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

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

        // ================= LABORATORY REQUESTS / RESULTS =================
        // Core Doctor workflow (create + review), same as Appointments /
        // Medical Records — gated only by role:Doctor above, never by a
        // permission slug, so it never depends on the Staff
        // 'upload_laboratory_result' permission.
        Route::post('/appointments/{id}/laboratory-request', [DoctorLaboratoryRequestController::class, 'store'])
            ->name('laboratory-requests.store');
        Route::get('/laboratory-requests', [DoctorLaboratoryRequestController::class, 'index'])
            ->name('laboratory-requests.index');
        Route::get('/laboratory-requests/{laboratoryRequest}', [DoctorLaboratoryRequestController::class, 'show'])
            ->name('laboratory-requests.show');
        Route::get('/laboratory-requests/{laboratoryRequest}/print', [DoctorLaboratoryRequestController::class, 'print'])
            ->name('laboratory-requests.print');
        Route::post('/laboratory-requests/{laboratoryRequest}/review', [DoctorLaboratoryRequestController::class, 'review'])
            ->name('laboratory-requests.review');
        Route::post('/laboratory-requests/{laboratoryRequest}/request-correction', [DoctorLaboratoryRequestController::class, 'requestCorrection'])
            ->name('laboratory-requests.request-correction');
        // Correct / remove a request saved by mistake — only while it is
        // still Awaiting Result (guarded in the controller).
        Route::put('/laboratory-requests/{laboratoryRequest}', [DoctorLaboratoryRequestController::class, 'update'])
            ->name('laboratory-requests.update');
        Route::delete('/laboratory-requests/{laboratoryRequest}', [DoctorLaboratoryRequestController::class, 'destroy'])
            ->name('laboratory-requests.destroy');

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

        // Medical Certificates — created by the Doctor from a COMPLETED
        // consultation (never requested online by the patient). Saving the
        // form issues the certificate and attaches its invoice; the fee is
        // paid at Staff → Billing & Payments, the Doctor prints it once paid
        // and signs the paper copy, and Staff releases it.
        //
        // NOTE: the fixed "/create" path is declared before the
        // {medicalCertificate} routes so it isn't captured as a certificate ID.
        Route::get('/medical-certificates', [DoctorMedicalCertificateController::class, 'index'])
            ->name('medical-certificates.index');

        Route::get('/medical-certificates/create', [DoctorMedicalCertificateController::class, 'create'])
            ->name('medical-certificates.create');

        Route::post('/medical-certificates', [DoctorMedicalCertificateController::class, 'store'])
            ->name('medical-certificates.store');

        Route::get('/medical-certificates/{medicalCertificate}', [DoctorMedicalCertificateController::class, 'show'])
            ->name('medical-certificates.show');

        // Corrections of an already-issued certificate
        Route::get('/medical-certificates/{medicalCertificate}/correction', [DoctorMedicalCertificateController::class, 'reviewCorrection'])
            ->name('medical-certificates.correction.review');

        Route::get('/medical-certificates/{medicalCertificate}/correction/edit', [DoctorMedicalCertificateController::class, 'editCorrection'])
            ->name('medical-certificates.correction.edit');

        Route::post('/medical-certificates/{medicalCertificate}/correction', [DoctorMedicalCertificateController::class, 'updateCorrection'])
            ->name('medical-certificates.correction.update');

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

        // Valid ID of a registrant — streamed from private storage to Admins only.
        Route::get('/users/{user}/valid-id', [ValidIdController::class, 'show'])
            ->whereNumber('user')
            // PreventBackHistory only understands Illuminate responses; this one
            // is a file stream that already sends its own no-store headers.
            ->withoutMiddleware('prevent-back-history')
            ->middleware('throttle:120,1')
            ->name('users.valid-id');

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

        // Website Management (Admin-only) — edits the public landing page
        // content (see WebsiteManagementController / LandingPageContent).
        // Same gate as the rest of this group: auth + verified + role:Admin.
        Route::prefix('website')->name('website.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\WebsiteManagementController::class, 'index'])->name('index');
            Route::put('/settings/{group}', [\App\Http\Controllers\Admin\WebsiteManagementController::class, 'updateSettings'])
                ->where('group', '[a-z]+')->name('settings.update');
            Route::post('/items', [\App\Http\Controllers\Admin\WebsiteManagementController::class, 'storeItem'])->name('items.store');
            Route::put('/items/{item}', [\App\Http\Controllers\Admin\WebsiteManagementController::class, 'updateItem'])->whereNumber('item')->name('items.update');
            Route::post('/items/{item}/toggle', [\App\Http\Controllers\Admin\WebsiteManagementController::class, 'toggleItem'])->whereNumber('item')->name('items.toggle');
            Route::post('/items/{item}/move/{direction}', [\App\Http\Controllers\Admin\WebsiteManagementController::class, 'moveItem'])
                ->whereNumber('item')->whereIn('direction', ['up', 'down'])->name('items.move');
            Route::delete('/items/{item}', [\App\Http\Controllers\Admin\WebsiteManagementController::class, 'destroyItem'])->whereNumber('item')->name('items.destroy');
        });

        // Backup & Recovery (Admin-only — see BackupController). Each throttle has
        // its own prefix: without one, Laravel shares a single counter per user
        // across every throttled route. Download is
        // by record id, never by filename/path; it opts out of
        // prevent-back-history only because that middleware cannot wrap a
        // streamed file response (the controller sends the same no-cache headers).
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])
            ->middleware('throttle:5,1,backup-create')
            ->name('backups.store');
        Route::get('/backups/{backup}/download', [BackupController::class, 'download'])
            ->whereNumber('backup')
            ->middleware('throttle:20,1,backup-download')
            ->withoutMiddleware('prevent-back-history')
            ->name('backups.download');

        // Restore (Phase 4). By record id only; typed confirmation is checked
        // server-side; the POST is rate limited with its own counter.
        Route::get('/backups/{backup}/restore', [BackupRestoreController::class, 'show'])
            ->whereNumber('backup')
            ->name('backups.restore');
        Route::post('/backups/{backup}/restore', [BackupRestoreController::class, 'run'])
            ->whereNumber('backup')
            ->middleware('throttle:3,1,backup-restore')
            ->name('backups.restore.run');

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
        // any other role. `view_appointments` is part of the standard
        // Staff default (see Permission::defaultSlugsForRole()), but it is
        // NOT guaranteed: a Staff account created with the Cashier preset
        // (billing permissions only), or any Staff Admin has restricted in
        // Manage Permissions, does not have it — and this gate is what
        // keeps such an account out even if it types the URL by hand.
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

        // Add an EXISTING patient to today's Walk-in Queue (Patients page ->
        // action menu -> Add to Queue). Creates one 'Walk-in' visit with no
        // reserved time; reuses the existing walk-in intake permission.
        Route::post('/walk-in-queue', [StaffAppointmentController::class, 'addToQueue'])
            ->middleware('permission:add_walk_in_patient')
            ->name('walk-in-queue.store');

        // Remove a still-Waiting walk-in from the queue (silent: no patient
        // email/SMS). Same permission as adding to the queue.
        Route::post('/walk-in-queue/{id}/remove', [StaffAppointmentController::class, 'removeFromQueue'])
            ->where('id', '[0-9]+')
            ->middleware('permission:add_walk_in_patient')
            ->name('walk-in-queue.remove');

        // Staff records Vital Signs (before Check-In) on the appointment's
        // existing medical record. The Doctor only ever VIEWS them.
        Route::post('/appointments/{id}/vital-signs', [StaffAppointmentController::class, 'saveVitalSigns'])
            ->middleware('permission:record_vital_signs')
            ->name('appointments.vitalSigns.save');

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

        Route::put('/profile/avatar', [StaffController::class, 'updateAvatar'])
            ->name('profile.avatar.update');

        Route::delete('/profile/avatar', [StaffController::class, 'removeAvatar'])
            ->name('profile.avatar.remove');

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

        // Per-item discount — applied BEFORE payment, same counter permission.
        Route::post('/billing/{invoice}/discounts', [StaffBillingController::class, 'updateDiscounts'])
            ->middleware('permission:record_payment')
            ->name('billing.discounts.update');

        Route::post('/billing/{invoice}/payments', [StaffBillingController::class, 'storePayment'])
            ->middleware('permission:record_payment')
            ->name('billing.payments.store');

        Route::get('/billing/{invoice}/receipt/{payment}', [StaffBillingController::class, 'receipt'])
            ->middleware('permission:view_payment_history')
            ->name('billing.receipt');

        // ================= MEDICAL CERTIFICATES (release only) =================
        Route::get('/medical-certificates', [StaffMedicalCertificateController::class, 'index'])
            ->middleware('permission:view_medical_certificates')
            ->name('medical-certificates.index');

        Route::post('/medical-certificates/{medicalCertificate}/release', [StaffMedicalCertificateController::class, 'release'])
            ->middleware('permission:release_medical_certificate')
            ->name('medical-certificates.release');

        // ================= LABORATORY REQUESTS / RESULTS =================
        // Staff can only ever VIEW the queue and UPLOAD a result file —
        // there is no review/interpret action anywhere in this
        // controller. Neither permission is granted by default to any
        // Staff account; Admin must explicitly grant them via Manage
        // Permissions, same as every other module here.
        Route::get('/laboratory-requests', [StaffLaboratoryRequestController::class, 'index'])
            ->middleware('permission:view_laboratory_requests')
            ->name('laboratory-requests.index');
        Route::post('/laboratory-requests/{laboratoryRequest}/upload', [StaffLaboratoryRequestController::class, 'upload'])
            ->middleware('permission:upload_laboratory_result')
            ->name('laboratory-requests.upload');
        // Replacing an incorrect result is the same authorized action as
        // uploading one in the first place (Section 11 of the workflow
        // spec) — reuses the same permission rather than inventing a new one.
        Route::post('/laboratory-requests/{laboratoryRequest}/results/{result}/replace', [StaffLaboratoryRequestController::class, 'replaceResult'])
            ->middleware('permission:upload_laboratory_result')
            ->name('laboratory-requests.replace');

        // ================= MEDICINE INVENTORY (shared module) =================
        // Staff access depends entirely on what Admin granted via
        // Manage Permissions — direct URL access is blocked the same
        // as hidden buttons by the permission middleware below.
        registerMedicineInventoryRoutes();
    });