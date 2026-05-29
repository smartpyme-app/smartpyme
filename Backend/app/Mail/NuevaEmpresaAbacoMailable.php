<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Admin\Empresa;

class NuevaEmpresaAbacoMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Empresa $empresa;
    public string $nombrePropietario;
    public string $correo;
    public string $telefono;
    public string $plan;
    public string $tipoPlan;
    public string $origenRegistro;

    /**
     * @param Empresa $empresa      La empresa recién creada
     * @param string  $origenRegistro  Dominio desde donde se hizo el registro
     */
    public function __construct(Empresa $empresa, string $origenRegistro)
    {
        $this->empresa         = $empresa;
        $this->nombrePropietario = $empresa->nombre_propietario ?? 'N/D';
        $this->correo          = $empresa->correo ?? 'N/D';
        $this->telefono        = $empresa->telefono ?? 'N/D';
        $this->plan            = $empresa->plan ?? 'N/D';
        $this->tipoPlan        = $empresa->tipo_plan ?? 'N/D';
        $this->origenRegistro  = $origenRegistro;
    }

    public function build(): self
    {
        return $this
            ->subject('Nueva empresa registrada desde ÁBACO – ' . $this->empresa->nombre)
            ->view('emails.nueva-empresa-abaco');
    }
}
