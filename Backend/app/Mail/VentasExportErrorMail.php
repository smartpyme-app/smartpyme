<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VentasExportErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $errorMessage;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($errorMessage)
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Error al Generar Reporte de Ventas - SmartPyme')
                    ->view('emails.ventas-export-error', [
                        'errorMessage' => $this->errorMessage
                    ]);
    }
}

