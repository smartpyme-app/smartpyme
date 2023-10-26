<?php

namespace App\Http\Controllers\Api\Compras\Devoluciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Compras\Devoluciones\Devolucion;
use App\Models\Registros\Proveedor;
use App\Models\Compras\Devoluciones\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use App\Models\Admin\Tanque;
use Illuminate\Support\Facades\DB;

class DevolucionComprasController extends Controller
{
    

    public function index() {
       
        $compras = Devolucion::orderBy('id','desc')->paginate(10);
        return Response()->json($compras, 200);
           
    }

    public function read($id) {

        $compra = Devolucion::where('id', $id)->with('detalles', 'proveedor')->first();
        return Response()->json($compra, 200);
 
    }

    public function search($txt) {

        $compras = Devolucion::whereHas('proveedor', function($query) use ($txt)
                    {
                        $query->where('nombre', 'like' ,'%' . $txt . '%');
                    })->paginate(10);

        return Response()->json($compras, 200);

    }

    public function filter(Request $request) {

        $compras = Devolucion::when($request->inicio, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
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
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($compras, 200);

    }



    public function store(Request $request)
    {

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'proveedor_id'      => 'required',
            'usuario_id'        => 'required',
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
            'proveedor_id'      => 'required',
            'detalles'          => 'required',
            'iva'               => 'required|numeric',
            // 'subcosto'          => 'required|numeric',
            'subtotal'          => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'required|max:255',
            'compra_id'         => 'required',
            'usuario_id'        => 'required',
            'empresa_id'        => 'required',
        ],[
            'detalles.required' => 'No hay detalles agregados'
        ]);


        DB::beginTransaction();
         
        try {

        // Compra
            if($request->id)
                $compra = Devolucion::findOrFail($request->id);
            else
                $compra = new Devolucion;

            $compra->fill($request->all());
            $compra->save();


        // Detalles

            foreach ($request->detalles as $det) {
                $detalle = new Detalle;
                $det['devolucion_id'] = $compra->id;
                $detalle->fill($det);
                $detalle->save();
                
                // Actualizar inventario
                $producto = Producto::findOrFail($det['producto_id']);

                $inventario = Inventario::where('producto_id', $producto->id)->where('bodega_id', $compra->compra->bodega_id)->first();

                if ($inventario) {
                    $inventario->stock -= $det['cantidad'];
                    $inventario->save();
                    $inventario->kardex($compra, $det['cantidad']);
                }

            }

        DB::commit();
        return Response()->json($compra, 200);

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
