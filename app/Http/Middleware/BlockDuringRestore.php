<?php

namespace App\Http\Middleware;

use App\Services\RestoreGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a backup is being restored, nobody may use the system: the database
 * and uploaded files are being replaced, so a transaction now could land on
 * half-restored data or be overwritten a moment later.
 *
 * Registered globally in bootstrap/app.php. Costs one file_exists() per
 * request when no restore is running.
 */
class BlockDuringRestore
{
    public function __construct(private RestoreGuard $guard)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->guard->isRestoreRunning()) {
            $message = 'The system is being restored from a backup. Please try again in a few minutes.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503, ['Retry-After' => '60']);
            }

            // Laravel's stock 503 page ignores the message, so people would only see
            // "Service Unavailable". This one explains itself and retries by itself.
            return response(
                '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<meta http-equiv="refresh" content="30"><title>System maintenance</title>'
                . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
                . 'background:#f9f6ff;font-family:Poppins,Arial,sans-serif;color:#374151}'
                . '.box{max-width:420px;margin:24px;padding:32px;background:#fff;border-radius:16px;text-align:center;'
                . 'box-shadow:0 8px 30px rgba(106,13,173,.12)}h1{font-size:20px;color:#6a0dad;margin:0 0 10px}'
                . 'p{font-size:14px;line-height:1.6;margin:0}</style></head><body><div class="box">'
                . '<h1>System maintenance in progress</h1><p>' . e($message) . '</p>'
                . '<p style="margin-top:10px;font-size:12px;color:#6b7280">This page checks again automatically.</p>'
                . '</div></body></html>',
                503,
                ['Retry-After' => '60', 'Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        return $next($request);
    }
}
