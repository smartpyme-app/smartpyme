<?php

namespace App\Http\Controllers\Api\Contabilidad\Partidas;

use App\Http\Controllers\Controller;
use App\Models\Contabilidad\Catalogo\Cuenta;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Partidas\Detalle;

class DetallesController extends Controller
{

    public function read($id) {

        $detalle = Detalle::where('id', $id)->firstOrFail();
        return Response()->json($detalle, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'id_cuenta'     => 'required|numeric',
            'codigo'     => 'required|numeric',
            'nombre_cuenta'     => 'required|numeric',
            'id_partida'    => 'required|numeric',
            'concepto'      => 'required|max:255',
            'cargo'         => 'required|numeric',
            'abono'         => 'required|numeric',
            'saldo'         => 'required|numeric',
        ]);

        if($request->id) {
            $detalle = Detalle::findOrFail($request->id);
            $cuenta = Cuenta::findOrFail($request->id_cuenta);
        }else {
            $detalle = new Detalle;
            $cuenta = Cuenta::findOrFail($request->id_cuenta);
        }

        $detalle->fill($request->all());
        $detalle['codigo'] = $cuenta->codigo;
        $detalle['nombre_cuenta'] = $cuenta->nombre;
        $detalle->save();

        return Response()->json($detalle, 200);

    }

    public function delete($id)
    {
        $detalle = Detalle::findOrFail($id);
        $detalle->delete();

        return Response()->json($detalle, 201);

    }

}
