<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Services\DoctorNotificationService;
use Illuminate\Http\Request;

class DoctorNotificationController extends Controller
{
    protected DoctorNotificationService $notifications;

    public function __construct(DoctorNotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Mark a single notification as read.
     *
     * Security: always scoped to the authenticated doctor —
     * $request->user() is the only user_id ever written, and markRead()
     * re-validates the key against the currently-generated notifications
     * before creating any read record. A doctor can never mark
     * another doctor's notification as read, regardless of what key
     * is supplied.
     */
    public function markRead(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
        ]);

        $doctor = $request->user();

        $ok = $this->notifications->markRead($doctor, $request->input('key'));

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired notification.',
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($doctor),
        ]);
    }

    /**
     * Mark every currently-unread notification as read for this doctor.
     */
    public function markAllRead(Request $request)
    {
        $doctor = $request->user();

        $this->notifications->markAllRead($doctor);

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($doctor),
        ]);
    }
}
