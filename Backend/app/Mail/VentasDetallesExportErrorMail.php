<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VentasDetallesExportErrorMail extends Mailable
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
        return $this->subject('Error al Generar Reporte de Detalles de Ventas - SmartPyme')
                    ->view('emails.ventas-detalles-export-error')
                    ->with(['errorMessage' => $this->errorMessage]);
    }
}

