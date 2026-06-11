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

    protected static function booted()
    {
        static::saved(function ($empresaFuncionalidad) {
            $empresaFuncionalidad->clearFidelizacionCache();
        });

        static::deleted(function ($empresaFuncionalidad) {
            $empresaFuncionalidad->clearFidelizacionCache();
        });
    }

    /**
     * Limpiar el cache de fidelización de la empresa
     */
    public function clearFidelizacionCache()
    {
        // Si no está cargada la relación, la cargamos para verificar el slug
        $funcionalidad = $this->funcionalidad;
        if ($funcionalidad && $funcionalidad->slug === 'fidelizacion-clientes') {
            cache()->forget("empresa_fidelizacion_{$this->id_empresa}");
        }
    }

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