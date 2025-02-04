<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Inventario\KardexExport;

class KardexController extends Controller
{
    

    public function index(Request $request) {

        $producto = Producto::where('id', $request->id_producto)->with('inventarios')->firstOrFail();

        $kardex = Kardex::where('id_producto', $producto->id)
                        ->when($request->id_inventario, function($q) use ($request){
                            $q->where('id_inventario', $request->id_inventario);
                        })
                        ->when($request->inicio, function($q) use ($request){
                            $q->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($q) use ($request){
                            $q->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->detalle, function($q) use ($request){
                            return $q->where('detalle', 'like' ,'%' . $request->detalle . '%');
                        })
                        ->orderBy($request->orden, $request->direccion)
                        ->orderBy('id', 'desc')
                        ->get();
        

        $producto->movimientos = $kardex;

        return Response()->json($producto, 200);

    }


    public function read($id) {

        $kardex = Kardex::findOrFail($id);
        return Response()->json($kardex, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required',
            'id_producto'   => 'required',
            'id_sucursal' => 'required|numeric',
            'detalle'       => 'required',
            'referencia'    => 'sometimes|max:255',
            'entrada_cantidad'      => 'required|numeric',
            'entrada_valor'         => 'required|numeric',
            'salida_cantidad'      => 'required|numeric',
            'salida_valor'         => 'required|numeric',
            'total_cantidad'      => 'required|numeric',
            'total_valor'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
        ]);

        if($request->id)
            $kardex = Kardex::findOrFail($request->id);
        else
            $kardex = new Kardex;

        // Actualizar inventario
            $producto = Producto::withoutGlobalScopes()->findOrFail($request->id_producto);
            $inventario = Inventario::where('id', $request->id_sucursal)->where('id_producto', $producto->id)->first();
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

    public function export(Request $request){
        $kardex = new KardexExport();
        $kardex->filter($request);

        return Excel::download($kardex, 'kardex.xlsx');
    }


}
