<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Presupuesto;
use Illuminate\Support\Facades\Crypt;
use JWTAuth;

class PresupuestosController extends Controller
{
    

    public function index() {
       
        $presupuestos = Presupuesto::orderBy('id', 'desc')->paginate(10);

        return Response()->json($presupuestos, 200);

    }


    public function read($id) {
        
        $presupuesto = Presupuesto::findOrFail($id);
        return Response()->json($presupuesto, 200);

    }

    public function filter(Request $request) {


        $presupuestos = Presupuesto::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha_inicio', [$request->inicio, $request->fin]);
                        })
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->when($request->estado, function($query) use ($request){
                            return $query->where('enable', $request->estado);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($presupuestos, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha_compra'  => 'required|date',
            'nombre'        => 'required|max:255',
            'descripcion'   => 'sometimes|max:255',
            'ubicacion'     => 'sometimes|max:255',
            'valor_compra' => 'required|numeric',
            'responsable_id'   => 'sometimes|numeric',
            'usuario_id'   => 'required|numeric',
            'sucursal_id'   => 'required|numeric',
            'empresa_id'   => 'required|numeric',
        ]);

        if($request->id)
            $presupuesto = Presupuesto::findOrFail($request->id);
        else
            $presupuesto = new Presupuesto;
        
        $presupuesto->fill($request->all());
        $presupuesto->save();

        return Response()->json($presupuesto, 200);

    }

    public function delete($id)
    {
       
        $presupuesto = Presupuesto::findOrFail($id);
        $presupuesto->delete();

        return Response()->json($presupuesto, 201);

    }


}
