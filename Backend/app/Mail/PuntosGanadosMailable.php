<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PuntosGanadosMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $datosEmail;

    /**
     * Create a new message instance.
     *
     * @param array $datosEmail
     */
    public function __construct(array $datosEmail)
    {
        $this->datosEmail = $datosEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $empresa = $this->datosEmail['empresa'];
        $puntosGanados = $this->datosEmail['puntos_ganados'];
        
        $fromName = $empresa->nombre ?? config('app.name');
        $fromEmail = config('mail.from.address');
        
        return $this->from($fromEmail, $fromName)
                    ->subject("¡Has ganado {$puntosGanados} puntos en {$empresa->nombre}!")
                    ->view('emails.puntos-ganados')
                    ->with([
                        'cliente' => $this->datosEmail['cliente'],
                        'empresa' => $this->datosEmail['empresa'],
                        'venta' => $this->datosEmail['venta'],
                        'puntos_ganados' => $this->datosEmail['puntos_ganados'],
                        'puntos_disponibles' => $this->datosEmail['puntos_disponibles'],
                        'fecha_venta' => $this->datosEmail['fecha_venta'],
                        'numero_venta' => $this->datosEmail['numero_venta'],
                    ]);
    }

}
