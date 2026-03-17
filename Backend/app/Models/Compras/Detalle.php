<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_compra';
    protected $fillable = array(
        'id_producto',
        'lote_id',
        'cantidad',
        'costo',
        'descuento',
        'no_sujeta',
        'exenta',
        'iva',
        'porcentaje_impuesto',
        'subtotal',
        'total',
        'id_compra'

    );

    protected $appends = ['nombre_producto', 'img', 'codigo'];

    public function getNombreProductoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
    }

    public function getImgAttribute(){
        $producto = $this->producto()->withoutGlobalScopes()->first();
        return $producto ? $producto->img : 'productos/default.jpg';
    }

    public function getcodigoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('codigo')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','id_compra');
    }

    public function lote(){
        return $this->belongsTo('App\Models\Inventario\Lote','lote_id');
    }


}
