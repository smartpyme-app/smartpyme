<?php

namespace App\Models\CreditosClientes;

use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditoCuota extends Model
{
    protected $table = 'credito_cuotas';

    public const ESTADO_PROGRAMADA = 'programada';
    public const ESTADO_FACTURADA = 'facturada';

    protected $fillable = [
        'id_contrato',
        'numero',
        'fecha_vencimiento',
        'monto',
        'estado',
        'id_venta',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha_vencimiento' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(CreditoContrato::class, 'id_contrato');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }
}
