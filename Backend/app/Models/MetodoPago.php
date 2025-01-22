<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'metodos_pago';
    protected $fillable = [
        'id_usuario',
        'id_tarjeta',
        'marca_tarjeta',
        'ultimos_cuatro',
        'titular_tarjeta',
        'nombre_emisor',
        'codigo_pais',
        'codigo_estado',
        'codigo_postal',
        'es_predeterminado',
        'esta_activo',
    ];
}
