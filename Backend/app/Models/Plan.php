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
        'caracteristicas'
    ];

    protected $casts = [
        'caracteristicas' => 'array',
        'activo' => 'boolean',
        'precio' => 'decimal:2'
    ];

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function estaActivo(): bool
    {
        return $this->activo;
    }
}
