<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class DetalleCompraLote extends Model
{
    protected $table = 'detalle_compra_lotes';

    protected $fillable = [
        'id_detalle_compra',
        'lote_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function detalle()
    {
        return $this->belongsTo(Detalle::class, 'id_detalle_compra');
    }

    public function lote()
    {
        return $this->belongsTo('App\Models\Inventario\Lote', 'lote_id');
    }
}
