<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Imagen extends Model {

    protected $table = 'producto_imagenes';
    protected $fillable = array(
        'img',
        'producto_id'
    );


    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'producto_id');
    }


}



