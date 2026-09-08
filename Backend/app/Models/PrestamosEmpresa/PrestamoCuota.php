<?php

namespace App\Models\PrestamosEmpresa;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestamoCuota extends Model
{
    protected $table = 'prestamo_cuotas';

    protected $fillable = [
        'id_prestamo',
        'numero',
        'fecha_vencimiento',
        'capital',
        'interes',
        'total',
        'estado',
        'id_pago',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'capital' => 'decimal:2',
        'interes' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoEmpresa::class, 'id_prestamo');
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(PrestamoPago::class, 'id_pago');
    }
}
