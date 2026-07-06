<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class PedidoDetalleLote extends Model
{
    protected $table = 'pedido_detalle_lotes';

    protected $fillable = [
        'pedido_detalle_id',
        'lote_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function detalle()
    {
        return $this->belongsTo(PedidoRestauranteDetalle::class, 'pedido_detalle_id');
    }

    public function lote()
    {
        return $this->belongsTo('App\Models\Inventario\Lote', 'lote_id');
    }
}
