<?php

namespace App\Models\Restaurante;

use App\Models\Inventario\Producto;
use App\Models\Restaurante\PedidoDetalleLote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoRestauranteDetalle extends Model
{
    protected $table = 'restaurante_pedido_detalles';

    protected $fillable = [
        'pedido_id',
        'producto_id',
        'id_paquete',
        'lote_id',
        'cantidad',
        'precio',
        'descuento',
        'subtotal',
        'total',
        'notas',
        'meta_inventario',
        'enviado_cocina',
        'enviado_barra',
    ];

    protected $casts = [
        'id_paquete' => 'integer',
        'cantidad' => 'decimal:4',
        'precio' => 'decimal:4',
        'descuento' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
        'meta_inventario' => 'array',
        'enviado_cocina' => 'boolean',
        'enviado_barra' => 'boolean',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoRestaurante::class, 'pedido_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function paquete(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventario\Paquete::class, 'id_paquete');
    }

    public function loteAsignaciones()
    {
        return $this->hasMany(PedidoDetalleLote::class, 'pedido_detalle_id');
    }
}
