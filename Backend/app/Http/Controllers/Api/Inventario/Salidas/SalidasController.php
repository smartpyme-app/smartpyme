<?php

namespace App\Http\Controllers\Api\Inventario\Salidas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Inventario\Salidas\Salida;
use App\Models\Inventario\Salidas\Detalle;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Inventario;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\DB;

class SalidasController extends Controller
{
    

    public function index() {
       
        $salidas = Salida::orderBy('id','desc')->paginate(7);

        return Response()->json($salidas, 200);

    }


    public function read($id) {

        $salida = Salida::where('id', $id)->with(['detalles'])->firstOrFail();
        return Response()->json($salida, 200);

    }
    
    public function search($txt) {

        $salidas = Salida::whereHas('cliente', function($query) use ($txt) {
                        $query->where('nombre', 'like' ,'%' . $txt . '%');
                    })->paginate(7);

        return Response()->json($salidas, 200);

    }

    public function filter(Request $request) {
        
        $salidas = Salida::when($request->inicio, function($query) use ($request){
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

        return Response()->json($salidas, 200);

    }


    public function store(Request $request)
    {
        $usuario = auth()->user();
        
        $request->validate([
            'fecha'         => 'required',
            'id_bodega'     => 'required|numeric',
            'concepto'      => 'required|max:255',
            'detalles'      => 'required|array',
            'id_usuario'     => 'required|numeric',
        ]);

        DB::beginTransaction();
         
        try {

            if($request->id)
                $salida = Salida::findOrFail($request->id);
            else
                $salida = new Salida;

            $salida->fill($request->all());            
            $salida->save();

            // Detalles
            foreach ($request->detalles as $value) {
                if (!isset($value['id'])) {
                    $detalle = new Detalle;
                    $value['id_salida'] = $salida->id;
                    $detalle->fill($value);
                    $detalle->save();
                }
            }

            // Afectar Inventario
            // El inventario solo se actualiza cuando se aprueba explícitamente
            // if ($request->estado == 'Aprobada') {
            //     $salida->actualizarInventario();
            // }

        DB::commit();
        return Response()->json($salida, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

        return Response()->json($salida, 200);

    }

    public function delete($id)
    {
        DB::beginTransaction();
        
        try {
            $salida = Salida::with('detalles')->findOrFail($id);
            
            // Solo revertir inventario si la salida está aprobada
            if ($salida->estado == 'Aprobada') {
                $salida->revertirInventario();
            }
            
            $salida->delete();
            
            DB::commit();
            return Response()->json($salida, 201);
            
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function aprobar($id)
    {
        DB::beginTransaction();
        
        try {
            $salida = Salida::with('detalles')->findOrFail($id);
            
            // Aprobar salida
            $salida->aprobar();
            
            DB::commit();
            return Response()->json($salida, 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function anular($id)
    {
        DB::beginTransaction();
        
        try {
            $salida = Salida::with('detalles')->findOrFail($id);
            
            // Anular salida
            $salida->anular();
            
            DB::commit();
            return Response()->json($salida, 200);
            
        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function generarDoc($id) {

        $salida = Salida::where('id', $id)->with('detalles')->firstOrFail();
        $empresa = Empresa::find(1);

        $reportes = \PDF::loadView('reportes.inventario.salida', compact('salida', 'empresa'));
        return $reportes->stream();

    }


}
