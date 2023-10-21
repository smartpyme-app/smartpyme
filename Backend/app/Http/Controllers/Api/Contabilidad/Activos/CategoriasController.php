<?php

namespace App\Http\Controllers\Api\Contabilidad\Activos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Contabilidad\Activos\Categoria;

class CategoriasController extends Controller
{
    
    public function index() {
       
        $categorias = Categoria::orderBy('nombre', 'desc')->get();

        return Response()->json($categorias, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'  => 'required',
            'empresa_id'   => 'required',
        ]);

        if($request->id)
            $categoria = Categoria::findOrFail($request->id);
        else
            $categoria = new Categoria;
        
        $categoria->fill($request->all());
        $categoria->save();

        return Response()->json($categoria, 200);

    }

    public function delete($id)
    {
       
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return Response()->json($categoria, 201);

    }


}
