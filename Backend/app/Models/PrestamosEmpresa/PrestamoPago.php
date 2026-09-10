<?php

namespace App\Models\PrestamosEmpresa;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestamoPago extends Model
{
    protected $table = 'prestamo_pagos';

    protected $fillable = [
        'id_prestamo',
        'fecha',
        'monto',
        'capital',
        'interes',
        'metodo',
        'referencia',
        'detalle_banco',
        'id_usuario',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
        'capital' => 'decimal:2',
        'interes' => 'decimal:2',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoEmpresa::class, 'id_prestamo');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(PrestamoCuota::class, 'id_pago');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
