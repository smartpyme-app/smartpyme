<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Inventario;

class InventariosController extends Controller
{
    

    public function index($bodega) {
       
        $inventarios = Inventario::where('bodega_id', $bodega)->with('producto')->orderBy('created_at','desc')->paginate(10);

        return Response()->json($inventarios, 200);

    }

    public function productos($id) {

            $productos = Inventario::where('bodega_id', $id)->with('producto')->paginate(50);

            return Response()->json($productos, 200);
    }

    public function search($bodega_id, $txt) {

        $productos = Inventario::where('bodega_id', $bodega_id)->with('producto')
                                    ->whereHas('producto', function($query) use ($txt){
                                        return $query->where('nombre', 'like' ,'%' . $txt . '%');
                                    })->paginate(30);

        return Response()->json($productos, 200);

    }

    public function productosFiltrar(Request $request) {

            $productos = Inventario::where('bodega_id', $request->bodega_id)
                                ->with('producto')
                                ->when($request->subcategorias_id, function($query) use ($request){
                                    $query->whereHas('producto', function($query) use ($request){
                                        return $query->whereIn('subcategoria_id', $request->subcategorias_id);
                                    });
                                })
                                ->when($request->stock_bodega, function($query) use ($request){
                                    return $query->whereRaw('stock <= stock_min');
                                })->paginate(20);

            return Response()->json($productos, 200);
    }


    public function read($id) {
        
        $bodega = Inventario::findOrFail($id);
        return Response()->json($bodega, 200);

    }

    public function store(Request $request) {
    	
        $request->validate([
            'bodega_id'    => 'required|numeric',
            'producto_id'    => 'required|numeric',
            'stock'          => 'required|numeric',
            'stock_min'      => 'required|numeric',
            'stock_max'      => 'required|numeric',
        ]);


        if($request->id){
            $inventario = Inventario::findOrFail($request->id);
        }
        else{

            $inventario = new Inventario;
            $existe = Inventario::where('producto_id', $request->producto_id)->where('bodega_id', $request->bodega_id)->first();

            if($existe)
                return  Response()->json(['error' => 'Ya ha sido configurada la bodega', 'code' => 400], 400);
        }
        
        $inventario->fill($request->all());
        $inventario->save();

        return Response()->json($inventario, 200);


    }

    public function delete($id)
    {
        $inventario = Inventario::findOrFail($id);
        $inventario->delete();

        return Response()->json($inventario, 201);

    }

    public function bodegaSearch($txt) {
        $productoInventario = Inventario::where('bodega_id', 1)->whereHas('producto', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('codigo', 'like' ,'%' . $txt . '%');

                    })->with('producto')->orderBy('stock', 'desc')->paginate(10);


    	return Response()->json($productoInventario, 200);

    }

    public function ventaSearch($txt) {

    	$productoVenta = Inventario::where('bodega_id', 2)->whereHas('producto', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('codigo', 'like' ,'%' . $txt . '%');

                    })->with('producto')->orderBy('stock', 'desc')->paginate(10);


        return Response()->json($productoVenta, 200);

    }


}
