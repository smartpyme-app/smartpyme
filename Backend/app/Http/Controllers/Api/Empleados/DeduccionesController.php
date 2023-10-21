<?php

namespace App\Http\Controllers\Api\Empleados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Empleados\Deduccion;
use Carbon\Carbon;

class DeduccionesController extends Controller
{

    public function index() {
       
        $deducciones = Deduccion::orderBy('id','desc')->get();

        return Response()->json($deducciones, 200);

    }

    public function read($id) {
        
        $deduccion = Deduccion::where('id', $id)->firstOrFail();
        return Response()->json($deduccion, 200);
    }
    

    public function store(Request $request) {
        
        $request->validate([
            'nombre'        => 'required|max:255',
            'tipo'          => 'required',
            'descripcion'   => 'sometimes|max:255',
            'total'         => 'required|numeric',
            'empresa_id'    => 'required|numeric',
        ]);

        if($request->id)
            $deduccion = Deduccion::findOrFail($request->id);
        else
            $deduccion = new Deduccion;
        
        $deduccion->fill($request->all());
        $deduccion->save();

        return Response()->json($deduccion, 200);


    }

    public function delete($id)
    {
       
        $deduccion = Deduccion::findOrFail($id);
        $deduccion->delete();

        return Response()->json($deduccion, 201);

    }



}
