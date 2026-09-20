<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Services\PatientNotificationService;
use Illuminate\Http\Request;

class PatientNotificationController extends Controller
{
    protected PatientNotificationService $notifications;

    public function __construct(PatientNotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Mark a single notification as read.
     *
     * Security: always scoped to the authenticated patient — auth()->id()
     * is the only user_id ever written, and markRead() re-validates the
     * key against THIS patient's own currently-generated notifications
     * before creating any read record. A patient can never mark another
     * patient's notification as read, regardless of what key is supplied.
     */
    public function markRead(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:255',
        ]);

        $patient = $request->user();

        $ok = $this->notifications->markRead($patient, $request->input('key'));

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired notification.',
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($patient),
        ]);
    }

    /**
     * Mark every currently-unread notification as read for this patient.
     */
    public function markAllRead(Request $request)
    {
        $patient = $request->user();

        $this->notifications->markAllRead($patient);

        return response()->json([
            'success'      => true,
            'unread_count' => $this->notifications->unreadCount($patient),
        ]);
    }
}
