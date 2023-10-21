<?php

namespace App\Models\Inventario\Precios;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'producto_precio_usuarios';
    protected $fillable = [
        'id_precio',
        'id_usuario',
    ];

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

}
