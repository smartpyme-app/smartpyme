<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrdenCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $orden;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($orden)
    {
        $this->orden = $orden;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Diana´s Gallery')->view('mails.ordenes.nueva');
    }
}
