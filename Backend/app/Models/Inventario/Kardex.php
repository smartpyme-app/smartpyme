<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Kardex extends Model {

    protected $table = 'kardexs';
    protected $fillable = array(
        'fecha',
        'id_producto',
        'id_inventario',
        'detalle',
        'referencia',
        'entrada_cantidad',
        'costo_unitario',
        'entrada_valor',
        'salida_cantidad',
        'precio_unitario',
        'salida_valor',
        'total_cantidad',
        'total_valor',
        'id_usuario',
    );

    protected $appends = ['nombre_usuario', 'nombre_producto', 'nombre_bodega', 'modelo', 'modelo_detalle'];

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->first() ? $this->usuario()->pluck('name')->first() : '';
    }

    public function getNombreProductoAttribute()
    {
        return  $this->producto()->first() ? $this->producto()->pluck('nombre')->first() : '';
    }

    public function getNombreBodegaAttribute()
    {
        return  $this->inventario()->first() ? $this->inventario()->first()->nombre : '';
    }

    public function getModeloDetalleattribute(){
        $detalle = [];
        $info = '';
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada') {
            $detalle = \App\Models\Ventas\Venta::find($this->referencia);
            $info = $detalle->nombre_documento . ' #' . $detalle->correlativo;
        }
        if ($this->detalle == 'Devolución Venta' || $this->detalle == 'Devolución Venta Anulada') {
            $detalle = \App\Models\Ventas\Devoluciones\Devolucion::find($this->referencia);
            $info = 'Devolución # ' . $detalle->id;
        }
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            $detalle = \App\Models\Compras\Compra::find($this->referencia);
            $info = $detalle->tipo_documento . ' #' . $detalle->referencia;
        }
        if ($this->detalle == 'Devolución Compra' || $this->detalle == 'Devolución Compra Anulada') {
            $detalle = \App\Models\Compras\Devoluciones\Devolucion::find($this->referencia);
            $info = 'Devolución # ' . $detalle->id;
        }
        if (strpos($this->detalle , 'Traslado') !== false || strpos($this->detalle , 'traslado') !== false) {
            $detalle = \App\Models\Inventario\Traslado::find($this->referencia);
            $info = 'Traslado #' . $this->referencia;
        }
        if (strpos($this->detalle , 'Ajuste') !== false || strpos($this->detalle , 'ajuste') !== false) {
            $detalle = \App\Models\Inventario\Ajuste::find($this->referencia);
            $info = 'Ajuste #' . $this->referencia;
        }

        return $info;
    }

    public function getModeloattribute(){
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada') {
            return 'venta';
        }
        if ($this->detalle == 'Devolución Venta' || $this->detalle == 'Devolución Venta Anulada') {
            return 'devolucion/venta';
        }
        if ($this->detalle == 'Devolución Compra' || $this->detalle == 'Devolución Compra Anulada') {
            return 'devolucion/compra';
        }
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            return 'compra';
        }
        if (strpos($this->detalle , 'Traslado') !== false || strpos($this->detalle , 'traslado') !== false) {
            return 'traslado';
        }
        if (strpos($this->detalle , 'Ajuste') !== false || strpos($this->detalle , 'ajuste') !== false) {
            return 'ajuste';
        }
    }

    public function inventario(){
        return $this->belongsTo('App\Models\Inventario\Inventario','id_inventario');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto')->withoutGlobalScopes();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

}



