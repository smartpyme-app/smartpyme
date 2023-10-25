<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_venta';
    protected $fillable = array(
        'id_producto',
        'cantidad',
        'precio',
        'costo',
        'descuento',
        'subcosto',
        'subtotal',
        'no_sujeta',
        'exenta',
        'gravada',
        'iva',
        'total',
        'id_venta'
    );

    protected $appends = ['nombre_producto', 'medida'];

    public function getNombreProductoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
    }

    public function getMedidaAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('medida')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','id_venta');
    }



}
