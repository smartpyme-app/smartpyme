<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;
use App\Models\Ventas\Clientes\Cliente;

class PacienteResponsable extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_paciente_responsables';

    protected $fillable = [
        'id_empresa',
        'id_paciente',
        'id_cliente',
        'id_responsable',
        'rol',
        'es_principal',
        'es_el_paciente',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'es_el_paciente' => 'boolean',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function persona()
    {
        return $this->belongsTo(Responsable::class, 'id_responsable');
    }
}
