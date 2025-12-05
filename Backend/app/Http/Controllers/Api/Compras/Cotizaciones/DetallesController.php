<?php

namespace App\Http\Controllers\Api\Compras\Cotizaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Compras\Compra as Cotizacion;
use App\Models\Compras\Detalle;
use Carbon\Carbon;
use App\Http\Requests\Compras\Cotizaciones\StoreDetalleCotizacionRequest;

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
                                ->when($request->stock_compra, function($query) use ($request){
                                    return $query->whereHas('bodegas', function($query){
                                        return $query->where('bodega_id', 2)->whereRaw('stock <= stock_min');
                                    });
                                })
                                ->orderBy('id','dsc')->paginate(100000);

            return Response()->json($detalles, 200);
    }

    public function store(StoreDetalleCotizacionRequest $request)
    {

        if($request->id)
            $detalle = Detalle::findOrFail($request->id);
        else
            $detalle = new Detalle;

        $detalle->fill($request->all());
        $detalle->save();

        $cotizacion = Cotizacion::where('id', $request->compra_id)->with('detalles')->first();
        $cotizacion->total = $cotizacion->detalles()->sum('total');
        $cotizacion->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        $detalle = Detalle::findOrFail($id);
        $detalle->delete();
        
        $cotizacion = Cotizacion::where('id', $detalle->compra_id)->first();
        $cotizacion->total = $cotizacion->detalles()->sum('total');
        $cotizacion->save();


        return Response()->json($detalle, 201);

    }

}
