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
        'no_sujeta',
        'exenta',
        'total',
        'id_venta'
    );

    protected $appends = ['nombre_producto', 'img'];

    public function getNombreProductoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
    }

    public function getImgAttribute(){
        return $this->producto()->withoutGlobalScopes()->first()->img;
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta','id_venta');
    }



}
