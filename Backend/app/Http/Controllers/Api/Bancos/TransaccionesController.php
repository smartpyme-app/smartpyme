<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Transaccion;
use Illuminate\Support\Facades\DB;

class TransaccionesController extends Controller
{
    

    public function index(Request $request) {
       
        $transacciones = Transaccion::with('cuenta')->when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->orderBy('id', 'desc')
                                ->paginate($request->paginate);

        return Response()->json($transacciones, 200);

    }

    public function list() {
       
        $transacciones = Transaccion::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($transacciones, 200);

    }
    
    public function read($id) {

        $transaccion = Transaccion::where('id', $id)->firstOrFail();
        return Response()->json($transaccion, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'id_cuenta'     => 'required|numeric',
            'concepto'      => 'required|max:255',
            'tipo'          => 'required|max:255',
            'estado'          => 'required|max:255',
            'total'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {

            if($request->id)
                $transaccion = Transaccion::findOrFail($request->id);
            else
                $transaccion = new Transaccion;

            // Aprobar transaccion
                if(($transaccion->estado == 'Pendiente') && ($request['estado'] == 'Aprobada')){

                    // $partida = new Partida;
                    // $partida->fecha = date('Y-m-d');
                    // $partida->tipo = 'Diario';
                    // $partida->concepto = $transaccion->concepto;
                    // $partida->estado = 'Pendiente';
                    // $partida->id_empresa = $cheque->id_empresa;
                    // $partida->id_usuario = Auth::user()->id;
                    // $partida->save();

                        // $detalle->id_cuenta 
                        // $detalle->codigo    
                        // $detalle->nombre_cuenta 
                        // $detalle->concepto  
                        // $detalle->debe  
                        // $detalle->haber 
                        // $detalle->saldo 
                        // $detalle->id_partida = $partida->id; 
                        // $detalle->save(); 

                    //Actualizar saldo de cuanta
                        $cuenta = $transaccion->cuenta()->first();

                        if ($transaccion->tipo == 'Cargo') {
                            $cuenta->saldo = $cuenta->saldo - $transaccion->total;
                        }

                        if ($transaccion->tipo == 'Abono') {
                            $cuenta->saldo = $cuenta->saldo + $transaccion->total;
                        }

                        $cuenta->save();
                }

            $transaccion->fill($request->all());
            $transaccion->save();

        DB::commit();
        return Response()->json($transaccion, 200);

        } catch (\Exception $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            DB::rollback();
            return Response()->json(['error' => $e->getMessage()], 400);
        }

    }

    public function delete($id)
    {
        $transaccion = Transaccion::findOrFail($id);
        $transaccion->delete();

        return Response()->json($transaccion, 201);

    }

}
