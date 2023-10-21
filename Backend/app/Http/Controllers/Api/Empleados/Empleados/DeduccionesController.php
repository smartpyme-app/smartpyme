<?php

namespace App\Http\Controllers\Api\Empleados\Empleados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Empleados\Empleados\Deduccion;
use Carbon\Carbon;

class DeduccionesController extends Controller
{
    
    public function index($id) {
        
        $deducciones = Deduccion::where('empleado_id', $id)->firstOrFail();
        return Response()->json($deducciones, 200);
    }
    

    public function store(Request $request) {
        
        $request->validate([
            'deduccion_id'  => 'required|max:255',
            'tipo'          => 'required',
            'total'         => 'required|numeric',
            'empleado_id'   => 'required|numeric',
        ]);

        if($request->id){
            $deduccion = Deduccion::findOrFail($request->id);
        }
        else{
            $deduccion = new Deduccion;
            $existe = Deduccion::where('empleado_id', $request->empleado_id)->where('deduccion_id', $request->deduccion_id)->first();

            if($existe)
                return  Response()->json(['error' => 'Ya ha sido configurada la deducción', 'code' => 400], 400);
        }
        
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
