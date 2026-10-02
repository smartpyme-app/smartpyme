<?php

namespace App\Models\Clinica;

use App\Models\Admin\Sucursal;
use App\Models\Clinica\Concerns\AlcanceEmpresaClinica;
use App\Models\Concerns\AuditableModel;
use App\Models\User;

class Profesional extends AuditableModel
{
    use AlcanceEmpresaClinica;

    protected $table = 'clinica_profesionales';

    protected $fillable = [
        'id_empresa',
        'id_usuario',
        'cargo',
        'especialidad',
        'colegiatura',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $auditExclude = [
        'colegiatura',
    ];

    protected static function auditModule(): string
    {
        return 'clinica';
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function sucursales()
    {
        return $this->belongsToMany(
            Sucursal::class,
            'clinica_profesional_sucursales',
            'id_profesional',
            'id_sucursal'
        );
    }
}
