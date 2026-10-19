<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactReplyRequest;
use App\Mail\ContactMessageReply;
use App\Models\ContactMessage;
use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveService;
use App\Services\ContactMessageNotifier;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Admin > Contact Messages. Reached only through the admin route group
 * (auth + verified + role:Admin), so Patient/Staff/Doctor accounts and
 * visitors never get here.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['unread', 'read'], true) ? $request->query('status') : 'all';
        $notice = in_array($request->query('notice'), ['sent', 'failed'], true) ? $request->query('notice') : '';
        $search = trim((string) $request->query('search'));

        $messages = ContactMessage::query()
            ->when($status === 'unread', fn ($q) => $q->unread())
            ->when($status === 'read', fn ($q) => $q->read())
            ->when($notice === 'sent', fn ($q) => $q->whereNotNull('notified_at'))
            ->when($notice === 'failed', fn ($q) => $q->whereNull('notified_at'))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('first_name', 'like', $like)
                      ->orWhere('last_name', 'like', $like)
                      ->orWhere('email', 'like', $like)
                      ->orWhere('contact_number', 'like', $like)
                      ->orWhere('message', 'like', $like);
                });
            })
            ->withCount('replies')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.contact-messages.index', [
            'messages'    => $messages,
            'status'      => $status,
            'notice'      => $notice,
            'search'      => $search,
            'totalCount'  => ContactMessage::count(),
            'unreadCount' => ContactMessage::unread()->count(),
        ]);
    }

    public function show(ContactMessage $contactMessage)
    {
        // Opening a message reads it. "Mark as unread" puts it back.
        $contactMessage->markAsRead();

        return view('admin.contact-messages.show', [
            'message' => $contactMessage,
            'replies' => $contactMessage->replies()->with('sender')->get(),
        ]);
    }

    public function markRead(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->markAsRead();

        return back()->with('success', 'Message marked as read.');
    }

    public function markUnread(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->markAsUnread();

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('success', 'Message marked as unread.');
    }

    public function resend(ContactMessage $contactMessage, ContactMessageNotifier $notifier): RedirectResponse
    {
        if ($notifier->send($contactMessage)) {
            return back()->with('success', 'Email notification sent.');
        }

        return back()->with('error', 'The email notification could not be sent. Please check the mail settings and try again.');
    }

    /**
     * Email a reply to the sender. The reply is recorded only after the mail
     * server accepted it; on a failure the Admin's text stays in the form.
     */
    public function reply(StoreContactReplyRequest $request, ContactMessage $contactMessage, ContactMessageNotifier $notifier): RedirectResponse
    {
        $data = $request->validated();
        $back = route('admin.contact-messages.show', $contactMessage) . '#reply';

        // Double-click / refresh guard: the identical reply within 2 minutes is not sent twice.
        $duplicate = $contactMessage->replies()
            ->where('subject', $data['subject'])
            ->where('body', $data['body'])
            ->where('sent_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($duplicate) {
            return redirect($back)->with('error', 'This exact reply was just sent. It was not sent again.');
        }

        try {
            Mail::to($contactMessage->email, $contactMessage->full_name)->send(new ContactMessageReply(
                $contactMessage,
                $data['subject'],
                $data['body'],
                SystemSettings::clinicName(),
                $notifier->recipient(),
                trim((string) SystemSettings::get('clinic_phone')) ?: null,
            ));
        } catch (Throwable $e) {
            Log::error('Reply to contact inquiry #' . $contactMessage->id . ' failed: ' . $e->getMessage());

            return redirect($back)
                ->withInput()
                ->with('error', 'The reply could not be sent. Your text was kept below; please check the mail settings and try again.');
        }

        $contactMessage->replies()->create([
            'user_id' => $request->user()?->id,
            'subject' => $data['subject'],
            'body'    => $data['body'],
            'sent_at' => now(),
        ]);

        return redirect($back)->with('success', 'Reply sent to ' . $contactMessage->email . '.');
    }

    /**
     * Archive an inquiry (it used to be deleted on the spot). The message and
     * its reply history are kept; it leaves this list and the unread badge.
     * Restore or permanently delete it from Admin > Archive.
     */
    public function destroy(Request $request, ContactMessage $contactMessage, ArchiveService $archive): RedirectResponse
    {
        try {
            $archive->archive('contact_message', $contactMessage->id, $request->user());
        } catch (ArchiveException $e) {
            return redirect()->route('admin.contact-messages.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.contact-messages.index')
            ->with('success', 'Message moved to the Archive.');
    }
}
