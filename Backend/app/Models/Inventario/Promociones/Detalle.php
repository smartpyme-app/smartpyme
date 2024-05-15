<?php

namespace App\Models\Inventario\Promociones;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model
{
    protected $table = 'detalles_promocion';
    protected $fillable = [
        'cantidad',
        'subtotal',
        'precio',
        'descuento',
        'id_producto',
        'id_promocion',   
    ];

    protected $appends = ['nombre_producto'];

    public function promocion(){
        return $this->belongsTo('App\Models\Inventario\Promociones\Promocion', 'id_promocion');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }
}
