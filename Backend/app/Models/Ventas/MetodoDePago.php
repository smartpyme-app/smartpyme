<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class MetodoDePago extends Model
{
    protected $table = 'venta_metodos_pago';
    protected $fillable = [
        'id_venta',
        'nombre',
        'total',
    ];

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta', 'id_venta');
    }

}
