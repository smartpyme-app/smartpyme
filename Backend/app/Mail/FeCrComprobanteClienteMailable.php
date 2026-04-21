<?php

namespace App\Mail;

use App\Models\Admin\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notificación al cliente/receptor con PDF, XML del comprobante (clave 50 dígitos) y XML de respuesta de Hacienda CR.
 */
class FeCrComprobanteClienteMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $filasCorreo
     */
    public function __construct(
        public Empresa $empresa,
        public string $nombreDestinatario,
        public array $filasCorreo,
        public string $asunto,
        public string $pdfBinary,
        public string $nombrePdf,
        public string $xmlComprobante,
        public string $nombreXmlComprobante,
        public string $xmlRespuestaHacienda,
        public string $nombreXmlRespuesta,
    ) {}

    public function build(): self
    {
        $fromAddress = config('mail.from.address', 'noreply@smartpyme.sv');
        $fromName = $this->empresa->nombre ?: (config('mail.from.name') ?: 'SmartPyME');

        return $this->from($fromAddress, $fromName)
            ->subject($this->asunto)
            ->view('mails.fe-cr-comprobante-cliente')
            ->with([
                'empresa' => $this->empresa,
                'nombreDestinatario' => $this->nombreDestinatario,
                'filas' => $this->filasCorreo,
            ])
            ->attachData($this->pdfBinary, $this->nombrePdf, ['mime' => 'application/pdf'])
            ->attachData($this->xmlComprobante, $this->nombreXmlComprobante, ['mime' => 'application/xml'])
            ->attachData($this->xmlRespuestaHacienda, $this->nombreXmlRespuesta, ['mime' => 'application/xml']);
    }
}
