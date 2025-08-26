<?php

namespace App\Http\Controllers\Api\Inventario\Entradas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Entradas\Entrada;
use App\Models\Inventario\Entradas\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Inventario;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\DB;

class EntradasController extends Controller
{
    

    public function index() {
       
        $entradas = Entrada::orderBy('id','desc')->paginate(7);

        return Response()->json($entradas, 200);

    }


    public function read($id) {

        $entrada = Entrada::where('id', $id)->with(['detalles'])->firstOrFail();
        return Response()->json($entrada, 200);

    }
    
    public function search($txt) {

        $entradas = Entrada::whereHas('cliente', function($query) use ($txt) {
                        $query->where('nombre', 'like' ,'%' . $txt . '%');
                    })->paginate(7);

        return Response()->json($entradas, 200);

    }

    public function filter(Request $request) {
        
        $entradas = Entrada::when($request->inicio, function($query) use ($request){
                                return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                            })
                            ->when($request->id_usuario, function($query) use ($request){
                                return $query->where('id_usuario', $request->id_usuario);
                            })
                            ->when($request->estado, function($query) use ($request){
                                return $query->where('estado', $request->estado);
                            })
                            ->when($request->id_bodega, function($query) use ($request){
                                return $query->where('id_bodega', $request->id_bodega);
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($entradas, 200);

    }
    
    
    public function store(Request $request)
    {
        $usuario = auth()->user();
        $request->validate([
            'fecha'         => 'required',
            'id_bodega'     =>  'required|numeric',
            'concepto'      => 'required|max:255',
            'detalles'      => 'required|array',
            'id_usuario'     => 'required|numeric',
        ]);

        DB::beginTransaction();
         
        try {

            if($request->id)
                $entrada = Entrada::findOrFail($request->id);
            else
                $entrada = new Entrada;

            $entrada->fill($request->all());      
            $entrada->save();

            // Detalles
            foreach ($request->detalles as $value) {
                if (!isset($value['id'])) {
                    $detalle = new Detalle;
                    $value['id_entrada'] = $entrada->id;
                    $detalle->fill($value);
                    $detalle->save();
                }
            }

            // Afectar Inventario
            // El inventario solo se actualiza cuando se aprueba explícitamente
            // if ($request->estado == 'Aprobada') {
            //     $entrada->actualizarInventario();
            // }

        DB::commit();
        return Response()->json($entrada, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

        return Response()->json($entrada, 200);

    }

    public function delete($id)
    {
        DB::beginTransaction();
        
        try {
            $entrada = Entrada::with('detalles')->findOrFail($id);
            
            // Solo revertir inventario si la entrada está aprobada
            if ($entrada->estado == 'Aprobada') {
                $entrada->revertirInventario();
            }
            
            $entrada->delete();
            
            DB::commit();
            return Response()->json($entrada, 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function aprobar($id)
    {
        DB::beginTransaction();
        
        try {
            $entrada = Entrada::with('detalles')->findOrFail($id);
            
            // Aprobar entrada
            $entrada->aprobar();
            
            DB::commit();
            return Response()->json($entrada, 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function anular($id)
    {
        DB::beginTransaction();
        
        try {
            $entrada = Entrada::with('detalles')->findOrFail($id);
            
            // Anular entrada
            $entrada->anular();
            
            DB::commit();
            return Response()->json($entrada, 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function generarDoc($id) {

        $entrada = Entrada::where('id', $id)->with('detalles')->firstOrFail();
        $empresa = Empresa::find($entrada->id_empresa);

        $reportes = \PDF::loadView('reportes.inventario.entrada', compact('entrada', 'empresa'));
        return $reportes->stream();

    }

    public function generarPartidaContable($id)
    {
        try {
            $entrada = Entrada::findOrFail($id);
            
            // Llamar al servicio de contabilidad para generar la partida
            $response = app('App\Services\Contabilidad\OtrasEntradasService')->crearPartida($entrada);
            
            return Response()->json([
                'success' => true,
                'message' => 'Partida contable generada exitosamente',
                'partida_id' => $response['partida_id']
            ], 200);
            
        } catch (\Exception $e) {
            return Response()->json([
                'success' => false,
                'message' => 'Error al generar la partida contable: ' . $e->getMessage()
            ], 400);
        }
    }


}
