<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class NotasEstadosFinancieros extends Model
{
    use HasFactory;

    protected $table = 'notas_estados_financieros';

    protected $fillable = [
        'id_empresa',
        'periodo_actual',
        'fecha_inicio',
        'fecha_fin',
        'fecha_aprobacion_junta',
        'incluir_comparativo',
        'periodo_anterior',
        'nivel_detalle',
        'notas_a_incluir',
        'configuracion',
        'contenido_manual',
        'notas_generadas',
        'completitud',
        'validaciones_cruzadas',
        'estado',
        'id_usuario_creacion',
        'id_usuario_emision',
        'fecha_emision',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_aprobacion_junta' => 'date',
        'incluir_comparativo' => 'boolean',
        'notas_a_incluir' => 'array',
        'configuracion' => 'array',
        'contenido_manual' => 'array',
        'notas_generadas' => 'array',
        'completitud' => 'array',
        'validaciones_cruzadas' => 'array',
        'fecha_emision' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
}
