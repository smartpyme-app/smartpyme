<?php

namespace App\Http\Controllers\Api\Empleados\Empleados;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Empleados\Empleados\Comision;
use Carbon\Carbon;

class ComisionesController extends Controller
{
    
    public function index() {
       
        $comisiones = Comision::orderBy('fecha', 'desc')->paginate(12);
        return Response()->json($comisiones, 200);

    }

    public function filter(Request $request) {
        
        $comisiones = Comision::when($request->tipo, function($query) use ($request){
                                    return $query->where('tipo', $request->tipo);
                                })
                                ->when($request->estado, function($query) use ($request){
                                    return $query->where('estado', $request->estado);
                                })
                                ->when($request->usuario_id, function($query) use ($request){
                                    return $query->where('empleado_id', $request->empleado_id);
                                })
                                ->orderBy('id','desc')->paginate(100000);

        return Response()->json($comisiones, 200);
    }

    public function store(Request $request) {
        
        $request->validate([
            'fecha'         => 'required',
            'concepto'      => 'required|max:250',
            'nota'          => 'sometimes|max:250',
            'tipo'          => 'required|max:250',
            'total'         => 'required|numeric',
            'empleado_id'   => 'required|numeric',
            'usuario_id'    => 'required|numeric',

        ]);

        if($request->id)
            $comision = Comision::findOrFail($request->id);
        else
            $comision = new Comision;
        
        $comision->fill($request->all());
        $comision->save();

        return Response()->json($comision, 200);


    }

    public function delete($id)
    {
       
        $comision = Comision::findOrFail($id);
        $comision->delete();

        return Response()->json($comision, 201);

    }



}
