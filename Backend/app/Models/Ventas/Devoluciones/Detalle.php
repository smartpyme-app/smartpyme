<?php

namespace App\Models\Ventas\Devoluciones;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'venta_devolucion_detalles';
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
        'devolucion_id'
    );
    protected $appends = ['nombre_producto', 'medida', 'exenta', 'gravada', 'no_sujeta'];

    public function getNombreProductoAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('nombre')->first();
    }

    public function getMedidaAttribute(){
        return $this->producto()->withoutGlobalScopes()->pluck('medida')->first();
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
        return $this->belongsTo('App\Models\Inventario\Producto','producto_id');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Devoluciones\Devolucion','devolucion_id');
    }



}
