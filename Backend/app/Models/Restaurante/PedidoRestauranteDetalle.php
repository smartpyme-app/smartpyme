<?php

namespace App\Models\Restaurante;

use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoRestauranteDetalle extends Model
{
    protected $table = 'restaurante_pedido_detalles';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'lote_id',
        'cantidad',
        'precio',
        'descuento',
        'subtotal',
        'total',
        'notas',
        'meta_inventario',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio' => 'decimal:4',
        'descuento' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
        'meta_inventario' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoRestaurante::class, 'pedido_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
