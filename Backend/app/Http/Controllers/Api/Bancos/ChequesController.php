<?php

namespace App\Http\Controllers\Api\Bancos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bancos\Cheque;

class ChequesController extends Controller
{
    

    public function index(Request $request) {
       
        $cheques = Cheque::when($request->buscador, function($query) use ($request){
                                    return $query->where('nombre', 'like' ,'%' . $request->buscador . '%');
                                })
                                ->orderBy($request->orden, $request->direccion)
                                ->paginate($request->paginate);

        return Response()->json($cheques, 200);

    }

    public function list() {
       
        $cheques = Cheque::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($cheques, 200);

    }
    
    public function read($id) {

        $cheque = Cheque::where('id', $id)->firstOrFail();
        return Response()->json($cheque, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'fecha'         => 'required|date',
            'id_cuenta'     => 'required|numeric',
            'correlativo'   => 'required|numeric',
            'anombrede'     => 'required|max:255',
            'concepto'      => 'required|max:255',
            'total'         => 'required|numeric',
            'id_usuario'    => 'required|numeric',
            'id_empresa'    => 'required|numeric',
        ]);

        if($request->id)
            $cheque = Cheque::findOrFail($request->id);
        else
            $cheque = new Cheque;
        
        $cheque->fill($request->all());
        $cheque->save();

        return Response()->json($cheque, 200);

    }

    public function delete($id)
    {
        $cheque = Cheque::findOrFail($id);
        $cheque->delete();

        return Response()->json($cheque, 201);

    }

}
