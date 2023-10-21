<?php

namespace App\Models\Inventario\Precios;

use Illuminate\Database\Eloquent\Model;

class Precio extends Model
{
    protected $table = 'producto_precios';
    protected $fillable = [
        'precio',
        'id_producto',
    ];

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'producto_id');
    }

}
