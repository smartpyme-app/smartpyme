<?php

namespace App\Models\FacturacionElectronica\CostaRica;

use Illuminate\Database\Eloquent\Model;

/**
 * Caché diaria del tipo de cambio de venta (indicador 318) publicado por el BCCR.
 */
class BccrTipoCambio extends Model
{
    protected $table = 'bccr_tipos_cambio';

    protected $fillable = [
        'date',
        'venta_reference_rate',
        'fetched_at',
    ];

    protected $casts = [
        'date' => 'date',
        'venta_reference_rate' => 'decimal:5',
        'fetched_at' => 'datetime',
    ];
}
