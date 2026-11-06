<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class VacunaCatalogo extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_vacuna_catalogo';

    protected $fillable = [
        'id_empresa',
        'nombre',
        'tipo',
        'tipo_paciente',
        'id_especie',
        'intervalo_dias',
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
