<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JWTAuth;

class ActivoDepreciacion extends Model
{
    protected $table = 'empresa_activos_depreciaciones';

    protected $fillable = [
        'id_activo',
        'id_empresa',
        'periodo',
        'monto',
        'depreciacion_acumulada',
        'valor_en_libros',
        'estado',
        'id_egreso',
    ];

    protected $casts = [
        'monto' => 'float',
        'depreciacion_acumulada' => 'float',
        'valor_en_libros' => 'float',
    ];

    protected static function booted(): void
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable) {
            $usuario = null;
        }

        if ($usuario) {
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(Activo::class, 'id_activo');
    }
}
