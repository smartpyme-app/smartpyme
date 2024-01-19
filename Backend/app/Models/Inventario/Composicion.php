<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Composicion extends Model {

    protected $table = 'producto_composiciones';
    protected $fillable = array(
        'id_producto',
        'id_compuesto',
        'cantidad'
    );

    protected $appends = ['nombre_producto'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function compuesto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_compuesto');
    }


}
