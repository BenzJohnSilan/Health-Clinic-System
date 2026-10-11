<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a patient's Valid ID to an authenticated Admin.
 *
 * The file lives on the private disk (no public URL). Access is decided here,
 * on the server: the route sits behind auth + role:Admin and the controller
 * re-checks the role, so changing the {user} id in the URL, or being logged in
 * as a Patient/Doctor/Staff, never reveals someone else's ID.
 */
class ValidIdController extends Controller
{
    public function show(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()?->role === 'Admin', 403);

        $path = $user->valid_id;

        // The stored value must be exactly what registration generates:
        // valid_ids/<single file name>. Anything else (empty, nested, "..") is refused.
        abort_unless(
            is_string($path) && preg_match('#^valid_ids/[A-Za-z0-9._-]+$#', $path) === 1 && !str_contains($path, '..'),
            404
        );

        $disk = Storage::disk('private');

        // Records created before the private disk existed may still have their
        // file in the old public location until `php artisan clinic:secure-valid-ids`
        // is run. They are served from there through this same authorized route.
        if (!$disk->exists($path)) {
            $legacy = Storage::disk('public');

            abort_unless($legacy->exists($path), 404);

            $disk = $legacy;
        }

        $isPdf = str_ends_with(strtolower($path), '.pdf');

        return $disk->response($path, null, [
            'Cache-Control'           => 'private, no-store, max-age=0',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => $isPdf
                ? "default-src 'none'; style-src 'unsafe-inline'"
                : "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
