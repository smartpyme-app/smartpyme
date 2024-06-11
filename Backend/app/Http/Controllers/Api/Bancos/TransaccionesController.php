<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Transaccion;

class TransaccionesController extends Controller
{
    

    public function index(Request $request) {
       
        $transacciones = Transaccion::with('cuenta')->when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->orderBy($request->orden ? $request->orden : 'id', $request->direccion ? $request->direccion : 'desc')
                                ->paginate($request->paginate);

        return Response()->json($transacciones, 200);

    }

    public function list() {
       
        $transacciones = Transaccion::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($transacciones, 200);

    }
    
    public function read($id) {

        $transaccion = Transaccion::where('id', $id)->firstOrFail();
        return Response()->json($transaccion, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'id_cuenta'     => 'required|numeric',
            'concepto'      => 'required|max:255',
            'tipo'          => 'required|max:255',
            'estado'          => 'required|max:255',
            'total'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $transaccion = Transaccion::findOrFail($request->id);
        else
            $transaccion = new Transaccion;
        
        $transaccion->fill($request->all());
        $transaccion->save();

        return Response()->json($transaccion, 200);

    }

    public function delete($id)
    {
        $transaccion = Transaccion::findOrFail($id);
        $transaccion->delete();

        return Response()->json($transaccion, 201);

    }

}
