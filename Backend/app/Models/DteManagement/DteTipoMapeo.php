<?php

namespace App\Models\DteManagement;

use Illuminate\Database\Eloquent\Model;

class DteTipoMapeo extends Model
{
    protected $table = 'dte_tipo_mapeo';

    protected $fillable = [
        'cod_pais',
        'codigo_mh',
        'nombre_tipo',
        'tipo_documento',
        'destino',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public static function getByCodigo(string $codigoMh, string $codPais = 'SV'): ?self
    {
        return static::where('codigo_mh', $codigoMh)
            ->where('cod_pais', $codPais)
            ->where('activo', true)
            ->first();
    }
}
