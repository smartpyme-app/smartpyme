<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Sucursal;
use App\Models\Inventario\Inventario;

class SucursalesController extends Controller
{
    

    public function index($producto) {
       
        $sucursales = Sucursal::where('producto_id', $producto)
                                    ->with('bodegas', 'inventarios', 'bodega_venta')
                                    ->orderBy('id','asc')->get();
        
        return Response()->json($sucursales, 200);

    }

    public function filterSucursal(Request $request) {

            $productos = Sucursal::where('producto_id', 1)->with('producto')
                                ->when($request->categorias_id, function($query) use ($request){
                                    $query->whereHas('producto', function($query) use ($request){
                                        return $query->whereIn('categoria_id', $request->categorias_id);
                                    });
                                })
                                ->when($request->stock_bodega, function($query) use ($request){
                                    return $query->whereRaw('stock <= stock_min');
                                })->paginate(100000);

            return Response()->json($productos, 200);
    }

    public function filterVenta(Request $request) {

            $productos = Sucursal::where('producto_id', 2)->with('producto')
                                ->when($request->categorias_id, function($query) use ($request){
                                    $query->whereHas('producto', function($query) use ($request){
                                        return $query->whereIn('categoria_id', $request->categorias_id);
                                    });
                                })
                                ->when($request->stock_venta, function($query) use ($request){
                                    return $query->whereRaw('stock <= stock_min');
                                })->paginate(100000);

            return Response()->json($productos, 200);
    }


    public function read($id) {
        
        $bodega = Sucursal::findOrFail($id);
        return Response()->json($bodega, 200);

    }

    public function store(Request $request) {
        
        $request->validate([
            'producto_id'    => 'required|numeric',
            'activo'          => 'required|boolean',
            'sucursal_id'    => 'required|numeric',
        ]);


        if($request->id){
            $sucursal = Sucursal::findOrFail($request->id);
        }
        else{

            $sucursal = new Sucursal;
            $existe = Sucursal::where('sucursal_id', $request->sucursal_id)->where('producto_id', $request->producto_id)->first();

            if($existe)
                return  Response()->json(['error' => 'Ya ha sido configurada la sucursal', 'code' => 400], 400);
        }
        
        $sucursal->fill($request->all());
        $sucursal->save();

        return Response()->json($sucursal, 200);


    }

    public function delete($id)
    {
        $inventario = Sucursal::findOrFail($id);
        $inventario->delete();

        return Response()->json($inventario, 201);

    }

    public function bodegaSearch($txt) {
        $productoSucursal = Sucursal::where('producto_id', 1)->whereHas('producto', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('codigo', 'like' ,'%' . $txt . '%');

                    })->with('producto')->orderBy('stock', 'desc')->paginate(10);


        return Response()->json($productoSucursal, 200);

    }

    public function ventaSearch($txt) {

        $productoVenta = Sucursal::where('producto_id', 2)->whereHas('producto', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('codigo', 'like' ,'%' . $txt . '%');

                    })->with('producto')->orderBy('stock', 'desc')->paginate(10);


        return Response()->json($productoVenta, 200);

    }


}
