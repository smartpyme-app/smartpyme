<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'venta_detalles';
    protected $fillable = array(
        'producto_id',
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
        'venta_id'
    );

    protected $appends = ['nombre_producto', 'medida'];

    public function getNombreProductoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
    }

    public function getMedidaAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('medida')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','producto_id');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','venta_id');
    }



}
