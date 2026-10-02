<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class Responsable extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_responsables';

    protected $fillable = [
        'id_empresa',
        'nombre',
        'documento',
        'telefono',
        'correo',
    ];

    protected $auditExclude = [
        'documento',
        'telefono',
        'correo',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }
}
