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
use Illuminate\Support\Facades\DB;
use App\Exports\DevolucionesComprasExport;
use Maatwebsite\Excel\Facades\Excel;

class DevolucionComprasController extends Controller
{
    

    public function index(Request $request) {
       
        $compras = Devolucion::when($request->buscador, function($query) use ($request){
                            return $query->where('observaciones', 'like', '%'.$request->buscador.'%');
                        })
                        ->when($request->inicio, function($query) use ($request){
                            return $query->where('fecha', '>=', $request->inicio);
                        })
                        ->when($request->fin, function($query) use ($request){
                            return $query->where('fecha', '<=', $request->fin);
                        })
                        ->when($request->estado !== null, function($q) use ($request){
                            $q->where('enable', !!$request->estado);
                        })
                        ->when($request->id_usuario, function($query) use ($request){
                            return $query->where('id_usuario', $request->id_usuario);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('enable', $request->estado);
                        })
                        ->when($request->id_proveedor, function($query) use ($request){
                            $query->where('id_proveedor', $request->id_proveedor);
                        })
                        ->when($request->referencia, function($query) use ($request){
                            $buscador = $request->referencia;
                            $query->where(function($q) use ($buscador) {
                                $q->whereHas('proveedor', function($q2) use ($buscador){
                                    $q2->where('nombre', 'like', "%{$buscador}%")
                                       ->orWhere('nombre_empresa', 'like', "%{$buscador}%")
                                       ->orWhere('ncr', 'like', "%{$buscador}%")
                                       ->orWhere('nit', 'like', "%{$buscador}%");
                                })
                                ->orWhere('referencia', 'like', "%{$buscador}%")
                                ->orWhere('observaciones', 'like', "%{$buscador}%")
                                ->orWhere('tipo_documento', 'like', "%{$buscador}%")
                                ->orWhere(function($q3) use ($buscador){
                                    $q3->where('tipo_documento', 'like', "%{$buscador}%")
                                    ->orWhere('referencia', 'like', "%{$buscador}%")
                                    ->orWhere(DB::raw("CONCAT(tipo_documento, ' #', referencia)"), 'like', "%{$buscador}%");
                                });
                            });
                        })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($compras, 200);

    }

    public function read($id) {

        $compra = Devolucion::where('id', $id)->with('detalles', 'compra', 'proveedor')->first();
        return Response()->json($compra, 200);
 
    }


    public function store(Request $request)
    {

        $request->validate([
            'fecha'             => 'required',
            'enable'            => 'required',
            'id_proveedor'      => 'required',
            'id_usuario'        => 'required',
            'tipo'              => 'required|in:devolucion,descuento_ajuste,anulacion_factura',
        ]);

        if($request->id)
            $compra = Devolucion::findOrFail($request->id);
        else
            $compra = new Devolucion;

        // Solo ajustar stocks si el tipo de devolución afecta inventario
        if ($request->tipo !== 'descuento_ajuste') {
            // Ajustar stocks
            foreach ($compra->detalles as $detalle) {

                $producto = Producto::where('id', $detalle->id_producto)
                                        ->with('composiciones')->firstOrFail();
                                        
                $inventario = Inventario::where('id_producto', $detalle->id_producto)->where('id_bodega', $compra->id_bodega)->first();
                
                // Anular y regresar stock
                if(($compra->enable != '0') && ($request['enable'] == '0')){

                    if ($inventario) {
                        $inventario->stock += $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($compra, $detalle->cantidad * -1);
                    }

                }
                // Cancelar anulación y descargar stock
                if(($compra->enable == '0') && ($request['enable'] != '0')){
                    // Aplicar stock
                    if ($inventario) {
                        $inventario->stock -= $detalle->cantidad;
                        $inventario->save();
                        $inventario->kardex($compra, $detalle->cantidad);
                    }

                }
            }
        }
        
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
            'tipo'              => 'required|in:devolucion,descuento_ajuste,anulacion_factura',
            'id_proveedor'      => 'required',
            'detalles'          => 'required',
            'iva'               => 'required|numeric',
            // 'subcosto'          => 'required|numeric',
            'sub_total'          => 'required|numeric',
            'total'             => 'required|numeric',
            'observaciones'     => 'required|max:255',
            'id_compra'         => 'required',
            'id_usuario'        => 'required',
            'id_bodega'        => 'required',
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

            // $compra = Compra::findOrFail($request['id_compra']);
            // $compra->estado = 'Anulada';
            // $compra->save();


        // Detalles

            foreach ($request->detalles as $det) {
                $detalle = new Detalle;
                $det['id_devolucion_compra'] = $devolucion->id;
                $detalle->fill($det);
                $detalle->save();
                
                // Solo actualizar inventario si el tipo de devolución afecta inventario
                if ($request->tipo !== 'descuento_ajuste') {
                    // Actualizar inventario
                    $inventario = Inventario::where('id_producto', $det['id_producto'])->where('id_bodega', $request->id_bodega)->first();

                    if ($inventario) {
                        $inventario->stock -= $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($devolucion, $det['cantidad']);
                    }
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

    public function export(Request $request){
        $compras = new DevolucionesComprasExport();
        $compras->filter($request);

        return Excel::download($compras, 'compras.xlsx');
    }



}
