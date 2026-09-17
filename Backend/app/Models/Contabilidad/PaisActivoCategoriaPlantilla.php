<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class PaisActivoCategoriaPlantilla extends Model
{
    protected $table = 'pais_activos_categorias_plantilla';

    protected $fillable = [
        'cod_pais',
        'nombre',
        'metodo_depreciacion',
        'porcentaje_anual',
        'vida_util_anios',
        'valor_residual_default',
        'permite_bien_usado',
        'reglas_bien_usado',
        'activo',
    ];

    protected $casts = [
        'porcentaje_anual' => 'float',
        'vida_util_anios' => 'float',
        'valor_residual_default' => 'float',
        'permite_bien_usado' => 'boolean',
        'reglas_bien_usado' => 'array',
        'activo' => 'boolean',
    ];
}
