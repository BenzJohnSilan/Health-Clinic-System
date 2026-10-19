<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Services\ContactMessageNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Public Contact Us form handler (POST /contact).
 * Rate limited by the "contact" limiter (see AppServiceProvider).
 */
class ContactMessageController extends Controller
{
    private const SUCCESS = 'Thank you! Your message has been sent. Our team will get back to you as soon as possible.';

    public function store(StoreContactMessageRequest $request, ContactMessageNotifier $notifier): RedirectResponse
    {
        // Honeypot: real visitors never see or fill this field. Bots do.
        // Answer exactly like a success so they learn nothing, and save nothing.
        if (filled($request->input('website'))) {
            return $this->done(self::SUCCESS);
        }

        $data = $request->validated();

        // Double-click / refresh / back-button resubmission: the same sender
        // sent the same message in the last 10 minutes -> do not store it twice.
        $duplicate = ContactMessage::query()
            ->where('email', $data['email'])
            ->where('message', $data['message'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($duplicate) {
            return $this->done(self::SUCCESS);
        }

        try {
            $contact = ContactMessage::create($data + ['status' => ContactMessage::STATUS_UNREAD]);
        } catch (Throwable $e) {
            Log::error('Contact form: could not save inquiry: ' . $e->getMessage());

            return redirect('/#contact')
                ->withInput()
                ->with('contact_error', 'Sorry, we could not send your message right now. Please try again in a few minutes.');
        }

        // The inquiry is already saved. If the email fails it is logged and
        // flagged on the Admin page for resending — the visitor still gets a
        // success message because their message WAS received.
        $notifier->send($contact);

        return $this->done(self::SUCCESS);
    }

    private function done(string $message): RedirectResponse
    {
        return redirect('/#contact')->with('contact_success', $message);
    }
}
