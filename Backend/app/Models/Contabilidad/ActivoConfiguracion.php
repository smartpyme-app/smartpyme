<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class ActivoConfiguracion extends Model
{
    protected $table = 'empresa_activos_configuracion';

    protected $fillable = [
        'id_empresa',
        'frecuencia',
        'dia_corte',
        'redondeo_decimales',
    ];

    protected $casts = [
        'dia_corte' => 'integer',
        'redondeo_decimales' => 'integer',
    ];

    public static function defaults(): array
    {
        return [
            'frecuencia' => 'mensual',
            'dia_corte' => 1,
            'redondeo_decimales' => 2,
        ];
    }

    public static function forEmpresa(int $empresaId): self
    {
        return static::firstOrCreate(
            ['id_empresa' => $empresaId],
            static::defaults()
        );
    }
}
