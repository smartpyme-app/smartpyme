<?php

namespace App\Mail;

use App\Models\Admin\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContadorDespachoInvitacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Empresa $empresaCliente,
        public Empresa $empresaDespacho,
        public string $nombreAdmin,
        public string $aceptarUrl,
        public string $expiraEnTexto,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Invitación de gestión contable — '.$this->empresaDespacho->nombre)
            ->view('mails.contador-despacho-invitacion');
    }
}
