<?php

namespace App\Models\Creditos;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Pago extends Model {
    
    protected $table = 'credito_pagos';
    protected $fillable = [
        'fecha',
        'credito_id',
        'metodo_pago',
        'saldo_inicial',
        'cuota',
        'interes',
        'mora',
        'descuento',
        'comision',
        'seguro',
        'abono',
        'saldo_final',
        'usuario_id',
    ];

    protected $appends = ['nombre_usuario'];

    public function getFechaAttribute($value)
    {
         return Carbon::parse($value)->format('Y-m-d');
    }


    public function getNombreUsuarioAttribute() 
    {
        return $this->usuario()->pluck('name')->first();
    }
    
    public function credito() 
    {
        return $this->belongsTo('App\Models\Creditos\Credito', 'credito_id');
    }

    public function usuario() 
    {
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

}