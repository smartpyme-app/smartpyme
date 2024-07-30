<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use JWTAuth;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;

class BodegasController extends Controller
{
    

    
    public function index(Request $request) {
       
        $bodegas = Bodega::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
                                ->when($request->estado !== null, function($q) use ($request){
                                    $q->where('activo', !!$request->estado);
                                })
                                ->when($request->id_sucursal, function($q) use ($request){
                                    $q->where('id_sucursal', $request->id_sucursal);
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('descripcion', 'like' ,"%" . $request->buscador . "%");
                                })
                                ->orderBy($request->orden, $request->direccion)
                                ->paginate($request->paginate);

        return Response()->json($bodegas, 200);

    }

    public function list() {
       
        $bodegas = Bodega::orderby('nombre')
                                ->where('activo', true)
                                ->get();

        return Response()->json($bodegas, 200);

    }


    public function read($id) {
        
        $bodega = Bodega::where('id', $id)->firstOrFail();

        // $productos = $bodega->productos()->get();
        // $bodega->productos  = $productos->count();
        // $bodega->cantidad   = $productos->sum('stock');

        return Response()->json($bodega, 200);

    }

    public function store(Request $request) {
        
        $request->validate([
            'nombre'       => 'required|max:255',
            'descripcion'  => 'sometimes|max:255',
            'id_sucursal'  => 'required|numeric',
            'id_empresa'  => 'required|numeric',
        ]);

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

        if ($cat != 'undefined') {
            $subcategorias = explode(',', $cat);
        }else{
            $subcategorias = null;
        }

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
            $stock = $prod->inventarios()->where('bodega_id', $id)->pluck('stock')->first();
            $p->push([
                'nombre'     => $prod->nombre,
                'categoria'     => $prod->nombre_categoria,
                'subcategoria'  => $prod->nombre_subcategoria,
                'stock'         => $stock,
                'costo'         => $prod->costo,
                'costoTotal'    => $prod->costo * $stock,
                'precio'        => $prod->precio,
                'precioTotal'   => $prod->precio * $stock,
            ]);
        }

        $bodega->productos = $p->sortBy([ ['categoria', 'asc'],['nombre', 'asc']]);
        // return $bodega;
        $reportes = \PDF::loadView('reportes.inventario.bodegas', compact('bodega', 'empresa'));
        return $reportes->stream();

    }


}
