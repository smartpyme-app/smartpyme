<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Lote;
use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\SoftDeletes;
//use Auth;
use Illuminate\Support\Facades\Auth;

class Inventario extends Model {

    use SoftDeletes;
    protected $table = 'inventario';
    protected $fillable = array(
        'id_producto',
        'stock',
        'stock_minimo',
        'stock_maximo',
        'nota',
        'id_bodega'
    );

    protected $appends = ['nombre_bodega', 'nombre_sucursal'];

    public function getNombreBodegaAttribute(){
        return $this->bodega()->pluck('nombre')->first();
    }

    public function getNombreSucursalAttribute(){
        return $this->bodega()->first() ? $this->bodega()->first()->nombre_sucursal : null;
    }

    public function kardex($modelo, $cantidad, $precio = NULL, $costo = NULL, $fecha = null){

        $clase = get_class($modelo);

        $entradaCantidad =  null;
        $salidaCantidad =  null;

        if ($clase == 'App\Models\Ventas\Venta') { //Salida
            if ($cantidad > 0) {
                $salidaCantidad =  $cantidad;
                $clase = $modelo->estado == 'Consigna' ? 'Venta a consigna' : 'Venta';
            }else{
                $entradaCantidad =  abs($cantidad);
                $clase = 'Venta Anulada';
            }
        }
        else if ($clase == 'App\Models\Compras\Compra') {
            if ($cantidad > 0) {
                $entradaCantidad =  $cantidad;
                $clase = $modelo->estado == 'Consigna' ? 'Compra a consigna' : 'Compra';
            }else{
                $salidaCantidad =  abs($cantidad);
                $clase = 'Compra Anulada';
            }
        }
        else if ($clase == 'App\Models\Inventario\Ajuste') {
            if ($cantidad > 0) {
                if ($modelo->estado == 'Cancelado') {
                    $clase = 'Ajuste cancelado';
                    $salidaCantidad =  $cantidad;
                }else{
                    $entradaCantidad =  $cantidad;
                    $clase = 'Ajuste';
                }
            }else{
                if ($modelo->estado == 'Cancelado') {
                    $clase = 'Ajuste cancelado';
                    $salidaCantidad =  $cantidad;
                }else{
                    $entradaCantidad =  $cantidad;
                    $clase = 'Ajuste';
                }
                $salidaCantidad =  abs($cantidad);
            }
        }
        else if ($clase == 'App\Models\Inventario\Traslado') {
            if ($cantidad > 0) {
                if ($modelo->estado == 'Cancelado') {
                    $clase = 'Traslado de ' . $modelo->destino()->pluck('nombre')->first() . ' cancelado';
                    $salidaCantidad =  $cantidad;
                }else{
                    $entradaCantidad =  $cantidad;
                    $clase = 'Traslado de ' . $modelo->origen()->pluck('nombre')->first();
                }
            }else{
                if ($modelo->estado == 'Cancelado') {
                    $clase = 'Traslado a ' . $modelo->origen()->pluck('nombre')->first() . ' cancelado';
                    $entradaCantidad =  abs($cantidad);
                }else{
                    $salidaCantidad =  abs($cantidad);
                    $clase = 'Traslado a ' . $modelo->destino()->pluck('nombre')->first();
                }
            }
        }
        else if ($clase == 'App\Models\Ventas\Devoluciones\Devolucion') {
            if ($cantidad > 0) {
                $entradaCantidad =  $cantidad;
                $clase = 'Devolución Venta';
            }else{
                $salidaCantidad =  abs($cantidad);
                $clase = 'Devolución Venta Anulada';
            }
        }
        else if ($clase == 'App\Models\Compras\Devoluciones\Devolucion') {
            if ($cantidad > 0) {
                $salidaCantidad =  $cantidad;
                $clase = 'Devolución Compra';
            }else{
                $entradaCantidad =  abs($cantidad);
                $clase = 'Devolución Compra Anulada';
            }
        }else if ($clase == 'App\Models\Inventario\Producto') {
            // Es una actualización de producto
            $clase = 'Actualización de producto';
            // No es entrada ni salida, por lo que ambos valores serían NULL
            $entradaCantidad = null;
            $salidaCantidad = null;
        }
        else if ($clase == 'App\Models\Inventario\Entradas\Entrada') {
            if ($cantidad > 0) {
                $entradaCantidad =  $cantidad;
                $clase = 'Otra Entrada';
            }else{
                $salidaCantidad =  abs($cantidad);
                $clase = 'Otra Entrada Anulada';
            }
        }
        else if ($clase == 'App\Models\Inventario\Salidas\Salida') {
            if ($cantidad > 0) {
                $salidaCantidad =  $cantidad;
                $clase = 'Otra Salida';
            }else{
                $entradaCantidad =  abs($cantidad);
                $clase = 'Otra Salida Anulada';
            }
        }else{
            // return null;
        }

        $producto = $this->producto()->withoutGlobalScope('empresa')->first();

        if (!$precio) {
            $precio = $producto->precio;
        }

        // if (!$costo) {
        //     if(Auth::user()->empresa->valor_inventario == 'promedio' && $producto->costo_promedio > 0){
        //         $costo = $producto->costo_promedio;
        //     }else{
        //         $costo = $producto->costo;
        //     }
        // }
        if (!$costo) {
            // Si estamos en un webhook (no hay usuario autenticado)
            if (!Auth::user()) {
                $costo = $producto->costo;
            } 
         
            else if(Auth::user()->empresa->valor_inventario == 'promedio' && $producto->costo_promedio > 0){
                $costo = $producto->costo_promedio;
            }else{
                $costo = $producto->costo;
            }
        }

        // Calcular el stock total según si el producto usa lotes o no
        $totalCantidad = $this->calcularStockParaKardex($producto, $modelo, $clase);

        $fechaKardex = $fecha ? (is_string($fecha) ? $fecha : $fecha->format('Y-m-d')) : date('Y-m-d');

        Kardex::create([
            'fecha'             => $fechaKardex,
            'id_producto'       => $this->id_producto,
            'id_inventario'     => $this->id_bodega,
            'detalle'           => $clase,
            'referencia'        => $modelo->id,
            'precio_unitario'   => $precio,
            'costo_unitario'    => $costo,
            'entrada_cantidad'  => $entradaCantidad,
            'entrada_valor'     => $entradaCantidad ? $entradaCantidad * $costo : null,
            'salida_cantidad'   => $salidaCantidad,
            'salida_valor'      => $salidaCantidad ? $salidaCantidad * $precio : null,
            'total_cantidad'    => $totalCantidad,
            'total_valor'       => $totalCantidad * $costo,
            'id_usuario'        => $modelo->id_usuario,
        ]);
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function kardexs(){
        return $this->hasMany('App\Models\Inventario\Kardex', 'id_inventario');
    }

    /**
     * Calcula el stock para el kardex según si el producto usa lotes o no
     */
    private function calcularStockParaKardex($producto, $modelo, $clase)
    {
        // Verificar si los lotes están activos en la empresa
        $empresa = null;
        if (Auth::user() && Auth::user()->empresa) {
            $empresa = Auth::user()->empresa;
        } else {
            // Si no hay usuario autenticado, obtener la empresa desde el producto
            $empresa = $producto->empresa ?? null;
        }

        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;

        // Si el producto no usa lotes o los lotes no están activos, usar stock tradicional
        if (!$producto->inventario_por_lotes || !$lotesActivo) {
            return $this->stock;
        }

        // Si el producto usa lotes, buscar el lote_id desde el modelo de referencia
        $loteId = $this->obtenerLoteIdDesdeModelo($modelo, $clase, $producto->id);

        if ($loteId) {
            // Si hay un lote específico, usar su stock
            $lote = Lote::find($loteId);
            if ($lote) {
                return $lote->stock;
            }
        }

        // Si no hay lote específico pero el producto usa lotes, sumar stock de todos los lotes de esta bodega
        $stockLotes = Lote::where('id_producto', $producto->id)
            ->where('id_bodega', $this->id_bodega)
            ->sum('stock');

        return $stockLotes;
    }

    /**
     * Obtiene el lote_id desde el modelo de referencia según el tipo de transacción
     */
    private function obtenerLoteIdDesdeModelo($modelo, $clase, $idProducto)
    {
        // Si es un ajuste
        if (strpos($clase, 'Ajuste') !== false || strpos($clase, 'ajuste') !== false) {
            if (isset($modelo->lote_id)) {
                return $modelo->lote_id;
            }
        }

        // Si es un traslado
        if (strpos($clase, 'Traslado') !== false || strpos($clase, 'traslado') !== false) {
            if (isset($modelo->lote_id)) {
                return $modelo->lote_id;
            }
        }

        // Si es una venta
        if ($clase == 'Venta' || $clase == 'Venta a consigna' || $clase == 'Venta Anulada') {
            $detalleVenta = \App\Models\Ventas\Detalle::where('id_venta', $modelo->id)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleVenta && $detalleVenta->lote_id) {
                return $detalleVenta->lote_id;
            }
        }

        // Si es una compra
        if ($clase == 'Compra' || $clase == 'Compra a consigna' || $clase == 'Compra Anulada') {
            $detalleCompra = \App\Models\Compras\Detalle::where('id_compra', $modelo->id)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleCompra && $detalleCompra->lote_id) {
                return $detalleCompra->lote_id;
            }
        }

        // Si es una devolución de venta
        if ($clase == 'Devolución Venta' || $clase == 'Devolución Venta Anulada') {
            $detalleDevolucion = \App\Models\Ventas\Devoluciones\Detalle::where('id_devolucion', $modelo->id)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleDevolucion && $detalleDevolucion->lote_id) {
                return $detalleDevolucion->lote_id;
            }
        }

        // Si es una devolución de compra
        if ($clase == 'Devolución Compra' || $clase == 'Devolución Compra Anulada') {
            $detalleDevolucion = \App\Models\Compras\Devoluciones\Detalle::where('id_devolucion', $modelo->id)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleDevolucion && $detalleDevolucion->lote_id) {
                return $detalleDevolucion->lote_id;
            }
        }

        // Si es otra entrada
        if ($clase == 'Otra Entrada' || $clase == 'Otra Entrada Anulada') {
            $detalleEntrada = \App\Models\Inventario\Entradas\Detalle::where('id_entrada', $modelo->id)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleEntrada && $detalleEntrada->lote_id) {
                return $detalleEntrada->lote_id;
            }
        }

        // Si es otra salida
        if ($clase == 'Otra Salida' || $clase == 'Otra Salida Anulada') {
            $detalleSalida = \App\Models\Inventario\Salidas\Detalle::where('id_salida', $modelo->id)
                ->where('id_producto', $idProducto)
                ->whereNotNull('lote_id')
                ->first();
            if ($detalleSalida && $detalleSalida->lote_id) {
                return $detalleSalida->lote_id;
            }
        }

        return null;
    }

}



