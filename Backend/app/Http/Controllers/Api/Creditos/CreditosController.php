<?php

namespace App\Http\Controllers\Api\Creditos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Creditos\Credito;
use App\Models\Creditos\Pago;
use App\Models\Registros\Cliente;
use App\Models\Creditos\Detalle;
use JWTAuth;

class CreditosController extends Controller
{
    
    public function index() {
       
        $creditos = Credito::orderBy('fecha','desc')->orderBy('id','desc')->with('cliente')->paginate(10);

        return Response()->json($creditos, 200);

    }

    public function read($id) {

        $credito = Credito::where('id', $id)->with('pagos', 'venta')->firstOrFail();
        return Response()->json($credito, 200);

    }

    public function search($txt) {

        $creditos = Credito::whereHas('venta', function($q) use ($txt){
                                $q->whereHas('cliente', function($q) use ($txt) {
                                    $q->where('nombre', 'like', '%'. $txt .'%');
                                });
                            })->paginate(10);

        return Response()->json($creditos, 200);

    }

    public function filter(Request $request) {

        $creditos = Credito::when($request->fecha_fin, function($query) use ($request){
                                    return $query->whereBetween('fecha', [$request->fecha_ini, $request->fecha_fin]);
                                })
                                ->when($request->categoria, function($query) use ($request){
                                    return $query->where('categoria', $request->categoria);
                                })
                                ->when($request->sucursal_id, function($query) use ($request){
                                    return $query->where('sucursal_id', $request->sucursal_id);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->orderBy('id','desc')->paginate(100000);

            return Response()->json($creditos, 200);
    }

    public function store(Request $request)
    {

        $request->validate([
            'fecha'         => 'required',
            'venta_id'      => 'required|unique:creditos,venta_id,'. $request->id,
            'cliente_id'    => 'required|numeric',
            'total'         => 'required|numeric',
            'interes_anual' => 'required|numeric',
            'tipo_cuota' => 'required|max:255',
            'numero_de_cuotas' => 'required|numeric',
            'forma_de_pago' => 'required|max:255',
            'periodo_de_gracia' => 'required|numeric',
            'prima'         => 'required|numeric',
            'nota'          => 'sometimes|max:255',
            'usuario_id'    => 'required|numeric',
            'empresa_id'    => 'required|numeric',
        ], [
            'venta_id.unique' => 'Ya existe un crédito para esta venta',
        ]);

        if($request->id)
            $credito = Credito::findOrFail($request->id);
        else
            $credito = new Credito;

        $credito->fill($request->all());
        $credito->save();

        return Response()->json($credito, 200);

    }

    public function facturacion(Request $request){

        $request->validate([
            'fecha'         => 'required',
            'estado'        => 'required|max:255',
            'categoria'     => 'required|max:255',
            'lugar'         => 'required|max:255',
            'cliente'       => 'required',
            'detalles'      => 'required',
            'pagado'        => 'required|numeric',
            'total'         => 'required|numeric',
            'usuario_id'    => 'required|numeric',
            'sucursal_id'   => 'required|numeric',
        ]);

        // Guardamos el cliente

            if(isset($request->cliente['id'])){
                $cliente = Cliente::findOrFail($request->cliente['id']);
            }
            else{
                $cliente = new Cliente;
                $cliente->empresa_id = JWTAuth::parseToken()->authenticate()->empresa_id;
            }

            $cliente->fill($request->cliente);
            $cliente->save();

        // Guardamos la credito
            if($request->id)
                $credito = Credito::findOrFail($request->id);
            else
                $credito = new Credito;

            $det['credito_id'] = $credito->id;
            $request['cliente_id'] = $cliente->id;
            $credito->fill($request->all());
            $credito->save();


        // Guardamos los detalles

            foreach ($request->detalles as $det) {
                if(isset($det['id']))
                    $detalle = Detalle::findOrFail($det['id']);
                else
                    $detalle = new Detalle;
                $det['credito_id'] = $credito->id;
                

                $detalle->fill($det);
                $detalle->save();
            }

        
        return Response()->json($credito, 200);

    }

    public function delete($id)
    {
        $credito = Credito::findOrFail($id);
        foreach ($credito->detalles as $detalle) {
            $detalle->delete();
        }
        $credito->delete();

        return Response()->json($credito, 201);

    }


    function planDePagos($monto, $numero_de_cuotas, $forma_de_pago, $interes_anual = 0, $prima = 0, $periodo_de_gracia = 0, $id = null){
        
        if ($id){
            $credito = Credito::findOrFail($id);
        }
        else{

            $credito = new Credito;
            $usuario = JWTAuth::parseToken()->authenticate();

            $credito->fecha = date('Y-m-d');
            $credito->nombre_cliente = null;
            $credito->nombre_usuario = $usuario->name;
            $credito->total = $monto;
            $credito->prima = $prima;
            $credito->forma_de_pago = $forma_de_pago;
            $credito->numero_de_cuotas = $numero_de_cuotas;
            $credito->interes_anual = $interes_anual;
            $credito->periodo_de_gracia = $periodo_de_gracia;
            $credito->nota = null;
        }


        $interes_mensual = ($credito->interes_anual / 12 ) / 100;
        $interes = ($credito->total - $credito->prima) * $interes_mensual;

        $cuota = $credito->cuota;
        
        $abono = $cuota - $interes;
        $saldo = $credito->total - $abono - $credito->prima;

        $pagos = collect();

        for ($i=1; $i <= $numero_de_cuotas; $i++) {
            if ($credito->forma_de_pago == 'Dias') {
                $fecha = \Carbon\Carbon::parse($credito->fecha)->addDays($i + $credito->periodo_de_gracia)->format('d/m/Y');
            }
            if ($credito->forma_de_pago == 'Semanas') {
                $fecha = \Carbon\Carbon::parse($credito->fecha)->addWeeks($i + $credito->periodo_de_gracia)->format('d/m/Y');
            }
            if ($credito->forma_de_pago == 'Meses') {
                $fecha = \Carbon\Carbon::parse($credito->fecha)->addMonths($i + $credito->periodo_de_gracia)->format('d/m/Y');
            }
            $pagos->push([
                'fecha'         => $fecha,
                'saldo_inicial' => $saldo + $abono,
                'cuota'         => $cuota,
                'interes'       => $interes,
                'abono'         => $abono,
                'saldo_final'   => $saldo,
            ]);
            
            $interes = $saldo * $interes_mensual;
            $abono = $cuota - $interes;
            $saldo = $saldo - $abono;
        }

        $credito->pagos = $pagos;

        $reportes = \PDF::loadView('reportes.creditos.tabla_amortizacion', compact('credito'))->setPaper('letter', 'landscape');
        return $reportes->stream();

    }

    function imprimirPagos($id){
        
        $credito = Credito::where('id', $id)->with('pagos')->firstOrFail();

        $credito->nombre_empresa = $credito->empresa()->first()->nombre;

        $credito['pagos'] = $credito->pagos;

        $reportes = \PDF::loadView('reportes.creditos.tabla_amortizacion', compact('credito'))->setPaper('letter', 'landscape');
        return $reportes->stream();

    }

    function imprimirPago($id){
        
        $pago = Pago::where('id', $id)->with('credito')->firstOrFail();

        $reportes = \PDF::loadView('reportes.creditos.pago', compact('pago'))->setPaper('letter', 'landscape');
        return $reportes->stream();

    }

}
