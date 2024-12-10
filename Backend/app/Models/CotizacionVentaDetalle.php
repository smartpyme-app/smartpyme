<?php

namespace App\Models;

use App\Models\Inventario\CustomFields\ProductCustomField;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionVentaDetalle extends Model
{
    use HasFactory;
    protected $table = 'detalles_cotizacion_ventas';
    // CotizacionVentaDetalle.php
    protected $fillable = [
        "cantidad",
        "costo",
        "precio",
        "total",
        "total_costo",
        "descuento",
        "no_sujeta",
        "exenta",
        "cuenta_a_terceros",
        "subtotal",
        "gravada",
        "iva",
        "descripcion",
        "id_producto",
        "id_cotizacion_venta",
        "id_vendedor",
     //   "remember_token"
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }

    public function cotizacion()
    {
        return $this->belongsTo(CotizacionVenta::class, 'id_cotizacion_venta');
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }

    public function customFields()
    {
        return $this->hasMany(ProductCustomField::class, 'cotizacion_venta_detalle_id');
    }
}
