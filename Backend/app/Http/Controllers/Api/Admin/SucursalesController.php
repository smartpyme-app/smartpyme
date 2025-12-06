<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\Sucursal;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\Admin\Sucursales\StoreSucursalRequest;

class SucursalesController extends Controller
{


    // public function index(Request $request) {
    //     Log::info($request->all());

    //     $sucursales = Sucursal::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
    //                             ->when($request->estado !== null, function($q) use ($request){
    //                                 $q->where('activo', !!$request->estado);
    //                             })
    //                             ->when($request->buscador, function($query) use ($request){
    //                                 return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
    //                                              ->orwhere('telefono', 'like' ,"%" . $request->buscador . "%");
    //                             })
    //                             // ->orderBy('enable', 'desc')
    //                             ->orderBy($request->orden, $request->direccion ?? 'asc')
    //                             ->paginate($request->paginate);

    //     return Response()->json($sucursales, 200);

    // }

    public function index(Request $request) {
        Log::info($request->all());

        $query = Sucursal::where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
            ->when($request->estado !== null, function($q) use ($request){
                $q->where('activo', !!$request->estado);
            })
            ->when($request->buscador, function($query) use ($request){
                return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                             ->orwhere('telefono', 'like' ,"%" . $request->buscador . "%");
            });

        // Aplicar ordenamiento solo si se proporciona una columna válida
        if ($request->filled('orden')) {
            $query->orderBy($request->orden, $request->direccion ?? 'asc');
        } else {
            // Ordenamiento por defecto
            $query->orderBy('id', 'desc');
        }

        $sucursales = $query->paginate($request->paginate);

        return Response()->json($sucursales, 200);
    }

    public function list()
    {

        $sucursales = Sucursal::orderby('nombre')
            ->where('activo', true)
            ->get();

        return Response()->json($sucursales, 200);
    }

    //lista de marcas por empresa estan en la tabla productos y el campo marca

    public function listaMarcas()
    {

        $marcas = Producto::select('marca as nombre')
            ->where('id_empresa', JWTAuth::parseToken()->authenticate()->id_empresa)
            ->whereNotNull('marca')
            ->where('marca', '!=', '')
            ->groupBy('marca')
            ->get();

        return Response()->json($marcas, 200);
    }



    public function read($id)
    {

        $sucursal = Sucursal::where('id', $id)->firstOrFail();
        return Response()->json($sucursal, 200);
    }

    public function store(StoreSucursalRequest $request)
    {

        if ($request->id)
            $sucursal = Sucursal::findOrFail($request->id);
        else
            $sucursal = new Sucursal;

        $sucursal->fill($request->all());
        $sucursal->save();
        //Crear bodega por defecto
        if (!$request->id) {
            $bodega = Bodega::create([
                'nombre' => $sucursal->nombre,
                'activo' => true,
                'id_sucursal' => $sucursal->id,
                'id_empresa' => $sucursal->id_empresa
            ]);

            // Configurar inventarios para los productos
            $productos = Producto::whereIn('tipo', ['Producto', 'Compuesto'])->get();
            foreach ($productos as $producto) {
                $inventario = new Inventario;
                $inventario->id_bodega    = $bodega->id;
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
