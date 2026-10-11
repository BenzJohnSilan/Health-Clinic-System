<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        // Register route middleware aliases
        $middleware->alias([
            'prevent-back-history' => \App\Http\Middleware\PreventBackHistory::class,

            // existing guest middleware (your override)
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,

            // ✅ ADD THIS: role-based middleware
            'role' => \App\Http\Middleware\CheckRole::class,

            // Granular feature-permission middleware (Manage Permissions)
            'permission' => \App\Http\Middleware\CheckPermission::class,

            // Patient panel gate: verified email + Admin-approved + active
            'registration.complete' => \App\Http\Middleware\EnsureRegistrationComplete::class,
        ]);

        // Backup & Recovery (Phase 4): nobody works with the system while a
        // backup is being restored. Costs one file_exists() when idle.
        $middleware->prepend(\App\Http\Middleware\BlockDuringRestore::class);

    })

    ->withExceptions(function (Exceptions $exceptions): void {

        // 419 Page Expired (CSRF token mismatch) -> friendly redirect instead
        // of the raw error page. Laravel converts TokenMismatchException into
        // an HttpException(419) before render callbacks run, so we match on
        // the status code.
        //
        // Double-click on login/register lands here: we go back to the form,
        // and if the first request already logged the user in, the `guest`
        // middleware sends them straight to their dashboard.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null; // let Laravel handle everything else normally
            }

            return redirect()
                ->back()
                ->withInput($request->except([
                    '_token',
                    'password',
                    'password_confirmation',
                    'current_password',
                ]))
                ->with('error', 'Your session expired. Please try again.');
        });

    })
    ->create();