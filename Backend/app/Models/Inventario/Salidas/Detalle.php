<?php

namespace App\Models\Inventario\Salidas;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'inventario_salida_detalles';
    protected $fillable = array(
        'id_producto',
        'cantidad',
        'costo',
        'total',
        'id_salida',
    );

    protected $appends = ['nombre_producto'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function salida(){
        return $this->belongsTo('App\Models\Inventario\Salidas\Salida', 'id_salida');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto')->withoutGlobalScope('tipo');
    }

}
