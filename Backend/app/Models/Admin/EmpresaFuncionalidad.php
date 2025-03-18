<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpresaFuncionalidad extends Model
{
    use HasFactory;

    protected $table = 'empresa_funcionalidades';

    protected $fillable = [
        'id_empresa',
        'id_funcionalidad',
        'activo',
        'configuracion'
    ];

    protected $casts = [
        'configuracion' => 'array',
        'activo' => 'boolean'
    ];

    /**
     * Relación con la empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Relación con la funcionalidad
     */
    public function funcionalidad()
    {
        return $this->belongsTo(Funcionalidad::class, 'id_funcionalidad');
    }
}