<?php

namespace App\Models\Inventario\Traslados;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'producto_traslado_detalles';
    protected $fillable = array(
        'producto_id',
        'cantidad',
        'traslado_id'
    );

    protected $appends = ['nombre_producto', 'medida'];

    public function getNombreProductoAttribute()
    {
        return $this->producto()->pluck('nombre')->first();
    }
    

    public function getMedidaAttribute()
    {
        return $this->producto()->pluck('medida')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','producto_id');
    }

    public function traslado(){
        return $this->belongsTo('App\Models\Inventario\Traslados\Traslado','traslado_id');
    }

}



