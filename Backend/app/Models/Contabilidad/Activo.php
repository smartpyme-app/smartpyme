<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JWTAuth;

class Activo extends Model
{
    protected $table = 'empresa_activos';

    protected $fillable = [
        'nombre',
        'referencia',
        'fecha_compra',
        'fecha_retiro',
        'estado',
        'id_categoria',
        'numero_de_serie',
        'descripcion',
        'ubicacion',
        'vida_util',
        'valor_compra',
        'depreciacion_acumulada',
        'valor_en_libros',
        'valor_residual',
        'es_usado',
        'porcentaje_base_usado',
        'fecha_inicio_depreciacion',
        'estado_registro',
        'metadata',
        'id_egreso',
        'id_compra_detalle',
        'id_responsable',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'fecha_retiro' => 'date',
        'fecha_inicio_depreciacion' => 'date',
        'vida_util' => 'float',
        'valor_compra' => 'float',
        'depreciacion_acumulada' => 'float',
        'valor_en_libros' => 'float',
        'valor_residual' => 'float',
        'es_usado' => 'boolean',
        'porcentaje_base_usado' => 'float',
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

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(ActivoCategoria::class, 'id_categoria');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo('App\Models\User', 'id_responsable');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function depreciaciones()
    {
        return $this->hasMany(ActivoDepreciacion::class, 'id_activo');
    }

    public function movimientos()
    {
        return $this->hasMany(ActivoMovimiento::class, 'id_activo');
    }

    /** ponytail: recalcular en modelo; Fase 2 DepreciacionService tomará el control */
    public function recalcularValorEnLibros(): void
    {
        $acumulada = (float) ($this->depreciacion_acumulada ?? 0);
        $this->valor_en_libros = max(0, (float) $this->valor_compra - $acumulada);
    }
}
