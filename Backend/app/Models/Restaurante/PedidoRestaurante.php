<?php

namespace App\Models\Restaurante;

use App\Models\Admin\Empresa;
use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta as VentaModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PedidoRestaurante extends Model
{
    protected $table = 'restaurante_pedidos';

    protected $fillable = [
        'id_empresa',
        'id_sucursal',
        'id_bodega',
        'usuario_id',
        'fecha',
        'canal',
        'referencia_externa',
        'estado',
        'id_venta',
        'cliente_id',
        'observaciones',
        'subtotal',
        'descuento',
        'total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'subtotal' => 'decimal:4',
        'descuento' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(PedidoRestauranteDetalle::class, 'pedido_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(VentaModel::class, 'id_venta');
    }
}
