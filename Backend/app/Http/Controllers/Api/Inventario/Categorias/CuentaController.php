<?php

namespace App\Http\Controllers\Api\Inventario\Categorias;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\Cuenta;

class CuentaController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'id_categoria'          => 'required|numeric',
            'id_sucursal'           => 'required|numeric',
        ]);

        if($request->id){
            $cuenta = Cuenta::findOrFail($request->id);
        }
        else{
            $cuenta = new Cuenta;
            $existe = Cuenta::where('id_categoria', $request->id_categoria)->where('id_sucursal', $request->id_sucursal)->first();

            if($existe)
                return  Response()->json(['error' => 'Ya ha sido configurada una cuenta en esta sucursal', 'code' => 400], 400);
        }

        $cuenta->fill($request->all());
        $cuenta->save();

        return Response()->json($cuenta, 200);

    }

    public function delete($id)
    {
        $cuenta = Cuenta::findOrFail($id);
        $cuenta->delete();

        return Response()->json($cuenta, 201);

    }


}
