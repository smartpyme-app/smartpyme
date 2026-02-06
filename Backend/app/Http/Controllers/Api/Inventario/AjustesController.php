<?php

namespace App\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Inventario\Kardex;
use App\Models\Admin\Tanque;

use App\Exports\Inventario\AjustesExport;
use Maatwebsite\Excel\Facades\Excel;

class AjustesController extends Controller
{
    

    public function index(Request $request) {
       
        $ajustes = Ajuste::when($request->fin, function($query) use ($request){
                                return $query->whereBetween('created_at', [$request->inicio . ' 00:00:00', $request->fin . ' 23:59:59']);
                            })
                            ->when($request->id_bodega, function($query) use ($request){
                                return $query->whereHas('bodega', function($q) use ($request){
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
            'lote_id'           => 'nullable|numeric|exists:lotes,id',
        ]);

        // Verificar si el producto tiene inventario por lotes
        $producto = Producto::findOrFail($request->id_producto);
        if ($producto->inventario_por_lotes) {
            // Si tiene lotes, el lote_id es requerido
            if (!$request->lote_id) {
                return response()->json([
                    'error' => 'Debe seleccionar un lote para este producto.'
                ], 400);
            }
        }

        if($request->id)
            $ajuste = Ajuste::findOrFail($request->id);
        else
            $ajuste = new Ajuste;

        $ajuste->fill($request->all());
        if ($request->has('lote_id') && $request->lote_id) {
            $ajuste->lote_id = $request->lote_id;
        }
        $ajuste->save(); 

        // Si el ajuste tiene lote_id, actualizar también el lote
        if ($ajuste->lote_id) {
            $lote = Lote::findOrFail($ajuste->lote_id);
            
            // Verificar que el lote pertenezca a la bodega correcta
            if ($lote->id_bodega != $request->id_bodega) {
                return Response()->json(['error' => 'El lote seleccionado no pertenece a la bodega especificada.'], 400);
            }
            
            $lote->stock = $request->stock_real;
            
            // Si es el primer ajuste y el stock_inicial es 0, actualizarlo
            if ($lote->stock_inicial == 0 && $request->stock_real > 0) {
                $lote->stock_inicial = $request->stock_real;
            }
            
            $lote->save();
        }

        // Actualizar inventario tradicional
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

        // Si el ajuste tiene lote_id, revertir el ajuste en el lote
        if ($ajuste->lote_id) {
            $lote = Lote::find($ajuste->lote_id);
            if ($lote) {
                // Revertir al stock anterior (stock_real - ajuste)
                $lote->stock = $ajuste->stock_real - $ajuste->ajuste;
                if ($lote->stock < 0) {
                    $lote->stock = 0;
                }
                $lote->save();
            }
        }

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

    public function storeLote(Request $request)
    {
        $request->validate([
            'id_producto'       => 'required|numeric',
            'id_bodega'         => 'required|numeric',
            'lote_id'           => 'required|numeric',
            'stock_actual'       => 'required|numeric',
            'stock_real'        => 'required|numeric',
            'ajuste'            => 'required|numeric',
            'concepto'          => 'required|max:255',
            'id_empresa'        => 'required|numeric',
            'id_usuario'        => 'required|numeric',
        ]);

        $lote = Lote::findOrFail($request->lote_id);
        if ($lote->id_producto != $request->id_producto || $lote->id_bodega != $request->id_bodega) {
            return response()->json([
                'error' => 'El lote no corresponde al producto o bodega especificados.'
            ], 400);
        }

        $ajuste = new Ajuste;
        $ajuste->fill($request->all());
        $ajuste->lote_id = $request->lote_id;
        $ajuste->save(); 

        // Actualizar lote
        $lote->stock = $request->stock_real;
        
        // Si es el primer ajuste y el stock_inicial es 0, actualizarlo
        if ($lote->stock_inicial == 0 && $request->stock_real > 0) {
            $lote->stock_inicial = $request->stock_real;
        }
        
        $lote->save();

        // Actualizar inventario tradicional también para mantener consistencia
        $inventario = Inventario::where('id_bodega', $request['id_bodega'])
            ->where('id_producto', $ajuste->id_producto)
            ->first();
        if ($inventario) {
            $inventario->stock += $request->ajuste;
            $inventario->save();
            $inventario->kardex($ajuste, $request->ajuste);
        }

        return Response()->json($ajuste, 200);
    }

}
