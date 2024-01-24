<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class Impuesto extends Model
{
    protected $table = 'venta_impuestos';
    protected $fillable = [
        'monto',
        'id_impuesto',
        'id_venta'
    ];

    protected $appends = ['nombre', 'porcentaje'];

    public function getNombreAttribute(){
        return $this->impuesto()->pluck('nombre')->first();
    }

    public function getPorcentajeAttribute(){
        return $this->impuesto()->pluck('porcentaje')->first();
    }

    public function impuesto(){
        return $this->belongsTo('App\Models\Admin\Impuesto', 'id_impuesto');
    }

}
