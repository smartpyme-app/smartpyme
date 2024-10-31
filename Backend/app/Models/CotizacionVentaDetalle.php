<?php

namespace App\Models;

use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionVentaDetalle extends Model
{
    use HasFactory;
    protected $table = 'detalles_cotizacion_ventas';
    protected $fillable = [
        "cantidad",
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
        "codigo_combo",
        "id_cotizacion_venta",
    ];
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
    public function combo()
    {
        return $this->belongsTo(ComboProducto::class, 'codigo_combo', 'codigo_combo');
    }
}
