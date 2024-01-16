<?php

namespace App\Http\Controllers\Api\Compras\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Compras\Devoluciones\Devolucion;
use App\Models\Compras\Compra;
use App\Models\Registros\Proveedor;
use App\Models\Compras\Devoluciones\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use App\Models\Admin\Tanque;
use Illuminate\Support\Facades\DB;

class DevolucionComprasController extends Controller
{
    

    public function index(Request $request) {
       
        $ventas = Devolucion::when($request->buscador, function($query) use ($request){
                            return $query->where('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('enable', $request->estado);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            return $query->whereHas('proveedor', function($query) use ($request)
                            {
                                $query->where('id_proveedor', $request->id_proveedor);

                            });
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($ventas, 200);

    }

    public function read($id) {

        $compra = Devolucion::where('id', $id)->with('detalles', 'proveedor')->first();
        return Response()->json($compra, 200);
 
    }



    public function store(Request $request)
    {

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'id_proveedor'      => 'required',
            'id_usuario'        => 'required',
        ]);

        if($request->id)
            $compra = Devolucion::findOrFail($request->id);
        else
            $compra = new Devolucion;
        
        $compra->fill($request->all());
        $compra->save();

        return Response()->json($compra, 200);

    }

    public function delete($id)
    {
        $compra = Devolucion::where('id', $id)->with('detalles')->firstOrFail();
        foreach ($compra->detalles as $detalle) {
            $detalle->delete();
        }
        $compra->delete();

        return Response()->json($compra, 201);
    }


    public function facturacion(Request $request){
        $request->validate([
            'fecha'             => 'required',
            'tipo'              => 'required',
            'id_proveedor'      => 'required',
            'detalles'          => 'required',
            'iva'               => 'required|numeric',
            // 'subcosto'          => 'required|numeric',
            'sub_total'          => 'required|numeric',
            'total'             => 'required|numeric',
            'observaciones'     => 'required|max:255',
            'id_compra'         => 'required',
            'id_usuario'        => 'required',
            'id_empresa'        => 'required',
        ],[
            'detalles.required' => 'No hay detalles agregados'
        ]);


        DB::beginTransaction();
         
        try {

        // Compra
            if($request->id)
                $devolucion = Devolucion::findOrFail($request->id);
            else
                $devolucion = new Devolucion;

            $devolucion->fill($request->all());
            $devolucion->save();

            $compra = Compra::findOrFail($request['id_compra']);
            $compra->estado = 'Anulada';
            $compra->save();


        // Detalles

            foreach ($request->detalles as $det) {
                $detalle = new Detalle;
                $det['id_devolucion_compra'] = $devolucion->id;
                $detalle->fill($det);
                $detalle->save();
                
                // Actualizar inventario
                $inventario = Inventario::where('id_producto', $det['id_producto'])->where('id_sucursal', $request->id_sucursal)->first();

                if ($inventario) {
                    $inventario->stock -= $det['cantidad'];
                    $inventario->save();
                    $inventario->kardex($devolucion, $det['cantidad']);
                }

            }

        DB::commit();
        return Response()->json($devolucion, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
        
        return Response()->json($compra, 200);

    }



}
