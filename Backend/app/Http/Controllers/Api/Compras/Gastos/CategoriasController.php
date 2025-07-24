<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Compras\Gastos\Categoria;

class CategoriasController extends Controller
{
    
    public function index() {
       
        $categorias = Categoria::with('cuenta')->orderBy('nombre', 'asc')->get();

        return Response()->json($categorias, 200);

    }

    public function list() {
       
        $categorias = Categoria::orderby('nombre')
                                // ->where('activo', true)
                                ->get();

        return Response()->json($categorias, 200);

    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre'  => 'required',
            'id_empresa'   => 'required',
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
