<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Cheque;
use App\Models\Bancos\Transaccion;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Bancos\ChequesExport;
use Barryvdh\DomPDF\Facade as PDF;
use Auth;

class ChequesController extends Controller
{
    

    public function index(Request $request) {
       
        $cheques = Cheque::when($request->buscador, function($query) use ($request){
                                    return $query->where('anombrede', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->id_cuenta, function($query) use ($request){
                                    return $query->where('id_cuenta', $request->id_cuenta);
                                })
                                ->orderBy($request->orden, $request->direccion)
                                ->orderBy('id', 'desc')
                                ->paginate($request->paginate);

        return Response()->json($cheques, 200);

    }

    public function list() {
       
        $cheques = Cheque::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($cheques, 200);

    }
    
    public function read($id) {

        $cheque = Cheque::where('id', $id)->firstOrFail();
        return Response()->json($cheque, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'id_cuenta'     => 'required|numeric',
            'correlativo'   => 'required|numeric',
            'anombrede'     => 'required|max:255',
            'concepto'      => 'required|max:255',
            'total'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $cheque = Cheque::findOrFail($request->id);
        else
            $cheque = new Cheque;
        
        
        DB::beginTransaction();

        try {

            // Aprobar cheque
                if(($cheque->estado == 'Pendiente') && ($request['estado'] == 'Aprobado')){

                    $transaccion = new Transaccion;
                    $transaccion->estado = 'Pendiente';
                    $transaccion->tipo = 'Abono';
                    $transaccion->tipo_operacion = 'Cheque';
                    $transaccion->concepto = 'Cheque: ' . $cheque->nombre_cuenta . ' #' . $cheque->correlativo;
                    $transaccion->id_cuenta = $cheque->id_cuenta;
                    $transaccion->referencia = 'Cheque';
                    $transaccion->id_referencia = $cheque->id;
                    $transaccion->total = $cheque->total;
                    $transaccion->fecha = date('Y-m-d');
                    $transaccion->id_empresa = $cheque->id_empresa;
                    $transaccion->id_usuario = Auth::user()->id;
                    $transaccion->save();

                }

            $cheque->fill($request->all());
            $cheque->save();

            // Incrementar correlativo
                if(!$request->id){
                    $cheque->cuenta->increment('correlativo_cheques');
                }


        DB::commit();
        return Response()->json($cheque, 200);

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
        $cheque = Cheque::findOrFail($id);
        $cheque->delete();

        return Response()->json($cheque, 201);

    }

    public function export(Request $request){
        $cheques = new ChequesExport();
        $cheques->filter($request);

        return Excel::download($cheques, 'cheques.xlsx');
    }

    public function generarDoc($id){
        $cheque = Cheque::where('id', $id)->firstOrFail();
        
        if(Auth::user()->id_empresa == 415){ //415 
            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Cheque-Fumigadora-Vector', compact('cheque'));
        }else{
            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Cheque-Fumigadora-Vector', compact('cheque'));
        }
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream('cheque-' . $cheque->correlativo . '.pdf');

    }


}
