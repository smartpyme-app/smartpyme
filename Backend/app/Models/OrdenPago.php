<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenPago extends Model
{
    use HasFactory;

    protected $table = 'ordenes_pagos';

    protected $fillable = [
        'id_orden',
        'id_usuario',
        'plan',
        'link_pago',
        'checkout_note',
        'monto',
        'estado',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
