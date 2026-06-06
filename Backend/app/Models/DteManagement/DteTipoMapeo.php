<?php

namespace App\Models\DteManagement;

use Illuminate\Database\Eloquent\Model;

class DteTipoMapeo extends Model
{
    protected $table = 'dte_tipo_mapeo';

    protected $fillable = [
        'codigo_mh',
        'nombre_tipo',
        'tipo_documento',
        'destino',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get mapping for a DTE code.
     *
     * @param string $codigoMh
     * @return self|null
     */
    public static function getByCodigo(string $codigoMh): ?self
    {
        return static::where('codigo_mh', $codigoMh)
            ->where('activo', true)
            ->first();
    }
}
