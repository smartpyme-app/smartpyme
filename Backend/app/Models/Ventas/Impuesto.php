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

    public function impuesto(){
        return $this->belongsTo('App\Models\Admin\Impuesto', 'id_impuesto');
    }

}
