<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocional extends Model
{
    use HasFactory;

    protected $table = 'promocionales';
    protected $fillable = [
        'codigo',
        'descuento',
        'tipo',
        'activo',
        'campania',
        'aliado',
        'descripcion',
        'planes_permitidos',
        'opciones',
    ];

    protected $casts = [
        'opciones' => 'array',
        'planes_permitidos' => 'array',
    ];
    
}
