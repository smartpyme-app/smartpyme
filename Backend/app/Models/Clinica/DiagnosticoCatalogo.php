<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class DiagnosticoCatalogo extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_diagnostico_catalogo';

    protected $fillable = [
        'id_empresa',
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }
}
