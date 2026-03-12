<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Funcionalidad extends Model
{
    use HasFactory;

    protected $table = 'funcionalidades';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'icono',
        'orden'
    ];

    /**
     * Relación con las empresas que tienen esta funcionalidad
     */
    public function empresas()
    {
        return $this->belongsToMany(
            Empresa::class,
            'empresa_funcionalidades',
            'id_funcionalidad',
            'id_empresa'
        )->withPivot('activo', 'configuracion');
    }

    /**
     * Relación con los registros de EmpresaFuncionalidad
     */
    public function empresaFuncionalidades()
    {
        return $this->hasMany(EmpresaFuncionalidad::class, 'id_funcionalidad');
    }
}
