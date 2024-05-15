<?php

namespace App\Models\Inventario\Composiciones;

use Illuminate\Database\Eloquent\Model;

class Opcion extends Model {

    protected $table = 'producto_composicion_opciones';
    protected $fillable = array(
        'id_composicion',
        'id_producto'
    );

    protected $appends = ['nombre_producto'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function composicion(){
        return $this->belongsTo('App\Models\Inventario\Composiciones\Composicion', 'id_composicion');
    }


}
