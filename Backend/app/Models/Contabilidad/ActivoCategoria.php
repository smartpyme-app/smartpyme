<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use JWTAuth;

class ActivoCategoria extends Model
{
    protected $table = 'empresa_activos_categorias';

    protected $fillable = [
        'nombre',
        'id_empresa',
        'plantilla_id',
        'metodo_depreciacion',
        'porcentaje_anual',
        'vida_util_anios',
        'valor_residual_default',
        'permite_bien_usado',
        'reglas_bien_usado',
        'metadata_schema',
    ];

    protected $casts = [
        'porcentaje_anual' => 'float',
        'vida_util_anios' => 'float',
        'valor_residual_default' => 'float',
        'permite_bien_usado' => 'boolean',
        'reglas_bien_usado' => 'array',
        'metadata_schema' => 'array',
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

    public function activos(): HasMany
    {
        return $this->hasMany(Activo::class, 'id_categoria');
    }
}
