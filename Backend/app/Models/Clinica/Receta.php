<?php

namespace App\Models\Clinica;

use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receta extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_recetas';

    protected $fillable = [
        'id_empresa',
        'id_expediente',
        'id_paciente',
        'id_consulta',
        'id_tratamiento',
        'id_sucursal',
        'id_usuario_profesional',
        'id_usuario_registro',
        'fecha',
        'estado',
        'motivo_anulacion',
        'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(RecetaLinea::class, 'id_receta')->orderBy('orden');
    }
}
