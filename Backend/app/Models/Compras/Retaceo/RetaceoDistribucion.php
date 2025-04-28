<?php

namespace App\Models\Compras\Retaceo;

use App\Models\Compras\Detalle;
use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetaceoDistribucion extends Model
{
    use HasFactory;

    protected $table = 'retaceo_distribucion';

    protected $fillable = [
        'id_retaceo',
        'id_producto',
        'id_detalle_compra',
        'cantidad',
        'costo_original',
        'valor_fob',
        'porcentaje_distribucion',
        'monto_transporte',
        'monto_seguro',
        'monto_dai',
        'monto_otros',
        'costo_landed',
        'costo_retaceado',
        'porcentaje_dai'
    ];

    /**
     * Relación con el retaceo
     */
    public function retaceo()
    {
        return $this->belongsTo(Retaceo::class, 'id_retaceo');
    }

    /**
     * Relación con el producto
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }

    /**
     * Relación con el detalle de compra
     */
    public function detalleCompra()
    {
        return $this->belongsTo(Detalle::class, 'id_detalle_compra');
    }
}