<?php

namespace App\Http\Controllers\Api\Creditos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Creditos\Fiador;

class FiadoresController extends Controller
{

    public function read($id) {

        $fiador = Fiador::where('id', $id)->firstOrFail();
        return Response()->json($fiador, 200);

    }

    public function search($txt) {

        $fiadors = Fiador::where('categoria_id', '!=', 1)->where('nombre', 'like' ,'%' . $txt . '%')->paginate(10);
        return Response()->json($fiadors, 200);

    }


    public function store(Request $request)
    {

        $request->validate([
            'credito_id'    => 'required|numeric',
            'cliente_id'    => 'required|numeric',
            'nota'         => 'required|max:255',
        ]);

        if($request->id)
            $fiador = Fiador::findOrFail($request->id);
        else
            $fiador = new Fiador;

        $fiador->fill($request->all());
        $fiador->save();

        return Response()->json($fiador, 200);

    }

    public function delete($id)
    {
        $fiador = Fiador::findOrFail($id);
        $fiador->delete();

        return Response()->json($fiador, 201);

    }

}
