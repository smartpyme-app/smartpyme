<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenCompraDetalle extends Model
{
    use HasFactory;

    protected $table = "detalles_orden_compra";

    protected $fillable = [
        "id_orden_compra",
        "id_producto",
        "cantidad",
        "costo",
        "descuento",
        "total",
    ];
    protected $appends = ['nombre_producto', 'img'];

    public function getNombreProductoAttribute()
    {
        return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
    }

    public function getImgAttribute()
    {
        return $this->producto()->withoutGlobalScopes()->first()->img;
    }

    public function producto()
    {
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function compra()
    {
        return $this->belongsTo('App\Models\Compras\Compra', 'id_compra');
    }
}
