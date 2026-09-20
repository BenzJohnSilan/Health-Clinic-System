<?php

namespace App\Providers;

use App\Services\AdminNotificationService;
use App\Services\PatientNotificationService;
use App\Services\StaffNotificationService;
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
                $view->with(['adminNotifications' => collect(), 'adminUnreadCount' => 0]);
                return;
            }

            $service = app(AdminNotificationService::class);
            $notifications = $service->build($admin);

            $view->with([
                'adminNotifications' => $notifications,
                'adminUnreadCount'   => $notifications->where('unread', true)->count(),
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
    }
}