<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class Tratamiento extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_tratamientos';

    protected $fillable = [
        'id_empresa',
        'id_expediente',
        'id_paciente',
        'id_consulta',
        'id_usuario_profesional',
        'id_usuario_registro',
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'frecuencia',
        'duracion',
        'indicaciones',
        'estado',
        'motivo_suspension',
        'motivo_cierre',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function avances()
    {
        return $this->hasMany(TratamientoAvance::class, 'id_tratamiento');
    }

    public function terapias()
    {
        return $this->hasMany(TratamientoTerapia::class, 'id_tratamiento');
    }
}
