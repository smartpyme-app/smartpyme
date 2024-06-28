<?php

namespace App\Models\Contabilidad\Catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaMayorizada extends Model
{
    use HasFactory;
    protected $fillable = [
        'codigo',
        'nombre',
        'cargo',
        'abono',
        'saldo',
        'naturaleza_saldo', //si el saldo de la cuenta es deudor o acreedor
    ];
}
