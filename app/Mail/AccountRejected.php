<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class AccountRejected extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $reason; // bagong property para sa reason

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param string $reason
     */
    public function __construct(User $user, $reason)
    {
        $this->user = $user;
        $this->reason = $reason;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your Account Has Been Rejected')
                    ->view('emails.account-rejected')
                    ->with([
                        'user' => $this->user,
                        'reason' => $this->reason,
                    ]);
    }
}