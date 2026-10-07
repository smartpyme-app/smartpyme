<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class TratamientoTerapia extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_tratamiento_terapias';

    protected $fillable = [
        'id_empresa',
        'id_tratamiento',
        'fecha',
        'tipo',
        'descripcion',
        'notas_resultado',
        'id_usuario',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }
}
