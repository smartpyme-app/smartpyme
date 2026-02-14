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

    protected $appends = ['nombre_usuario', 'nombre_producto', 'modelo', 'modelo_detalle', 'numero_lote'];

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->first() ? $this->usuario()->pluck('name')->first() : '';
    }

    public function getNombreProductoAttribute()
    {
        return  $this->producto()->first() ? $this->producto()->pluck('nombre')->first() : '';
    }

    public function getModeloDetalleattribute(){
        $detalle = [];
        $info = '';
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada') {
            $detalle = \App\Models\Ventas\Venta::find($this->referencia);
            $info = $detalle ? ($detalle->nombre_documento ?? 'Venta') : ('Venta #' . $this->referencia);
        }
        if (str_contains($this->detalle, 'Devolución Venta')) {
            $detalle = \App\Models\Ventas\Devoluciones\Devolucion::find($this->referencia);
            $info = $detalle ? ($detalle->nombre_documento ?: 'Devolución') : 'Devolución';
        }
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            $detalle = \App\Models\Compras\Compra::find($this->referencia);
            $info = $detalle ? ($detalle->tipo_documento ?? 'Compra') : ('Compra #' . $this->referencia);
        }
        if (str_contains($this->detalle, 'Devolución Compra')) {
            $detalle = \App\Models\Compras\Devoluciones\Devolucion::find($this->referencia);
            $info = $detalle ? ($detalle->tipo_documento ?: 'Devolución') : 'Devolución';
        }
        if (strpos($this->detalle , 'Traslado') !== false || strpos($this->detalle , 'traslado') !== false) {
            $detalle = \App\Models\Inventario\Traslado::find($this->referencia);
            $info = 'Traslado';
        }
        if (strpos($this->detalle , 'Ajuste') !== false || strpos($this->detalle , 'ajuste') !== false) {
            $detalle = \App\Models\Inventario\Ajuste::find($this->referencia);
            $info = 'Ajuste';
        }
        if ($this->detalle == 'Actualización de producto') {
            $info = 'Actualización de producto';
        }
        if ($this->detalle == 'Otra Entrada' || $this->detalle == 'Otra Entrada Anulada') {
            $info = 'Entrada #' . $this->referencia;
        }
        if ($this->detalle == 'Otra Salida' || $this->detalle == 'Otra Salida Anulada') {
            $info = 'Salida #' . $this->referencia;
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
        if ($this->detalle == 'Actualización de producto') {
            return 'producto';
        }
        if ($this->detalle == 'Otra Entrada' || $this->detalle == 'Otra Entrada Anulada') {
            return 'entrada/detalle';
        }
        if ($this->detalle == 'Otra Salida' || $this->detalle == 'Otra Salida Anulada') {
            return 'salida/detalle';
        }
    }

    public function inventario(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_inventario');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto')->withoutGlobalScopes();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    /**
     * Obtiene el número de lote si el movimiento tiene un lote asociado
     */
    public function getNumeroLoteAttribute()
    {
        // Si es un ajuste, obtener el lote desde el ajuste
        if (strpos($this->detalle, 'Ajuste') !== false || strpos($this->detalle, 'ajuste') !== false) {
            $ajuste = \App\Models\Inventario\Ajuste::find($this->referencia);
            if ($ajuste && $ajuste->lote_id) {
                $lote = \App\Models\Inventario\Lote::find($ajuste->lote_id);
                return $lote ? ($lote->numero_lote ?: 'Sin número') : null;
            }
        }
        
        // Si es un traslado, obtener el lote desde el traslado
        if (strpos($this->detalle, 'Traslado') !== false || strpos($this->detalle, 'traslado') !== false) {
            $traslado = \App\Models\Inventario\Traslado::find($this->referencia);
            if ($traslado && $traslado->lote_id) {
                $lote = \App\Models\Inventario\Lote::find($traslado->lote_id);
                return $lote ? ($lote->numero_lote ?: 'Sin número') : null;
            }
        }
        
        // Si es una venta, obtener el lote desde el detalle de venta
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada') {
            $venta = \App\Models\Ventas\Venta::find($this->referencia);
            if ($venta) {
                // Buscar el detalle de venta que corresponda a este producto
                $detalleVenta = \App\Models\Ventas\Detalle::where('id_venta', $venta->id)
                    ->where('id_producto', $this->id_producto)
                    ->whereNotNull('lote_id')
                    ->first();
                if ($detalleVenta && $detalleVenta->lote_id) {
                    $lote = \App\Models\Inventario\Lote::find($detalleVenta->lote_id);
                    return $lote ? ($lote->numero_lote ?: 'Sin número') : null;
                }
            }
        }
        
        // Si es una compra, obtener el lote desde el detalle de compra
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            $compra = \App\Models\Compras\Compra::find($this->referencia);
            if ($compra) {
                // Buscar el detalle de compra que corresponda a este producto
                $detalleCompra = \App\Models\Compras\Detalle::where('id_compra', $compra->id)
                    ->where('id_producto', $this->id_producto)
                    ->whereNotNull('lote_id')
                    ->first();
                if ($detalleCompra && $detalleCompra->lote_id) {
                    $lote = \App\Models\Inventario\Lote::find($detalleCompra->lote_id);
                    return $lote ? ($lote->numero_lote ?: 'Sin número') : null;
                }
            }
        }
        
        return null;
    }

}



