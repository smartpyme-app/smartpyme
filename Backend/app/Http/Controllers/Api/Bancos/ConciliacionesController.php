<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Conciliacion;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Bancos\ConciliacionExport;

class ConciliacionesController extends Controller
{
    

    public function index(Request $request) {
       
        $conciliaciones = Conciliacion::with('cuenta')->when($request->buscador, function($query) use ($request){
                                    return $query->where('nota', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->when($request->inicio, function($query) use ($request){
                                    return $query->where('fecha', '>=', $request->inicio);
                                })
                                ->when($request->fin, function($query) use ($request){
                                    return $query->where('fecha', '<=', $request->fin);
                                })
                                ->when($request->id_usuario, function($query) use ($request){
                                    return $query->where('id_usuario', $request->id_usuario);
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->orderBy('id', 'desc')
                                ->paginate($request->paginate);

        return Response()->json($conciliaciones, 200);

    }

    public function list() {
       
        $conciliaciones = Conciliacion::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($conciliaciones, 200);

    }
    
    public function read($id) {

        $conciliacion = Conciliacion::where('id', $id)->firstOrFail();
        return Response()->json($conciliacion, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'desde'         => 'required|date',
            'hasta'         => 'required|date',
            'id_cuenta'     => 'required|numeric',
            // 'saldo_anterior' => 'required|numeric',
            'saldo_actual'  => 'required|numeric',
            'nota'          => 'sometimes|max:255',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $conciliacion = Conciliacion::findOrFail($request->id);
        else
            $conciliacion = new Conciliacion;
        
        $conciliacion->fill($request->all());
        $conciliacion->save();

        return Response()->json($conciliacion, 200);

    }

    public function delete($id)
    {
        $conciliacion = Conciliacion::findOrFail($id);
        $conciliacion->delete();

        return Response()->json($conciliacion, 201);

    }

    public function lastOne(Request $request) {

        $conciliacion = Conciliacion::where('id_cuenta', $request->id_cuenta)->latest()->first();

        return Response()->json($conciliacion, 200);

    }

    public function export(Request $request){
        $conciliaciones = new ConciliacionExport();
        $conciliaciones->filter($request);

        return Excel::download($conciliaciones, 'conciliaciones.xlsx');
    }


}
