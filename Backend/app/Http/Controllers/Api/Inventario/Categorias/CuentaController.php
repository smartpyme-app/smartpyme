<?php

namespace App\Http\Controllers\Api\Inventario\Categorias;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Inventario\Categorias\Cuenta;
use App\Http\Requests\Inventario\Categorias\Cuenta\StoreCuentaRequest;

class CuentaController extends Controller
{

    public function store(StoreCuentaRequest $request)
    {
        if($request->id){
            $cuenta = Cuenta::findOrFail($request->id);
        }
        else{
            $cuenta = new Cuenta;
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
