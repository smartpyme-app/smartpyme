<?php

namespace App\Http\Controllers\Api\Ventas\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\DevolucionDetalle as Detalle;
use App\Models\Inventario\Producto;
use App\Models\Admin\Bomba;
use App\Models\Admin\Tanque;
use App\Models\Inventario\Inventario;

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


    public function store(DetalleRequest $request)
    {
        if($request->id){
            $detalle = Detalle::findOrFail($request->id);
        }
        else{
            $detalle = new Detalle;

        // Actualizar inventario
            $producto = Producto::findOrFail($request->producto_id);
            if ($producto->inventario) {
                // Si es gasolina disminuir tanque
                if ($producto->categoria_id == 1) {
                    $bomba = Bomba::findOrFail($request->bomba_id);
                    
                    $tanque = Tanque::findOrFail($bomba->tanque_id);
                    $tanque->stock += $request->cantidad;
                    $bomba->lectura -= $request->cantidad;
                    $bomba->save();
                    $tanque->save();
                // Si es producto disminuir bodega
                } else {
                    $bodega = Inventario::where('bodega_id', 2)->where('producto_id',$producto->id)->first();
                    $bodega->stock += $request->cantidad;
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
                $tanque->stock -= $detalle->cantidad;
                $tanque->save();
            } else {
                $bodega = Inventario::where('bodega_id', 2)->where('producto_id', $detalle->producto_id)->first();
                $bodega->stock -= $detalle->cantidad;
                $bodega->save();
            } 
        $detalle->delete();

        return Response()->json($detalle, 201);

    }

}
