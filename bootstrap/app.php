<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();