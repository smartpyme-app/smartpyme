<?php

namespace App\Http\Controllers\Api\Ventas\Clientes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Clientes\Anticipo;
use App\Models\Ventas\Venta;
use App\Models\Creditos\Credito;

use App\Imports\Clientes;
use Maatwebsite\Excel\Facades\Excel;

class ClientesController extends Controller
{
    

    public function index() {
       
        $clientes = Cliente::orderBy('id','desc')
                    ->paginate(10);

        return Response()->json($clientes, 200);

    }

    public function list() {

        $clientes = Cliente::orderBy('nombre','asc')->get();
        
        return Response()->json($clientes, 200);

    }

    public function search($txt) {

        $clientes = Cliente::where('nombre', 'like' ,'%' . $txt . '%')
                            ->orWhere('empresa', 'like' , $txt . '%')
                            ->orWhere('dui', 'like' , $txt . '%')
                            ->orWhere('registro', 'like' , $txt . '%')
                            ->orWhere('etiquetas', 'like' ,'%' . $txt . '%')
                            ->orWhereRaw('REPLACE(registro, "-", "") like "'.$txt.'"')
                            ->orderBy('registro','asc')->paginate(10);
        return Response()->json($clientes, 200);

    }

    public function filter(Request $request) {

        $clientes = Cliente::when($request->nombre, function($query) use ($request){
                                return $query->where('nombre', 'like',  '%'.$request->nombre .'%')
                                            ->orwhere('nit', 'like',  '%'. $request->nombre .'%')
                                            ->orwhere('giro', 'like',  '%'. $request->nombre .'%')
                                            ->orwhere('telefono', 'like',  '%'. $request->nombre .'%')
                                            ->orwhere('ncr', 'like',  '%'. $request->nombre .'%')
                                            ->orwhere('dui', 'like',  '%'. $request->nombre .'%');
                            })
                            ->when($request->estado, function($query) use ($request){
                                return $query->where('enable', $request->estado);
                            })
                            ->orderBy('id','desc')->paginate(100000);

        return Response()->json($clientes, 200);
    }

    public function read($id) {

        $cliente = Cliente::findOrFail($id);
        $cliente->num_ventas = $cliente->ventas()->count();
        $cliente->num_creditos = $cliente->creditos()->count();

        return Response()->json($cliente, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'nombre'         => 'required|string|between:2,255',
            'registro'       => 'nullable|unique:clientes,registro,'. $request->id,
            'dui'            => 'nullable|unique:clientes,dui,'. $request->id,
            'nit'            => 'nullable|unique:clientes,nit,'. $request->id,
            'usuario_id'     => 'required|integer|exists:users,id',
            'empresa_id'     => 'required|integer|exists:empresas,id',
        ]);

        if($request->id)
            $cliente = Cliente::findOrFail($request->id);
        else
            $cliente = new Cliente;
        
        $cliente->fill($request->all());
        $cliente->save();

        return Response()->json($cliente, 200);

    }

    public function delete($id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->delete();

        return Response()->json($cliente, 201);

    }

    public function ventas($id) {

        $ventas = Venta::where('cliente_id', $id)
                        ->where('estado', '!=', 'Anulada')
                        ->orderBy('id', 'desc')
                        ->paginate(10);
        return Response()->json($ventas, 200);

    }

    public function creditos($id) {

        $creditos = Credito::where('cliente_id', $id)
                        ->orderBy('id', 'desc')
                        ->paginate(10);
        return Response()->json($creditos, 200);

    }

    public function ventasFilter(Request $request) {

        if ($request->estado == 'Anulada') {
            $ventas = Venta::where('cliente_id', $request->id)
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->orderBy('id','desc')->paginate(100000);
        }else{

            $ventas = Venta::where('cliente_id', $request->id)
                        ->where('estado', '!=', 'Anulada')
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->orderBy('id','desc')->paginate(100000);
        }

        return Response()->json($ventas, 200);

    }

    public function cxc() {
       
        $clientes = Cliente::where('id','!=', 1)
                        ->whereRaw('clientes.id in (select cliente_id from ventas where estado = ?)', ['Pendiente'])
                        ->paginate(10);

        foreach ($clientes as $cliente) {
            $cliente->num_ventas_pendientes = $cliente->ventasPendientes->count();
            $cliente->pago_pendiente = $cliente->ventasPendientes->sum('total');
        }

        return Response()->json($clientes, 200);

    }

    public function cxcBuscar($txt) {
       
        $clientes = Cliente::where('id','!=', 1)->where('nombre', 'like' ,'%' . $txt . '%')
                        ->orWhere('registro', 'like' , $txt . '%')
                        ->orWhereRaw('REPLACE(registro, "-", "") like "'.$txt.'"')
                        ->whereRaw('clientes.id in (select cliente_id from ventas where estado = ?)', ['Pendiente'])
                        ->paginate(10);

        return Response()->json($clientes, 200);

    }

    public function estadoCuenta($id) {
       
        $cliente = Cliente::where('id', $id)->with('empresa')->firstOrFail();
        $cliente->ventas = $cliente->ventas()->where('estado', 'Pendiente')->get();
        $cliente->fletes = $cliente->fletes()->where('estado', 'Pendiente')->get();
        // return $cliente;
        $reportes = \PDF::loadView('reportes.clientes.estado-cuenta', compact('cliente'))->setPaper('letter', 'landscape');
        return $reportes->stream();

    }

    public function dash(Request $request) {

        $datos = new \stdClass();

        $datos->ventas   = \App\Models\Ventas\Venta::selectRaw('count(id) AS total, cliente_id, (select nombre from clientes where cliente_id = id) as nombre')
                                    ->groupBy('cliente_id')
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    ->orderBy('total', 'desc')
                                    ->take(5)
                                    ->get();

        $datos->municipios   = Cliente::selectRaw('count(id) AS total, municipio')
                                    ->groupBy('municipio')
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('sucursal_id', $request->sucursal_id);
                                    // })
                                    ->orderBy('total', 'desc')
                                    ->take(5)
                                    ->get();


        return Response()->json($datos, 200);
    }

    public function import(Request $request){
        
        $request->validate([
            'file'          => 'required',
        ]);

        $import = new Clientes();
        Excel::import($import, $request->file);
        
        return Response()->json($import->getRowCount(), 200);

    }

    public function export(Request $request){

      $clientes = new ClientesExport();
      $clientes->filter($request);

      return Excel::download($clientes, 'clientes.xlsx');
    }


    public function datos(Request $request) {
       
        $cliente = Cliente::where('id', $request->id)->firstOrFail();

        $ventas = $cliente->ventas()->whereBetween('fecha', [$request->inicio, $request->fin])->get();
        $fletes = $cliente->fletes()->whereBetween('fecha', [$request->inicio, $request->fin])->get();

        $cliente->total_ventas_pagadas = $ventas->where('estado', 'Pagada')->sum('total');
        $cliente->total_ventas_pendientes = $ventas->where('estado', 'Pendiente')->sum('total');

        $cliente->total_fletes_pagados = $fletes->where('estado', 'Pagado')->sum('total');
        $cliente->total_fletes_pendientes = $fletes->where('estado', 'Pendiente')->sum('total');

        $cliente->total_balance = $cliente->total_ventas_pagadas - $cliente->total_ventas_pendientes - $cliente->total_fletes_pendientes;


        return Response()->json($cliente, 200);

    }


}
