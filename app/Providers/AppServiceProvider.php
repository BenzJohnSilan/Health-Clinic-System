<?php

namespace App\Providers;

use App\Services\AdminNotificationService;
use App\Services\DoctorNotificationService;
use App\Services\PatientNotificationService;
use App\Services\StaffNotificationService;
use App\Services\SystemSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ===================== SESSION TIMEOUT (System Settings) =====================
        // Applies the Admin-configured idle timeout to session.lifetime
        // before the session driver is resolved by StartSession, so the
        // server-side session expiry and the cookie both honor it. With no
        // saved value the server's SESSION_LIFETIME is left untouched.
        SystemSettings::applySessionLifetime();

        // ===================== REGISTRATION RATE LIMIT =====================
        // Per IP: 5 attempts a minute and 20 an hour (failed validations count
        // too). Plenty for a patient fixing typos; far too slow for bots
        // creating accounts or spamming OTP emails.
        RateLimiter::for('register', function (Request $request) {
            $tooMany = fn () => back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['registration' => 'Too many registration attempts. Please wait a few minutes and try again.']);

            return [
                Limit::perMinute(5)->by('register:minute:' . $request->ip())->response($tooMany),
                Limit::perHour(20)->by('register:hour:' . $request->ip())->response($tooMany),
            ];
        });

        // ===================== CONTACT FORM RATE LIMIT =====================
        // Public Contact Us form, per IP: 3 a minute and 10 an hour (failed
        // validations count too). Enough for a real visitor fixing a typo,
        // far too slow for a bot flooding the clinic's inbox.
        RateLimiter::for('contact', function (Request $request) {
            $tooMany = fn () => redirect('/#contact')
                ->withInput()
                ->with('contact_error', 'You are sending messages too quickly. Please wait a few minutes and try again.');

            return [
                Limit::perMinute(3)->by('contact:minute:' . $request->ip())->response($tooMany),
                Limit::perHour(10)->by('contact:hour:' . $request->ip())->response($tooMany),
            ];
        });

        // ===================== PASSWORD POLICY =====================
        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });

        // ===================== ADMIN LAYOUT VIEW COMPOSER =====================
        // Injects $adminNotifications (each with a persistent 'unread'
        // flag from the database) and $adminUnreadCount into every view
        // that extends layouts.admin — so the notification bell/panel
        // works the same way regardless of which admin page is loaded.
        // Completely independent from the Patient/Staff composers above:
        // its own service, its own read-state table
        // (admin_notification_reads).
        //
        // NOTE: this used to inject raw $pendingAccounts / $pendingAppointments
        // collections here under those exact names — which collided with
        // the *integer* $pendingAppointments the Dashboard controller
        // passes to admin.dashboard.blade.php (the composer's data wins at
        // render time, silently replacing the int with a Collection).
        // Renaming these avoids that collision as well.
        View::composer('layouts.admin', function ($view) {
            $admin = auth()->user();

            if (!$admin) {
                $view->with(['adminNotifications' => collect(), 'adminUnreadCount' => 0, 'contactUnreadCount' => 0]);
                return;
            }

            $service = app(AdminNotificationService::class);
            $notifications = $service->build($admin);

            // Unread Contact Us inquiries for the sidebar badge. Wrapped so an
            // un-run migration can never break every Admin page.
            try {
                $contactUnread = \App\Models\ContactMessage::unread()->count();
            } catch (\Throwable $e) {
                $contactUnread = 0;
            }

            $view->with([
                'adminNotifications'   => $notifications,
                'adminUnreadCount'     => $notifications->where('unread', true)->count(),
                'contactUnreadCount'   => $contactUnread,
            ]);
        });

        // ===================== PATIENT LAYOUT VIEW COMPOSER =====================
        // Injects $patientNotifications (each with a persistent 'unread'
        // flag from the database) and $patientUnreadCount into every view
        // that extends layouts.patient — so the notification bell/panel
        // works the same way regardless of which patient page is loaded.
        View::composer('layouts.patient', function ($view) {
            $patient = auth()->user();

            if (!$patient) {
                $view->with(['patientNotifications' => collect(), 'patientUnreadCount' => 0]);
                return;
            }

            $service = app(PatientNotificationService::class);
            $notifications = $service->build($patient);

            $view->with([
                'patientNotifications' => $notifications,
                'patientUnreadCount'   => $notifications->where('unread', true)->count(),
            ]);
        });

        // ===================== STAFF LAYOUT VIEW COMPOSER =====================
        // Injects $staffNotifications (each with a persistent 'unread' flag
        // from the database) and $staffUnreadCount into every view that
        // extends layouts.staff — so the notification bell/panel works the
        // same way regardless of which staff page is loaded. Completely
        // independent from the Patient notification composer above: its
        // own service, its own read-state table (staff_notification_reads).
        View::composer('layouts.staff', function ($view) {
            $staff = auth()->user();

            if (!$staff) {
                $view->with(['staffNotifications' => collect(), 'staffUnreadCount' => 0]);
                return;
            }

            $service = app(StaffNotificationService::class);
            $notifications = $service->build($staff);

            $view->with([
                'staffNotifications' => $notifications,
                'staffUnreadCount'   => $notifications->where('unread', true)->count(),
            ]);
        });

        // ===================== DOCTOR LAYOUT VIEW COMPOSER =====================
        // Injects $doctorNotifications (each with a persistent 'unread' flag
        // from the database) and $doctorUnreadCount into every view that
        // extends layouts.doctor — so the notification bell/panel works the
        // same way regardless of which doctor page is loaded. Completely
        // independent from the Admin/Staff/Patient composers: its own
        // service, its own read-state table (doctor_notification_reads),
        // and every source query is filtered server-side by the
        // authenticated doctor's id.
        View::composer('layouts.doctor', function ($view) {
            $doctor = auth()->user();

            if (!$doctor) {
                $view->with(['doctorNotifications' => collect(), 'doctorUnreadCount' => 0]);
                return;
            }

            $service = app(DoctorNotificationService::class);
            $notifications = $service->build($doctor);

            $view->with([
                'doctorNotifications' => $notifications,
                'doctorUnreadCount'   => $notifications->where('unread', true)->count(),
            ]);
        });
    }
}