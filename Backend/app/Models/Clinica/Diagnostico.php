<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class Diagnostico extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_diagnosticos';

    protected $fillable = [
        'id_empresa',
        'id_expediente',
        'id_paciente',
        'id_consulta',
        'id_sucursal',
        'id_usuario_profesional',
        'id_usuario_registro',
        'id_diagnostico_anterior',
        'codigo',
        'descripcion',
        'rol',
        'fecha',
        'estado',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }
}
