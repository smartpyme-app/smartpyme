<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JWTAuth;

class ActivoMovimiento extends Model
{
    protected $table = 'empresa_activos_movimientos';

    protected $fillable = [
        'id_activo',
        'id_empresa',
        'id_usuario',
        'tipo',
        'fecha',
        'descripcion',
        'monto',
        'metadata',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'float',
        'metadata' => 'array',
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
