<?php

namespace App\Http\Controllers\Api\Transporte\Motoristas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Transporte\Motoristas\Motorista;

class MotoristasController extends Controller
{
    

    public function index() {
       
        $motoristas = Motorista::orderBy('id','desc')->paginate(200);

        return Response()->json($motoristas, 200);

    }

    public function list() {
       
        $motorista = Motorista::orderBy('id','desc')->get();

        return Response()->json($motorista, 200);

    }


    public function read($id) {
        
        $motorista = Motorista::where('id', $id)->firstOrFail();

        return Response()->json($motorista, 200);
    }

    public function filter(Request $request) {
        
        $motorista = Motorista::when($request->sucursal_id, function($query) use ($request){
                                    return $query->where('sucursal_id', $request->sucursal_id);
                                })
                                ->when($request->tipo, function($query) use ($request){
                                    return $query->where('tipo', $request->tipo);
                                })
                                ->orderBy('id','desc')->paginate(100000);

        return Response()->json($motorista, 200);
    }

    public function search($txt) {

        $motorista = Motorista::where('name', 'like' ,'%' . $txt . '%')->paginate(200);
        return Response()->json($motorista, 200);

    }


    public function store(Request $request)
    {
        $request->validate([
            'nombre'   => 'required|max:255',
        ]);

        if($request->id)
            $motorista = Motorista::findOrFail($request->id);
        else
            $motorista = new Motorista;
        
        $motorista->fill($request->all());
        $motorista->save();

        return Response()->json($motorista, 200);


    }

    public function delete($id)
    {
       
        $motorista = Motorista::findOrFail($id);
        $motorista->delete();

        return Response()->json($motorista, 201);

    }

    public function comisiones($id)
    {
        
        $motorista = Motorista::where('id', $id)->with('comisiones')->firstOrFail();

        return Response()->json($motorista, 200); 

    }

    public function fletes(Request $request)
    {
        
        $motoristas = Motorista::orderBy('nombre', 'desc')->get();

        foreach ($motoristas as $motorista) {
            $fletes = $motorista->fletes()->whereBetween('fecha', [$request->inicio, $request->fin])->get();
            $motorista->fletes = $fletes;
            $motorista->cantidad_fletes = $fletes->count();
            $motorista->total_pago_fletes = $fletes->sum('motorista');
        }

        return Response()->json($motoristas, 200); 

    }



}
