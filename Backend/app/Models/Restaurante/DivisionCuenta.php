<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class DivisionCuenta extends Model
{
    protected $table = 'division_cuenta_restaurante';

    protected $fillable = [
        'sesion_id',
        'tipo',
        'num_pagadores',
    ];

    public function sesion()
    {
        return $this->belongsTo(SesionMesa::class, 'sesion_id');
    }

    public function preCuentas()
    {
        return $this->hasMany(PreCuenta::class, 'division_cuenta_id');
    }
}
