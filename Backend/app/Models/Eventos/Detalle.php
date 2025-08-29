<?php

namespace App\Models\Eventos;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_evento';
    protected $fillable = array(
        'id_producto',
        'cantidad',
        'id_evento'
    );

    protected $appends = ['nombre_producto', 'precio_producto'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function getPrecioProductoAttribute(){
        return $this->producto()->pluck('precio')->first() ?? 0;
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function evento(){
        return $this->belongsTo('App\Models\Eventos\Evento','id_evento');
    }

}
