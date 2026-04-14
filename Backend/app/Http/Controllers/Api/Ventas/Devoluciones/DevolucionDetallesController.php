<?php

namespace App\Http\Controllers\Api\Ventas\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Devoluciones\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Http\Requests\Ventas\Devoluciones\StoreDetalleDevolucionVentaRequest;

class DevolucionDetallesController extends Controller
{
    

    public function index() {

        $detalles = Detalle::orderBy('id','desc')->paginate(10);

        return Response()->json($detalles, 200);

    }


    public function read($id) {

        $detalle = Detalle::findOrFail($id);
        return Response()->json($detalle, 200);

    }


    public function store(StoreDetalleDevolucionVentaRequest $request)
    {
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;

        // Actualizar inventario
            $producto = Producto::findOrFail($request->producto_id);
            if ($producto->inventario) {
                $bodega = Inventario::where('bodega_id', 2)->where('producto_id',$producto->id)->first();
                $bodega->stock += $request->cantidad;
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
        $producto = Producto::findOrFail($detalle->producto_id);
        $bodega = Inventario::where('bodega_id', 2)->where('producto_id', $detalle->producto_id)->first();
        $bodega->stock -= $detalle->cantidad;
        $bodega->save();
        $detalle->delete();

        return Response()->json($detalle, 201);

    }

}
