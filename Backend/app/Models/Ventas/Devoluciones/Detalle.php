<?php

namespace App\Models\Ventas\Devoluciones;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_devolucion_venta';
    protected $fillable = array(
        'cantidad',
        'precio',
        'costo',
        'descuento',
        'no_sujeta',
        'cuenta_a_terceros',
        'exenta',
        'total',
        'id_devolucion_venta',
        'id_producto'
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

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Devoluciones\Devolucion','id_devolucion_venta');
    }



}
