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
        'id_orden_n1co',
        'id_autorizacion_3ds',
        'autorizacion_url',
        'id_plan',
        'plan',
        'monto',
        'estado',
        'divisa',
        'codigo_autorizacion',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'id_plan');
    }
}
