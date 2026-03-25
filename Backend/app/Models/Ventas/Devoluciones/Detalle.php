<?php

namespace App\Models\Ventas\Devoluciones;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'detalles_devolucion_venta';
    protected $fillable = array(
        'id_producto',
        'lote_id',
        'descripcion',
        'cantidad',
        'precio',
        'costo',
        'descuento',
        'no_sujeta',
        'cuenta_a_terceros',
        'exenta',
        'total',
        'id_devolucion_venta',
    );
    protected $appends = ['nombre_producto', 'medida', 'codigo', 'marca'];

    public function getNombreProductoAttribute(){
        if ($this->descripcion) {
            return $this->descripcion;
        }else{
            return $this->producto()->pluck('nombre')->first();
        }
    }

    public function getMedidaAttribute(){
        return $this->producto()->pluck('medida')->first();
    }

    public function getCodigoAttribute(){
        return $this->producto()->pluck('codigo')->first();
    }

    public function getMarcaAttribute(){
        return $this->producto()->pluck('marca')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function composiciones(){
        return $this->hasMany('App\Models\Ventas\Devoluciones\DetalleCompuesto','id_detalle');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Devoluciones\Devolucion','id_devolucion_venta');
    }

    public function lote(){
        return $this->belongsTo('App\Models\Inventario\Lote','lote_id');
    }

}
