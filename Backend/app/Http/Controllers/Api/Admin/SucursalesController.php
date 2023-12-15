<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use JWTAuth;

class SucursalesController extends Controller
{
    

    public function index(Request $request) {
       
        $sucursales = Sucursal::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
                                ->when($request->estado !== null, function($q) use ($request){
                                    $q->where('activo', !!$request->estado);
                                })
                                ->when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                                 ->orwhere('telefono', 'like' ,"%" . $request->buscador . "%");
                                })
                                // ->orderBy('enable', 'desc')
                                ->orderBy($request->orden, $request->direccion)
                                ->paginate($request->paginate);

        return Response()->json($sucursales, 200);

    }

    public function list() {
       
        $sucursales = Sucursal::orderby('nombre')
                                ->where('activo', true)
                                ->get();

        return Response()->json($sucursales, 200);

    }
    
    public function read($id) {

        $sucursal = Sucursal::where('id', $id)->firstOrFail();
        return Response()->json($sucursal, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $sucursal = Sucursal::findOrFail($request->id);
        else
            $sucursal = new Sucursal;
        
        $sucursal->fill($request->all());
        $sucursal->save();

        // Configurar inventarios para los productos
        if (!$request->id) {
            $productos = Producto::whereIn('tipo', ['Producto', 'Compuesto'])->get();
            foreach ($productos as $producto) {
                $inventario = new Inventario;
                $inventario->id_sucursal    = $sucursal->id;
                $inventario->stock          = 0;
                $inventario->id_producto    = $producto->id;
                $inventario->save();
            }
        }

        return Response()->json($sucursal, 200);

    }

    public function delete($id)
    {
        $sucursal = Sucursal::findOrFail($id);
        $sucursal->delete();

        return Response()->json($sucursal, 201);

    }

}
