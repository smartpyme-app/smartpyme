<?php

namespace App\Http\Controllers\Api\Transporte\Mantenimientos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Transporte\Mantenimientos\Mantenimiento;
use App\Models\Transporte\Mantenimientos\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use Carbon\Carbon;
use JWTAuth;

use Illuminate\Support\Facades\DB;

class MantenimientosController extends Controller
{
    

    public function index() {
       
        $mantenimientos = Mantenimiento::orderBy('id','desc')->paginate(10);
       
        return Response()->json($mantenimientos, 200);

    }



    public function read($id) {

        $mantenimiento = Mantenimiento::where('id', $id)->with('detalles', 'flota')->first();

        return Response()->json($mantenimiento, 200);

    }

    public function search($txt) {

        $mantenimientos = Mantenimiento::whereHas('cliente', function($query) use ($txt) {
                                    $query->where('nombre', 'like' ,'%' . $txt . '%');
                                })
                                ->orwhere('correlativo', 'like', '%'.$txt.'%')
                                ->orwhere('tipo_documento', 'like', '%'.$txt.'%')
                                ->orwhere('estado', 'like', '%'.$txt.'%')
                                ->orwhere('nota', 'like', '%'.$txt.'%')
                                ->orwhere('metodo_pago', 'like', '%'.$txt.'%')
                                ->orwhere('referencia', 'like', '%'.$txt.'%')
                                ->paginate(10);

        return Response()->json($mantenimientos, 200);

    }

    public function filter(Request $request) {


        $mantenimientos = Mantenimiento::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->when($request->tipo_documento, function($query) use ($request){
                            return $query->where('tipo_documento', $request->tipo_documento);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($mantenimientos, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required',
            'cliente_id'        => 'required',
            'usuario_id'        => 'required',
        ]);

        if($request->id)
            $mantenimiento = Mantenimiento::findOrFail($request->id);
        else
            $mantenimiento = new Mantenimiento;
        
        $mantenimiento->fill($request->all());
        $mantenimiento->save();        

        return Response()->json($mantenimiento, 200);

    }

    public function delete($id)
    {
        $mantenimiento = Mantenimiento::findOrFail($id);

        foreach ($mantenimiento->detalles as $detalle) {
            $detalle->delete();
        }
        $mantenimiento->delete();

        return Response()->json($mantenimiento, 201);

    }



    // Facturacion

    public function corte() {

        $usuario = JWTAuth::parseToken()->authenticate();
       
        $caja   = Caja::where('id', $usuario->caja_id)->with('corte')->firstOrFail();
        $corte  = $caja->corte;
        $mantenimientos = $corte->mantenimientos()->orderBy('id', 'desc')
                            ->paginate(30);

        return Response()->json($mantenimientos, 200);

    }

    public function facturacion(Request $request){

        $request->validate([
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            'tipo'              => 'required|max:255',
            'detalles'          => 'required',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'flota_id'          => 'required|numeric',
            'usuario_id'        => 'required|numeric',
            'bodega_id'         => 'required|numeric',
            'sucursal_id'       => 'required|numeric',
        ], [
            'flota_id.required' => 'Seleccione la flota',
            'detalles.required' => 'Agrege al menos un detalle al mantenimiento',
        ]);

        DB::beginTransaction();
         
        try {
        
        // Guardamos la venta
            if($request->id)
                $mantenimiento = Mantenimiento::findOrFail($request->id);
            else
                $mantenimiento = new Mantenimiento;
            $mantenimiento->fill($request->all());
            $mantenimiento->save();

        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['mantenimiento_id'] = $mantenimiento->id;
                

                $detalle->fill($det);
                $detalle->save();

                // Actualizar inventario
                if ($mantenimiento->estado == 'Completado') {
                    $producto = Producto::where('id', $det['producto_id'])->with('composiciones')->firstOrFail();

                    $inventario = Inventario::where('producto_id', $producto->id)->where('bodega_id', $mantenimiento->bodega_id)->first();
                    if ($inventario) {
                        $inventario->stock -= $det['cantidad'];
                        $inventario->save();
                        $inventario->kardex($mantenimiento, $det['cantidad']);
                    }
                }

            }
        
        DB::commit();
        return Response()->json($mantenimiento, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
        

    }

    public function ordenes() {

        $usuario = JWTAuth::parseToken()->authenticate();
       
        $caja    = Caja::where('id', $usuario->caja_id)->with('corte')->firstOrFail();
        $corte   = $caja->corte;
        
        if (!$corte->cierre)
            $corte->cierre = Carbon::now()->toDateTimeString(); ;

        $mantenimientos  = $corte->mantenimientos()->where('estado', 'En Proceso')
                            ->orderBy('id', 'desc')
                            ->paginate(5000);
        

        return Response()->json($mantenimientos, 200);


    }

    public function propinas(Request $request) {

        $mantenimientos = Mantenimiento::where('propina', '>', 0)->when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($mantenimientos, 200);

    }

    public function generarDoc($id){
        $mantenimiento = Mantenimiento::where('id', $id)->with('flota', 'detalles', 'sucursal.empresa')->firstOrFail();

        $reportes = \PDF::loadView('reportes.transporte.mantenimiento', compact('mantenimiento'))->setPaper('letter', 'portrait');
        return $reportes->stream();

        return view('reportes.transporte.mantenimiento', compact('mantenimiento'));

    }

    public function anularDoc(){

        return view('reportes.anulacion');

    }

    public function flota($id) {
       
        $mantenimientos = Mantenimiento::where('flota_id', $id)->paginate(5);

        return Response()->json($mantenimientos, 200);

    }


}
