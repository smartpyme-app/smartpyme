<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use JWTAuth;
use App\Models\Admin\Empresa;
//use App\Models\Admin\Bodega;
use App\Models\Inventario\Bodega;
use App\Http\Requests\Admin\Bodegas\StoreBodegaRequest;

class BodegasController extends Controller
{
    

    public function index() {
       
        $bodegas = Bodega::all();

        return Response()->json($bodegas, 200);

    }


    public function read($id) {
        
        $bodega = Bodega::where('id', $id)->firstOrFail();

        $productos = $bodega->productos()->get();
        $bodega->productos  = $productos->count();
        $bodega->cantidad   = $productos->sum('stock');

        return Response()->json($bodega, 200);

    }

    public function store(StoreBodegaRequest $request) {

        if($request->id)
            $bodega = Bodega::findOrFail($request->id);
        else
            $bodega = new Bodega;
        
        $bodega->fill($request->all());
        $bodega->save();

        return Response()->json($bodega, 200);


    }

    public function delete($id)
    {
       
        $bodega = Bodega::findOrFail($id);
        if ($bodega->productos()->count() > 0)
            return  Response()->json(['message' => 'La bodega tiene productos', 'code' => 402], 402);
        $bodega->delete();

        return Response()->json($bodega, 201);

    }

    public function reporte($id, $cat) {

        $bodega = Bodega::where('id', $id)->firstOrFail();
        $productos = [];

        $subcategorias = explode(',', $cat);

        $productos = $bodega->productos()->with('producto')
        ->when($subcategorias, function($query) use ($subcategorias){
            $query->whereHas('producto', function($query) use ($subcategorias){
                return $query->whereIn('subcategoria_id', $subcategorias);
            });
        })->get();

        $bodega->fecha = Carbon::now();
        $bodega->usuario = JWTAuth::parseToken()->authenticate()->name;
        $empresa = Empresa::find(1);

        $p = collect();

        foreach ($productos as $producto) {
            $prod = $producto->producto()->first();
            $p->push([
                'nombre'     => $prod->nombre,
                'categoria'     => $prod->nombre_categoria,
                'subcategoria'  => $prod->nombre_subcategoria,
                'stock'         => $prod->inventarios()->where('bodega_id', $id)->pluck('stock')->first(),
                'costo'         => $prod->costo,
                'costoTotal'    => $prod->costo * $prod->inventarios()->where('bodega_id', $id)->pluck('stock')->first(),
                'precio'        => $prod->precio,
                'precioTotal'   => $prod->precio * $prod->inventarios()->where('bodega_id', $id)->pluck('stock')->first(),
            ]);
        }

        $bodega->productos = $p->sortBy([ ['categoria', 'asc'],['nombre', 'asc']]);
        // return $bodega;
        $reportes = \PDF::loadView('reportes.bodegas', compact('bodega', 'empresa'));
        return $reportes->stream();

    }


}
