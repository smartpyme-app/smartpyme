<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class DetalleCompuesto extends Model {

    protected $table = 'detalles_compuesto_venta';
    protected $fillable = array(
        'id_producto',
        'cantidad',
        'id_detalle'
    );

    protected $appends = ['producto_nombre', 'detalle_cantidad'];

    public function getProductoNombreAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function getDetalleCantidadAttribute(){
        return $this->detalle()->pluck('cantidad')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function detalle(){
        return $this->belongsTo('App\Models\Ventas\Detalle','id_detalle');
    }


}
