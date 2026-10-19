<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An Admin's reply to a Contact Us inquiry, sent from the admin panel.
 *
 * From is always the application's configured MAIL_FROM_ADDRESS. Reply-To is
 * the clinic inbox (when configured) so the sender's answer comes back to the
 * clinic instead of the system's no-reply style sending account.
 */
class ContactMessageReply extends Mailable
{
    public function __construct(
        public ContactMessage $contact,
        public string $replySubject,
        public string $replyBody,
        public string $clinicName,
        public ?string $clinicEmail = null,
        public ?string $clinicPhone = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: $this->clinicEmail ? [new Address($this->clinicEmail, $this->clinicName)] : [],
            subject: $this->replySubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-reply');
    }
}
