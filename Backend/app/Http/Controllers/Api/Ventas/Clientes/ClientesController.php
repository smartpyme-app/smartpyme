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
    

    public function index(Request $request) {
       
        $clientes = Cliente::where('id','!=', 1)->withSum('ventas', 'total')
                    ->when($request->buscador, function($query) use ($request){
                        return $query->where('nombre', 'like' ,'%' . $request->buscador . '%')
                                    ->orwhere('nombre_empresa', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('nit', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('giro', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('telefono', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('ncr', 'like',  '%'. $request->buscador .'%')
                                    ->orwhere('dui', 'like',  '%'. $request->buscador .'%');
                    })
                    ->when($request->estado !== null, function($q) use ($request){
                        $q->where('enable', !!$request->estado);
                    })
                    ->orderBy($request->orden, $request->direccion)
                    ->paginate($request->paginate);

        return Response()->json($clientes, 200);

    }

    public function list() {

        $clientes = Cliente::orderBy('nombre','asc')
                            ->where('enable', true)
                            ->get();
        
        return Response()->json($clientes, 200);

    }

    public function read($id) {

        $cliente = Cliente::findOrFail($id);

        return Response()->json($cliente, 200);

    }

    public function store(Request $request)
    {

        $request->validate([
            'nombre'         => 'required|string|between:2,255',
            'registro'       => 'nullable|unique:clientes,registro,'. $request->id,
            'dui'            => 'nullable|unique:clientes,dui,'. $request->id,
            'nit'            => 'nullable|unique:clientes,nit,'. $request->id,
            'id_usuario'     => 'required|numeric',
            'id_empresa'     => 'required|numeric|exists:empresas,id',
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

        $ventas = Venta::where('id_cliente', $id)
                        ->where('estado', '!=', 'Anulada')
                        ->orderBy('id', 'desc')
                        ->paginate(10);
        return Response()->json($ventas, 200);

    }

    public function creditos($id) {

        $creditos = Credito::where('id_cliente', $id)
                        ->orderBy('id', 'desc')
                        ->paginate(10);
        return Response()->json($creditos, 200);

    }

    public function ventasFilter(Request $request) {

        if ($request->estado == 'Anulada') {
            $ventas = Venta::where('id_cliente', $request->id)
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('estado', $request->estado);
                        })
                        ->when($request->metodo_pago, function($query) use ($request){
                            return $query->where('metodo_pago', $request->metodo_pago);
                        })
                        ->orderBy('id','desc')->paginate(100000);
        }else{

            $ventas = Venta::where('id_cliente', $request->id)
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
                        ->whereRaw('clientes.id in (select id_cliente from ventas where estado = ?)', ['Pendiente'])
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
                        ->whereRaw('clientes.id in (select id_cliente from ventas where estado = ?)', ['Pendiente'])
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

        $datos->ventas   = \App\Models\Ventas\Venta::selectRaw('count(id) AS total, id_cliente, (select nombre from clientes where id_cliente = id) as nombre')
                                    ->groupBy('id_cliente')
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('id_sucursal', $request->id_sucursal);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('id_sucursal', $request->id_sucursal);
                                    // })
                                    ->orderBy('total', 'desc')
                                    ->take(5)
                                    ->get();

        $datos->municipios   = Cliente::selectRaw('count(id) AS total, municipio')
                                    ->groupBy('municipio')
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('id_sucursal', $request->id_sucursal);
                                    // })
                                    // ->when('sucursal', function($q) use($request){
                                    //     $q->where('id_sucursal', $request->id_sucursal);
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
