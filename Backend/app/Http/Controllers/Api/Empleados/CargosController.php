<?php

namespace App\Http\Controllers\Api\Empleados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Empleados\Cargo;
use JWTAuth;

class CargosController extends Controller
{
    

    public function index() {
        $cargos = Cargo::get();
        return Response()->json($cargos, 200);

    }
    
    public function read($id) {

        $cargo = Cargo::where('id', $id)->firstOrFail();
        return Response()->json($cargo, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'        => 'required|max:255',
            'empresa_id'    => 'required|numeric',
        ]);

        if($request->id)
            $cargo = Cargo::findOrFail($request->id);
        else
            $cargo = new Cargo;
        
        $cargo->fill($request->all());
        $cargo->save();

        return Response()->json($cargo, 200);

    }

    public function delete($id)
    {
        $cargo = Cargo::findOrFail($id);
        $cargo->delete();

        return Response()->json($cargo, 201);

    }

}
