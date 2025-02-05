<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'planes';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'precio',
        'duracion_dias',
        'activo',
        'enlace_n1co',
        'caracteristicas',
        'id_enlace_pago_n1co',
        'n1co_metadata',
        'permite_periodo_prueba',
        'dias_periodo_prueba'
    ];

    protected $casts = [
        'caracteristicas' => 'array',
        'activo' => 'boolean',
        'precio' => 'decimal:2',
        'n1co_metadata' => 'array'
    ];

    protected $appends = ['sku'];

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }

    public function getTipoPlanAttribute()
    {
        if ($this->duracion_dias == 30) {
            return 'Mensual';
        }
        if ($this->duracion_dias == 90) {
            return 'Trimestral';
        }
        if ($this->duracion_dias == 180) {
            return 'Semestral';
        }
    }

    public function getSkuAttribute()
    {
        return 'PLAN-' . $this->id;
    }
}
