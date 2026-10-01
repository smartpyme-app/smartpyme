<?php

namespace App\Models\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;

class Paciente extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_pacientes';

    protected $fillable = [
        'id_empresa',
        'id_sucursal',
        'id_usuario',
        'tipo',
        'activo',
        'nombres',
        'apellidos',
        'nombre',
        'fecha_nacimiento',
        'sexo',
        'documento',
        'telefono',
        'correo',
        'direccion',
        'informacion_relevante',
        'id_especie',
        'id_raza',
        'color',
        'peso',
        'microchip',
        'esterilizado',
        'identificadores',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_nacimiento' => 'date',
        'esterilizado' => 'boolean',
        'peso' => 'decimal:2',
    ];

    protected $auditExclude = [
        'documento',
        'telefono',
        'correo',
        'direccion',
        'informacion_relevante',
        'microchip',
        'identificadores',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function expediente()
    {
        return $this->hasOne(Expediente::class, 'id_paciente');
    }

    public function especie()
    {
        return $this->belongsTo(Especie::class, 'id_especie');
    }

    public function raza()
    {
        return $this->belongsTo(Raza::class, 'id_raza');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }
}
