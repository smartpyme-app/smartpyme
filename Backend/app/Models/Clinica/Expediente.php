<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class Expediente extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_expedientes';

    protected $fillable = [
        'id_empresa',
        'id_paciente',
        'numero',
        'fecha_apertura',
        'estado',
    ];

    protected $casts = [
        'fecha_apertura' => 'date',
        'numero' => 'integer',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }
}
