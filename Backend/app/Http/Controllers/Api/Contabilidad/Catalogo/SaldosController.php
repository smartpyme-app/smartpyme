<?php

namespace App\Http\Controllers\Api\Contabilidad\Catalogo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Catalogo\Saldo;

class SaldosController extends Controller
{
    
    public function read($id) {

        $saldo = Saldo::where('id', $id)->firstOrFail();
        return Response()->json($saldo, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'anio'          => 'required|numeric',
            'mes'           => 'required|numeric',
            'saldo_inicial' => 'required|numeric',
            'abonos'        => 'required|numeric',
            'cargos'        => 'required|numeric',
            'saldo_final'   => 'required|numeric',
            'id_cuenta'     => 'required|numeric',
        ]);

        if($request->id)
            $saldo = Saldo::findOrFail($request->id);
        else
            $saldo = new Saldo;
        
        $saldo->fill($request->all());
        $saldo->save();

        return Response()->json($saldo, 200);

    }

    public function delete($id)
    {
        $saldo = Saldo::findOrFail($id);
        $saldo->delete();

        return Response()->json($saldo, 201);

    }

}
