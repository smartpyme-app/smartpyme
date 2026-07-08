<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class DetalleVentaLote extends Model
{
    protected $table = 'detalle_venta_lotes';

    protected $fillable = [
        'id_detalle_venta',
        'lote_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function detalle()
    {
        return $this->belongsTo(Detalle::class, 'id_detalle_venta');
    }

    public function lote()
    {
        return $this->belongsTo('App\Models\Inventario\Lote', 'lote_id');
    }
}
