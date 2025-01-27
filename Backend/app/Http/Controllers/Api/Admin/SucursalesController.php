<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Log;
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

    //lista de marcas por empresa estan en la tabla productos y el campo marca

    public function listaMarcas() {
       
        $marcas = Producto::select('marca as nombre')
                                ->where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
                                ->whereNotNull('marca')
                                ->where('marca', '!=', '')
                                ->groupBy('marca')
                                ->get();

        return Response()->json($marcas, 200);

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

        return Response()->json($sucursal, 200);

    }

    public function delete($id)
    {
        $sucursal = Sucursal::findOrFail($id);
        $sucursal->delete();

        return Response()->json($sucursal, 201);

    }

}
