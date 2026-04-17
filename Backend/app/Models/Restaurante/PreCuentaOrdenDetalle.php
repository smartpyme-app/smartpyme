<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

/**
 * Pivot para división por ítems: vincula orden_detalle a pre_cuenta específica.
 */
class PreCuentaOrdenDetalle extends Model
{
    protected $table = 'pre_cuenta_orden_detalle';

    protected $fillable = [
        'pre_cuenta_id',
        'orden_detalle_id',
    ];

    public function preCuenta()
    {
        return $this->belongsTo(PreCuenta::class);
    }

    public function ordenDetalle()
    {
        return $this->belongsTo(OrdenDetalle::class, 'orden_detalle_id');
    }
}
