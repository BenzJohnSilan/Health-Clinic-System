<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    protected AdminNotificationService $notifications;

    public function __construct(AdminNotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Mark a single notification as read.
     *
     * Security: always scoped to the authenticated admin —
     * $request->user() is the only user_id ever written, and markRead()
     * re-validates the key against the currently-generated notifications
     * before creating any read record. An admin can never mark another
     * admin's notification as read, regardless of what key is supplied.
     */
    public function markRead(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
        ]);

        $admin = $request->user();

        $ok = $this->notifications->markRead($admin, $request->input('key'));

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired notification.',
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($admin),
        ]);
    }

    /**
     * Mark every currently-unread notification as read for this admin.
     */
    public function markAllRead(Request $request)
    {
        $admin = $request->user();

        $this->notifications->markAllRead($admin);

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($admin),
        ]);
    }
}
