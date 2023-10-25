<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Imagen extends Model {

    protected $table = 'productos_imagenes';
    protected $fillable = array(
        'img',
        'id_producto'
    );


    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }


}



