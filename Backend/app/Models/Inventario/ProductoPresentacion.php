<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoPresentacion extends Model
{
    protected $table = 'producto_presentaciones';

    protected $fillable = [
        'id_producto',
        'id_unidad_medida',
        'nombre_comercial',
        'factor_conversion',
        'precio_venta',
        'codigo_barras',
    ];

    protected $casts = [
        'factor_conversion' => 'decimal:6',
        'precio_venta'      => 'decimal:6',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    /**
     * Producto base al que pertenece esta presentación.
     */
    public function producto()
    {
        return $this->belongsTo(
            'App\Models\Inventario\Producto',
            'id_producto'
        );
    }

    /**
     * Unidad de medida fiscal del catálogo oficial.
     */
    public function unidadMedida()
    {
        return $this->belongsTo(
            'App\Models\MH\Unidad',
            'id_unidad_medida'
        );
    }
}
