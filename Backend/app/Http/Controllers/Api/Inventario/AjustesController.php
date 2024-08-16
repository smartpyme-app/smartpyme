<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use App\Models\Admin\Tanque;

use App\Exports\AjustesExport;
use Maatwebsite\Excel\Facades\Excel;

class AjustesController extends Controller
{
    

    public function index(Request $request) {
       
        $ajustes = Ajuste::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega, function($query) use ($request){
                                return $query->whereHas('sucursal', function($q) use ($request){
                                    $q->where('id_bodega', $request->id_bodega);
                                });
                            })
                            ->when($request->id_usuario, function($query) use ($request){
                                return $query->whereHas('usuario', function($q) use ($request){
                                    $q->where('id_usuario', $request->id_usuario);
                                });
                            })
                            ->when($request->search, function($query) use ($request){
                                return $query->whereHas('producto', function($q) use ($request){
                                    $q->where('nombre', 'like',  '%'. $request->search . '%');
                                });
                            })
                            ->when($request->estado, function($query) use ($request){
                                $query->where('estado', $request->estado);
                            })
                            ->when($request->id_producto, function($query) use ($request){
                                return $query->where('id_producto', $request->id_producto);
                            })
                            ->orderBy($request->orden, $request->direccion)
                            ->paginate($request->paginate);

        return Response()->json($ajustes, 200);

    }


    public function read($id) {

        $ajuste = Ajuste::findOrFail($id);
        return Response()->json($ajuste, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'id_producto'       => 'required|numeric',
            'id_bodega'       => 'required|numeric',
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
                        
            $inventario = Inventario::where('id_bodega', $request['id_bodega'])->where('id_producto', $ajuste->id_producto)->first();
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
        $ajuste->estado = 'Cancelado';
        $ajuste->save();

        // Ajustar inventario
            $inventario = Inventario::where('id_producto', $ajuste->id_producto)
                                    ->where('id_bodega', $ajuste->id_bodega)
                                    ->first();
            if ($inventario) {
                $inventario->stock -= $ajuste->ajuste;
                $inventario->save();
                $inventario->kardex($ajuste, $ajuste->ajuste * -1);
            }

        return Response()->json($ajuste, 201);

    }

    public function export(Request $request){
        $ajustes = new AjustesExport();
        $ajustes->filter($request);

        return Excel::download($ajustes, 'ajustes.xlsx');
    }



}
