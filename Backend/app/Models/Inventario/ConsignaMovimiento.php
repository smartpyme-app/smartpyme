<?php

namespace App\Models\Inventario;

use App\Models\Admin\Empresa;
use App\Models\User;
use App\Models\Compras\Compra;
use App\Models\Compras\Detalle as DetalleCompra;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ConsignaMovimiento extends Model
{
    protected $table = 'consigna_movimientos';

    protected $fillable = [
        'id_empresa',
        'fecha',
        'tipo',
        'cantidad',
        'id_producto',
        'id_bodega',
        'id_proveedor',
        'id_cliente',
        'costo_unitario',
        'id_compra',
        'id_detalle_compra',
        'id_venta',
        'id_detalle_venta',
        'id_traslado',
        'nota',
        'idempotency_key',
        'id_usuario',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'cantidad' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
    ];

    public function scopeForEmpresa(Builder $query, int $idEmpresa): Builder
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'id_bodega');
    }

    public function proveedor()
    {
        return $this->belongsTo(\App\Models\Compras\Proveedores\Proveedor::class, 'id_proveedor');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'id_compra');
    }

    public function detalleCompra()
    {
        return $this->belongsTo(DetalleCompra::class, 'id_detalle_compra');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }

    public function detalleVenta()
    {
        return $this->belongsTo(DetalleVenta::class, 'id_detalle_venta');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }
}
