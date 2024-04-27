<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inventario\Kardex;
use Illuminate\Database\Eloquent\SoftDeletes;
class Inventario extends Model {

    use SoftDeletes;
    protected $table = 'inventario';
    protected $fillable = array(
        'id_producto',
        'stock',
        'stock_minimo',
        'stock_maximo',
        'nota',
        'id_sucursal'
    );

    protected $appends = ['nombre_sucursal'];

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function kardex($modelo, $cantidad){

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
        if ($clase == 'App\Models\Transporte\Mantenimientos\Mantenimiento') { //Mantenimiento
            $salidaCantidad =  $cantidad;
            $clase = 'Mantenimiento';
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
        }else{
            // return null;
        }

        $precio = $this->producto()->pluck('precio')->first();
        $costo = $this->producto()->pluck('costo')->first();

        Kardex::create([
            'fecha'             => date('Y-m-d'),
            'id_producto'       => $this->id_producto,
            'id_inventario'     => $this->id_sucursal,
            'detalle'           => $clase,
            'referencia'        => $modelo->id,
            'precio_unitario'   => $salidaCantidad ? $precio : null,
            'costo_unitario'    => $entradaCantidad ? $costo : null,
            'entrada_cantidad'  => $entradaCantidad,
            'entrada_valor'     => $entradaCantidad ? $entradaCantidad * $costo : null,
            'salida_cantidad'   => $salidaCantidad,
            'salida_valor'      => $salidaCantidad ? $salidaCantidad * $precio : null,
            'total_cantidad'    => $this->stock,
            'total_valor'       => $this->stock * ($salidaCantidad ? $precio : $costo),
            'id_usuario'        => $modelo->id_usuario,
        ]);
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

}



