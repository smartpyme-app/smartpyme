<?php

namespace App\Models\Inventario\Entradas;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'inventario_entrada_detalles';
    protected $fillable = array(
        'id_producto',
        'lote_id',
        'cantidad',
        'costo',
        'total',
        'id_entrada',
    );

    protected $appends = ['nombre_producto'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function entrada(){
        return $this->belongsTo('App\Models\Inventario\Entradas\Entrada', 'id_entrada');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto')->withoutGlobalScope('tipo');
    }

    public function lote(){
        return $this->belongsTo('App\Models\Inventario\Lote', 'lote_id');
    }

}
