<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class EnvioPantalla extends Model
{
    protected $table = 'restaurante_envio_pantalla';

    protected $fillable = [
        'pantalla_id',
        'orden_detalle_id',
        'pedido_detalle_id',
    ];

    public function pantalla()
    {
        return $this->belongsTo(PantallaRestaurante::class, 'pantalla_id');
    }
}
