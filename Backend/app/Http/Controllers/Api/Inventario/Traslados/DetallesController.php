<?php

namespace App\Http\Controllers\Api\Inventario\Traslados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Traslados\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;

class DetallesController extends Controller
{
    

    public function index() {

        $detalles = Detalle::orderBy('id','desc')->paginate(7);

        return Response()->json($detalles, 200);

    }


    public function read($id) {

        $detalle = Detalle::findOrFail($id);
        return Response()->json($detalle, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'producto_id'       => 'required',
            'cantidad'          => 'required',
            'traslado_id'       => 'required'
        ]);

        if($request->id)
            $detalle = Detalle::findOrFail($request->id);
        else
            $detalle = new Detalle;

        $detalle->fill($request->all());
        $detalle->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        $detalle = Detalle::findOrFail($id);

        // Actualizar inventario si ya ha sido efectuado
        if ($detalle->traslado()->first()->estado == 'Aprobado') {
            $producto = Producto::findOrFail($detalle->producto_id);
            $bodega = Inventario::where('bodega_id', $detalle->traslado()->first()->origen_id)->where('producto_id',$producto->id)->decrement('stock', $detalle->cantidad);
            $bodega = Inventario::where('bodega_id', $detalle->traslado()->first()->destino_id)->where('producto_id',$producto->id)->increment('stock', $detalle->cantidad);
        }

        $detalle->delete();

        return Response()->json($detalle, 201);

    }

}
