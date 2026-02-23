<?php

namespace App\Models\Compras\Devoluciones;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_devolucion_compra';
    protected $fillable = array(
        'id_producto',
        'lote_id',
        'cantidad',
        'costo',
        'descuento',
        'no_sujeta',
        'exenta',
        'gravada',
        'subtotal',
        'iva',
        'total',
        'id_devolucion_compra'

    );

    protected $appends = ['nombre_producto', 'medida', 'exenta', 'gravada', 'no_sujeta'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function getExentaAttribute(){
        if ($this->tipo == 'Exenta')
            return $this->subtotal;
        else
            return 0;
    }

    public function getGravadaAttribute(){
        if ($this->tipo == 'Gravada')
            return $this->subtotal;
        else
            return 0;
    }

    public function getNoSujetaAttribute(){
        if ($this->tipo == 'No Sujeta')
            return $this->subtotal;
        else
            return 0;
    }

    public function getMedidaAttribute(){
        return $this->producto()->pluck('medida')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function devolucion(){
        return $this->belongsTo('App\Models\Compras\Compra','id_devolucion_compra');
    }

    public function lote(){
        return $this->belongsTo('App\Models\Inventario\Lote','lote_id');
    }

}
