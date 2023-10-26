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

        $ajustes = Ajuste::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_sucursal, function($query) use ($request){
                                return $query->whereHas('sucursal', function($q) use ($request){
                                    $q->where('id_sucursal', $request->id_sucursal);
                                });
                            })
                            ->when($request->nombre, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', $request->nombre);
                                });
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($ajustes, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_producto'       => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
            'stock_actual'      => 'required|numeric',
            'stock_real'        => 'required|numeric',
            'ajuste'            => 'required|numeric',
            'concepto'          => 'required|max:255',
            'id_empresa'        => 'required|numeric',
            'id_usuario'        => 'required|numeric',
        ]);

        if($request->id)
            $ajuste = Ajuste::findOrFail($request->id);
        else
            $ajuste = new Ajuste;

        $ajuste->fill($request->all());
        $ajuste->save(); 

        // Actualizar inventario
                        
            $inventario = Inventario::where('id_sucursal', $request['id_sucursal'])->where('id_producto', $ajuste->id_producto)->first();
            if ($inventario) {
                $inventario->stock += $request->ajuste;
                $inventario->save();
                $inventario->kardex($ajuste, $request->ajuste);
            }


        return Response()->json($ajuste, 200);

    }

    public function delete($id)
    {
        $ajuste = Ajuste::findOrFail($id);
        $ajuste->delete();

        return Response()->json($ajuste, 201);

    }



}
