<?php

namespace App\Http\Controllers\Api\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contabilidad\Presupuesto;
use Illuminate\Support\Facades\Crypt;
use JWTAuth;

class PresupuestosController extends Controller
{
    

    public function index(Request $request) {
       
        $presupuestos = Presupuesto::when($request->buscador, function($query) use ($request){
                        return $query->where('titulo', 'like', '%'. $request->buscador . '%');
                    })
                    ->when($request->inicio, function($query) use ($request){
                        return $query->where('fecha_inicio', '>=', $request->inicio);
                    })
                    ->when($request->fin, function($query) use ($request){
                        return $query->where('fecha_fin', '<=', $request->fin);
                    })
                    ->when($request->id_usuario, function($query) use ($request){
                        return $query->where('id_usuario', $request->id_usuario);
                    })
                    ->when($request->estado !== null, function($q) use ($request){
                        $q->where('enable', !!$request->estado);
                    })
                    ->when($request->id_proyecto, function($query) use ($request){
                        return $query->where('id_proyecto', $request->id_proyecto);
                    })
                    ->orderBy($request->orden, $request->direccion)
                    ->orderBy('id', 'desc')
                    ->paginate($request->paginate);

        return Response()->json($presupuestos, 200);

    }


    public function read($id) {
        
        $presupuesto = Presupuesto::findOrFail($id);
        return Response()->json($presupuesto, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'titulo'        => 'required|max:255',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'required|date',
            'ingresos'   => 'required|numeric',
            'egresos'   => 'required|numeric',
            'compras'   => 'required|numeric',
            'utilidad'   => 'required|numeric',
            'id_empresa'   => 'required|numeric',
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
