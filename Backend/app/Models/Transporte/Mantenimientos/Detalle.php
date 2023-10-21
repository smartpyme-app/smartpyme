<?php

namespace App\Models\Transporte\Mantenimientos;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'transporte_mantenimiento_detalles';
    protected $fillable = array(
        'producto_id',
        'cantidad',
        'costo',
        'total',
        'mantenimiento_id',
    );

    protected $appends = ['nombre_producto'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','producto_id');
    }

    public function mantenimiento(){
        return $this->belongsTo('App\Models\Transporte\Mantenimientos\Mantenimiento','mantenimiento_id');
    }


}
