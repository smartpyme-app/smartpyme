<?php

namespace App\Models\Inventario\Salidas;

use Illuminate\Database\Eloquent\Model;

class DetalleSalidaLote extends Model
{
    protected $table = 'detalle_salida_lotes';

    protected $fillable = [
        'id_detalle_salida',
        'lote_id',
        'cantidad',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
    ];

    public function detalle()
    {
        return $this->belongsTo(Detalle::class, 'id_detalle_salida');
    }

    public function lote()
    {
        return $this->belongsTo('App\Models\Inventario\Lote', 'lote_id');
    }
}
