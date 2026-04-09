<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class AppointmentStatusMail extends Mailable
{
    public $appointment;
    public $status;

    public function __construct($appointment, $status)
    {
        $this->appointment = $appointment;
        $this->status = $status;
    }

    public function build()
    {
        return $this->subject('Appointment ' . $this->status)
                    ->view('emails.appointment-status');
    }
}