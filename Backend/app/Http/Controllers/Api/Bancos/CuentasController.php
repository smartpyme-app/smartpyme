<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Cuenta;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Http\Requests\Bancos\StoreCuentaRequest;

class CuentasController extends Controller
{


    public function index(Request $request) {

        $cuentas = Cuenta::when($request->buscador, function($query) use ($request){
            return $query->where('nombre_banco', 'like' ,'%' . $request->buscador . '%')
            ->orWhere('tipo', 'like' ,'%' . $request->buscador . '%')
            ->orwhere('numero', 'like' ,'%' . $request->buscador . '%');
        })
        ->when($request->tipo, function($query) use ($request){
            return $query->where('tipo', $request->tipo);
        })
        ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
        ->paginate($request->paginate);

        return Response()->json($cuentas, 200);

    }

    public function list() {

        $cuentas = Cuenta::orderby('numero')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($cuentas, 200);

    }

    public function read($id) {

        $cuenta = Cuenta::where('id', $id)->firstOrFail();
        return Response()->json($cuenta, 200);

    }

    public function store(StoreCuentaRequest $request)
    {

        if($request->id)
            $cuenta = Cuenta::findOrFail($request->id);
        else
            $cuenta = new Cuenta;

        $cuenta->fill($request->only([
            'numero',
            'nombre_banco',
            'tipo',
            'correlativo_cheques',
            'saldo',
            'id_cuenta_contable',
            'id_empresa',
        ]));
        $cuenta->save();

        return Response()->json($cuenta, 200);

    }

    public function delete($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $cuenta->delete();

        return Response()->json($cuenta, 201);

    }

    public function libro($id, $del, $al){

        $cuenta = Cuenta::with(['transacciones' => function($q) use ($del, $al) {
                                $q->where('fecha', '>=', $del)
                                    ->where('fecha', '<=', $al)
                                    ->where('estado', 'Aprobada');
                            }])->where('id', $id)
                            // ->orderBy('fecha', 'desc')
                            ->orderBy('id', 'desc')
                            ->firstOrFail();

        $cuenta->del = $del;
        $cuenta->al = $al;


        $pdf = PDF::loadView('reportes.contabilidad.libro-de-bancos', compact('cuenta'));
        $pdf->setPaper('US Letter', 'portrait');

        return $pdf->stream($cuenta->nombre_banco . '-libro' . '.pdf');

    }

}
