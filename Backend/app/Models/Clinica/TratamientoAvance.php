<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class TratamientoAvance extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_tratamiento_avances';

    protected $fillable = [
        'id_empresa',
        'id_tratamiento',
        'fecha',
        'nota',
        'incumplimiento',
        'id_usuario',
    ];

    protected $casts = [
        'fecha' => 'date',
        'incumplimiento' => 'boolean',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }
}
