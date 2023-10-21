<?php

namespace App\Http\Controllers\Api\Compras\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Compras\DevolucionDetalle as Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\BodegaProducto;
use App\Models\Admin\Bomba;
use App\Models\Admin\Tanque;

class DevolucionDetallesController extends Controller
{
    

    public function index() {
       
        $detalles = Detalle::orderBy('created_at','desc')->paginate(10);

        return Response()->json($detalles, 200);

    }


    public function read($id) {
       
        $detalle = Detalle::findOrFail($request->id);
        return Response()->json($detalle, 200);

    }


    public function store(Request $request)
    {
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;

        // Actualizar producto
            $producto = Producto::findOrFail($request->producto_id);

            if ($producto->inventario) {
                // Si es gasolina disminuir tanque
                if ($producto->categoria_id == 1) {
                    $tanque = Tanque::findOrFail($request->tanque_id);
                    $tanque->stock -= $request->cantidad;
                    $tanque->save();
                // Si es producto disminuir bodega
                } else {
                    $bodega = BodegaProducto::where('bodega_id', 1)->where('producto_id',$producto->id)->first();
                    $bodega->stock -= $request->cantidad;
                    $bodega->save();
                }
            }
        }
        
        $detalle->fill($request->all());
        $detalle->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        
        $detalle = Detalle::findOrFail($id);
        // Actualizar inventario
            $producto = Producto::findOrFail($detalle->producto_id);
            if ($producto->categoria == 1) {
                $tanque = Tanque::findOrFail($detalle->tanque_id);
                $tanque->stock += $detalle->cantidad;
                $tanque->save();
            } else {
                $bodega = BodegaProducto::where('bodega_id', 1)->where('producto_id', $detalle->producto_id)->first();
                $bodega->stock += $detalle->cantidad;
                $bodega->save();
            } 
        $detalle->delete();

        return Response()->json($detalle, 201);

    }

}
