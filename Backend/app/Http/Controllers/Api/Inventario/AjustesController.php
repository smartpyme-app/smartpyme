<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use App\Models\Admin\Tanque;

class AjustesController extends Controller
{
    

    public function index() {
       
        $ajustes = Ajuste::orderBy('id','desc')->paginate(10);

        return Response()->json($ajustes, 200);

    }


    public function read($id) {

        $ajuste = Ajuste::findOrFail($id);
        return Response()->json($ajuste, 200);

    }

    public function filter(Request $request) {

        $ajustes = Ajuste::when($request->fecha_fin, function($query) use ($request){
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

        return Response()->json($ajustes, 200);
    }


    public function store(Request $request)
    {
        $request->validate([
            'producto_id'       => 'required|numeric',
            'bodega_id'         => 'required|numeric',
            'stock_inicial'     => 'required|numeric',
            'stock_final'       => 'required|numeric',
            'usuario_id'        => 'required|numeric'
        ]);

        if($request->id)
            $ajuste = Ajuste::findOrFail($request->id);
        else
            $ajuste = new Ajuste;

        $ajuste->fill($request->all());
        $ajuste->save(); 

        // Actualizar inventario
            
            $valorAjuste = $request->stock_final - $request->stock_inicial;
            
            $inventario = Inventario::where('bodega_id', $request['bodega_id'])->where('producto_id', $ajuste->producto_id)->first();
            if ($inventario) {
                $inventario->stock += $valorAjuste;
                $inventario->save();
                $inventario->kardex($ajuste, $valorAjuste);
            }


        return Response()->json($ajuste, 200);

    }

    public function delete($id)
    {
        $ajuste = Ajuste::findOrFail($id);
        $ajuste->delete();

        return Response()->json($ajuste, 201);

    }


    public function search($txt) {

        $ajustes = Ajuste::whereHas('producto', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%')
                                ->orWhere('codigo', 'like' ,'%' . $txt . '%');
                            })
                            ->orwhereHas('bodega', function($query) use ($txt)
                            {
                                $query->where('nombre', 'like' ,'%' . $txt . '%');
                            })
                            ->paginate(10);

        return Response()->json($ajustes, 200);

    }


}
