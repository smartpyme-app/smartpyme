<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_compra';
    protected $fillable = array(
        'id_producto',
        'cantidad',
        'costo',
        'descuento',
        'no_sujeta',
        'exenta',
        'gravada',
        'iva',
        'subtotal',
        'total',
        'id_compra'

    );

    protected $appends = ['nombre_producto', 'medida'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function getMedidaAttribute(){
        return $this->producto()->pluck('medida')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','id_compra');
    }


}
