<?php

namespace App\Models\Ventas\Orden_Produccion;

use App\Models\Inventario\CustomFields\CustomField;
use App\Models\Inventario\CustomFields\ProductCustomField;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Model;

class DetalleOrdenProduccion extends Model
{
    protected $table = 'detalles_orden_produccion';

    protected $fillable = [
        'id_orden_produccion',
        'cantidad',
        'precio',
        'total',
        'total_costo',
        'descuento',
        'id_producto',
        'descripcion',
        'id_cotizacion_venta'
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'total' => 'decimal:2',
        'total_costo' => 'decimal:2',
        'descuento' => 'decimal:2'
    ];

    // Relaciones
    public function orden()
    {
        return $this->belongsTo(OrdenProduccion::class, 'id_orden_produccion');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }

    public function customFields()
    {
        return $this->hasMany(ProductCustomField::class, 'orden_produccion_detalle_id');
    }
}