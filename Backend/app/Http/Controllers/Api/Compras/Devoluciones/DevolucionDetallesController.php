<?php

namespace App\Http\Controllers\Api\Compras\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Compras\DevolucionDetalle as Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Bodega;
use App\Http\Requests\Compras\Devoluciones\StoreDetalleDevolucionCompraRequest;

class DevolucionDetallesController extends Controller
{
    

    public function index() {
       
        $detalles = Detalle::orderBy('created_at','desc')->paginate(10);

        return Response()->json($detalles, 200);

    }


    public function read($id) {
       
        $detalle = Detalle::findOrFail($id);
        return Response()->json($detalle, 200);

    }


    public function store(StoreDetalleDevolucionCompraRequest $request)
    {
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;

        // Actualizar producto
            $producto = Producto::findOrFail($request->producto_id);

            if ($producto->inventario) {
                $bodega = Bodega::where('bodega_id', 1)->where('producto_id',$producto->id)->first();
                $bodega->stock -= $request->cantidad;
                $bodega->save();
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
            $bodega = Bodega::where('bodega_id', 1)->where('producto_id', $detalle->producto_id)->first();
            $bodega->stock += $detalle->cantidad;
            $bodega->save();
        $detalle->delete();

        return Response()->json($detalle, 201);

    }

}
