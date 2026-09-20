<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\StaffNotificationService;
use Illuminate\Http\Request;

class StaffNotificationController extends Controller
{
    protected StaffNotificationService $notifications;

    public function __construct(StaffNotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Mark a single notification as read.
     *
     * Security: always scoped to the authenticated staff member —
     * $request->user() is the only user_id ever written, and markRead()
     * re-validates the key against the currently-generated notifications
     * before creating any read record. A staff member can never mark
     * another staff member's notification as read, regardless of what key
     * is supplied.
     */
    public function markRead(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
        ]);

        $staff = $request->user();

        $ok = $this->notifications->markRead($staff, $request->input('key'));

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired notification.',
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($staff),
        ]);
    }

    /**
     * Mark every currently-unread notification as read for this staff member.
     */
    public function markAllRead(Request $request)
    {
        $staff = $request->user();

        $this->notifications->markAllRead($staff);

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($staff),
        ]);
    }
}
