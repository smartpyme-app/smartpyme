<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnalisisVentasMensualErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $errorMessage,
    ) {
    }

    public function build()
    {
        return $this->subject('Error al generar reporte - SmartPyme')
            ->view('emails.analisis-ventas-mensual-error', [
                'errorMessage' => $this->errorMessage,
            ]);
    }
}
