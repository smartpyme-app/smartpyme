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
            'categoria_id'          => 'required|numeric',
            'sucursal_id'           => 'required|numeric',
            'cuenta_contable_id'    => 'required|numeric',
        ]);

        if($request->id)
            $cuenta = Cuenta::findOrFail($request->id);
        else
            $cuenta = new Cuenta;

        $cuenta->fill($request->all());
        $cuenta->save();

        return Response()->json($cuenta, 200);

    }

    public function delete($id)
    {
        $cuenta = SubCategoria::findOrFail($id);
        $cuenta->delete();

        return Response()->json($cuenta, 201);

    }


}
