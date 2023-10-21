<?php

namespace App\Http\Controllers\Api\Empleados\Planillas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Empleados\Planillas\Detalle;
use Carbon\Carbon;

class DetallesController extends Controller
{
    

    public function store(Request $request) {
        
        $request->validate([
            'empleado_id'   => 'required|numeric',
            'dias'          => 'required|numeric',
            'horas'         => 'required|numeric',
            'horas_extras'  => 'required|numeric',
            'sueldo'        => 'required|numeric',
            'extras'        => 'sometimes|numeric',
            'otros'         => 'sometimes|numeric',
            'renta'         => 'sometimes|numeric',
            'isss'          => 'sometimes|numeric',
            'afp'           => 'sometimes|numeric',
            'total'         => 'required|numeric',
            'planilla_id'   => 'required|numeric',
        ]);

        if($request->id)
            $detalle = Detalle::findOrFail($request->id);
        else
            $detalle = new Detalle;
        
        $detalle->fill($request->all());
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
