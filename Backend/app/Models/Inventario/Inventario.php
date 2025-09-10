<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventario\Kardex;
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

    public function kardex($modelo, $cantidad, $precio = NULL, $costo = NULL){

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

        Kardex::create([
            'fecha'             => date('Y-m-d'),
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
            'total_cantidad'    => $this->stock,
            'total_valor'       => $this->stock * $costo,
            'id_usuario'        => $modelo->id_usuario,
        ]);
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function kardexs(){
        return $this->hasMany('App\Models\Inventario\Kardex', 'id_inventario');
    }

}



