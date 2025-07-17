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
use Luecano\NumeroALetras\NumeroALetras;
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

        // Validar que la cuenta existe y pertenece a la empresa
        $cuentaExists = \App\Models\Bancos\Cuenta::where('id', $request->id_cuenta)
                                                 ->where('id_empresa', $request->id_empresa)
                                                 ->exists();

        if (!$cuentaExists) {
            return Response()->json(['error' => 'La cuenta bancaria seleccionada no existe o no pertenece a su empresa'], 400);
        }

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
                    // Verificar que la cuenta existe y pertenece a la empresa antes de incrementar
                    $cuenta = \App\Models\Bancos\Cuenta::where('id', $cheque->id_cuenta)
                                                       ->where('id_empresa', $cheque->id_empresa)
                                                       ->first();

                    if ($cuenta) {
                        $cuenta->increment('correlativo_cheques');
                    } else {
                        throw new \Exception('La cuenta bancaria seleccionada no existe o no pertenece a su empresa');
                    }
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

        // Convertir el monto a palabras
        $formatter = new NumeroALetras();
        $n = explode(".", number_format($cheque->total, 2));

        $dolares = $formatter->toWords(floatval(str_replace(',', '', $n[0])));
        $centavos = $formatter->toWords($n[1]);

        if(Auth::user()->id_empresa == 415){ //415
            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Cheque-Fumigadora-Vector', compact('cheque', 'dolares', 'centavos'));
        }else{
            $pdf = PDF::loadView('reportes.facturacion.formatos_empresas.Cheque-Fumigadora-Vector', compact('cheque', 'dolares', 'centavos'));
        }
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream('cheque-' . $cheque->correlativo . '.pdf');

    }


}
