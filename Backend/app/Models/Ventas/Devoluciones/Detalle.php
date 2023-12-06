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
        'total',
        'id_devolucion_venta',
        'id_producto'
    );
    protected $appends = ['nombre_producto', 'medida', 'exenta', 'gravada', 'no_sujeta'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function getMedidaAttribute(){
        return $this->producto()->pluck('medida')->first();
    }

    public function getExentaAttribute(){
        if ($this->tipo_impuesto == 'Exenta')
            return $this->subtotal;
        else
            return 0;
    }

    public function getGravadaAttribute(){
        if ($this->tipo_impuesto == 'Gravada')
            return $this->subtotal;
        else
            return 0;
    }

    public function getNoSujetaAttribute(){
        if ($this->tipo_impuesto == 'No Sujeta')
            return $this->subtotal;
        else
            return 0;
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Devoluciones\Devolucion','id_devolucion_venta');
    }



}
