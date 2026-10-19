<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notification sent to the clinic when someone submits the Contact Us form.
 *
 * The visitor's address is only ever used as Reply-To. The From address is
 * always the application's configured MAIL_FROM_ADDRESS, so a visitor can
 * never make the message look like it came from someone else.
 */
class ContactMessageReceived extends Mailable
{
    public function __construct(public ContactMessage $contact)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact->email, $this->contact->full_name)],
            subject: 'New Contact Inquiry from ' . $this->contact->full_name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
