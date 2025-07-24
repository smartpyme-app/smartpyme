<?php

namespace App\Models\Contabilidad\Catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaReporte extends Model
{
    use HasFactory;

    protected $fillable = [
        'cuenta',
        'detalles',
        'naturaleza',
        'cargo',
        'abono',
        'saldo_actual',
        'saldo_anterior'

    ];
}
