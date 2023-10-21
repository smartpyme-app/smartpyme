<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;

class KardexController extends Controller
{
    

    public function index(Request $request) {

        $producto = Producto::with('inventarios')->findOrFail($request->producto_id);
        $kardex = Kardex::
                        where('producto_id', $request->producto_id)
                        ->when($request->bodega_id, function($query) use ($request){
                            return $query->whereHas('inventario', function($q) use ($request){
                                $q->where('bodega_id', $request->bodega_id);
                            });
                        })
                        // ->where('bodega_id', $request->bodega_id)
                        ->whereBetween('fecha', [$request->inicio, $request->fin])
                        ->orderBy('id','desc')
                        ->get();
        

        $producto->movimientos = $kardex;

        return Response()->json($producto, 200);

    }


    public function read($id) {

        $kardex = Kardex::findOrFail($id);
        return Response()->json($kardex, 200);

    }

    public function filter(Request $request) {

        $kardexs = Kardex::when($request->fecha_fin, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->fecha_ini, $request->fecha_fin]);
                            })
                            ->when($request->bodega_id, function($query) use ($request){
                                return $query->whereHas('inventario', function($q) use ($request){
                                    $q->where('bodega_id', $request->bodega_id);
                                });
                            })
                            ->when($request->producto_id, function($query) use ($request){
                                return $query->where('producto_id', $request->producto_id);
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($kardexs, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required',
            'producto_id'   => 'required',
            'bodega_id' => 'required|numeric',
            'detalle'       => 'required',
            'referencia'    => 'sometimes|max:255',
            'entrada_cantidad'      => 'required|numeric',
            'entrada_valor'         => 'required|numeric',
            'salida_cantidad'      => 'required|numeric',
            'salida_valor'         => 'required|numeric',
            'total_cantidad'      => 'required|numeric',
            'total_valor'         => 'required|numeric',
            'usuario_id'    => 'required|numeric',
        ]);

        if($request->id)
            $kardex = Kardex::findOrFail($request->id);
        else
            $kardex = new Kardex;

        // Actualizar inventario
            $producto = Producto::withoutGlobalScopes()->findOrFail($request->producto_id);
            $inventario = Inventario::where('id', $request->bodega_id)->where('producto_id', $producto->id)->first();
            $inventario->stock += ($request->stock_final - $request->stock_inicial);
            $inventario->save();

        $kardex->fill($request->all());
        $kardex->save();        

        return Response()->json($kardex, 200);

    }

    public function delete($id)
    {
        $kardex = Kardex::findOrFail($id);
        $kardex->delete();

        return Response()->json($kardex, 201);

    }


    public function search($txt) {

        $kardexs = Kardex::whereHas('producto', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%')
                                ->orWhere('codigo', 'like' ,'%' . $txt . '%');
                            })
                            ->orwhereHas('bodega', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%');
                            })
                            ->paginate(10);

        return Response()->json($kardexs, 200);

    }


}
