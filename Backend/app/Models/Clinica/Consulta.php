<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class Consulta extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_consultas';

    protected $fillable = [
        'id_empresa',
        'id_expediente',
        'id_paciente',
        'id_sucursal',
        'id_usuario_profesional',
        'id_usuario_registro',
        'fecha',
        'hora',
        'motivo',
        'estado',
        'anamnesis',
        'antecedentes',
        'examen_fisico',
        'observaciones',
        'indicaciones',
        'signos_vitales',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'signos_vitales' => 'array',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class, 'id_paciente');
    }

    public function expediente()
    {
        return $this->belongsTo(Expediente::class, 'id_expediente');
    }
}
