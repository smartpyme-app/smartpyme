<?php

namespace App\Models\Inventario\Traslados;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'traslado_detalles';
    protected $fillable = array(
        'id_producto',
        'cantidad',
        'costo',
        'id_traslado'
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
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function traslado(){
        return $this->belongsTo('App\Models\Inventario\Traslados\Traslado','id_traslado');
    }

}



