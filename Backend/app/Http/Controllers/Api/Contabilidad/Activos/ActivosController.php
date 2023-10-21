<?php

namespace App\Http\Controllers\Api\Contabilidad\Activos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Activos\Activo;
use Illuminate\Support\Facades\Crypt;
use JWTAuth;

class ActivosController extends Controller
{
    

    public function index() {
       
        $activos = Activo::orderBy('id', 'desc')->paginate(100000);

        return Response()->json($activos, 200);

    }


    public function read($id) {
        
        $activo = Activo::findOrFail($id);
        return Response()->json($activo, 200);

    }

    public function filter(Request $request) {


        $activos = Activo::when($request->inicio, function($query) use ($request){
                            return $query->whereBetween('fecha', [$request->inicio, $request->fin]);
                        })
                        ->when($request->sucursal_id, function($query) use ($request){
                            return $query->where('sucursal_id', $request->sucursal_id);
                        })
                        ->when($request->usuario_id, function($query) use ($request){
                            return $query->where('usuario_id', $request->usuario_id);
                        })
                        ->when($request->categoria, function($query) use ($request){
                            return $query->where('categoria', $request->categoria);
                        })
                        ->orderBy('id','desc')->paginate(100000);

        return Response()->json($activos, 200);

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
            $activo = Activo::findOrFail($request->id);
        else
            $activo = new Activo;
        
        $activo->fill($request->all());
        $activo->save();

        return Response()->json($activo, 200);

    }

    public function delete($id)
    {
       
        $activo = Activo::findOrFail($id);
        $activo->delete();

        return Response()->json($activo, 201);

    }


}
