<?php

namespace App\Http\Controllers\Api\Ventas\Cotizaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Venta as Orden;
use App\Models\Ventas\Detalle;
use Carbon\Carbon;

class DetallesController extends Controller
{
    
    public function index() {
       
        $detalles = Detalle::orderBy('id','asc')->paginate(10);

        return Response()->json($detalles, 200);

    }

    public function read($id) {

        $detalle = Detalle::where('id', $id)->firstOrFail();
        return Response()->json($detalle, 200);

    }

    public function search($txt) {

        $detalles = Detalle::where('categoria_id', '!=', 1)->where('nombre', 'like' ,'%' . $txt . '%')->paginate(10);
        return Response()->json($detalles, 200);

    }

    public function filter(Request $request) {

            // $star = $request->fecha_ini . ' ' . $request->hora_ini;
            // $end = $request->fecha_fin . ' ' . $request->hora_fin;

            $detalles = Detalle::where('categoria_id', '!=', 1)->with('bodegas')//->whereBetween('created_at', [$star, $end])
                                ->when($request->categoria_id, function($query) use ($request){
                                    return $query->where('categoria_id', $request->categoria_id);
                                })
                                ->when($request->stock_bodega, function($query) use ($request){
                                    return $query->whereHas('bodegas', function($query){
                                        return $query->where('bodega_id', 1)->whereRaw('stock <= stock_min');
                                    });
                                })
                                ->when($request->stock_venta, function($query) use ($request){
                                    return $query->whereHas('bodegas', function($query){
                                        return $query->where('bodega_id', 2)->whereRaw('stock <= stock_min');
                                    });
                                })
                                ->orderBy('id','dsc')->paginate(100000);

            return Response()->json($detalles, 200);
    }

    public function store(Request $request)
    {

        $request->validate([
            'producto_id'   => 'required|numeric',
            // 'estado'        => 'required|max:255',
            'cantidad'      => 'required|numeric',
            'precio'        => 'required|numeric',
            'costo'         => 'required|numeric',
            'descuento'     => 'required|numeric',
            'total'         => 'required|numeric',
            'nota'          => 'sometimes|max:255',
            'venta_id'    => 'required|required'
        ]);

        if($request->id)
            $detalle = Detalle::findOrFail($request->id);
        else
            $detalle = new Detalle;

        $detalle->fill($request->all());
        $detalle->save();

        $orden = Orden::where('id', $request->venta_id)->with('detalles')->first();
        $orden->total = $orden->detalles()->sum('total');
        $orden->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        $detalle = Detalle::findOrFail($id);
        $detalle->delete();
        
        $orden = Orden::where('id', $detalle->venta_id)->first();
        $orden->total = $orden->detalles()->sum('total');
        $orden->save();


        return Response()->json($detalle, 201);

    }

}
