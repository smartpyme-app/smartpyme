<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Compras\Gastos\Categoria;

class CategoriasController extends Controller
{
    private function idEmpresaUsuario(): int
    {
        return (int) auth()->user()->id_empresa;
    }

    /**
     * Categorías personalizadas de gastos (tabla gastos_categorias, por id_empresa).
     */
    public function index()
    {
        $categorias = Categoria::where('id_empresa', $this->idEmpresaUsuario())
            ->orderBy('nombre', 'asc')
            ->get();

        return Response()->json($categorias, 200);
    }

    /**
     * Listado compacto para selectores (misma tabla y filtro por empresa).
     */
    public function list()
    {
        $categorias = Categoria::where('id_empresa', $this->idEmpresaUsuario())
            ->orderBy('nombre', 'asc')
            ->get();

        return Response()->json($categorias, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string',
        ]);

        $idEmpresa = $this->idEmpresaUsuario();

        if ($request->id) {
            $categoria = Categoria::where('id', $request->id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();
        } else {
            $categoria = new Categoria;
            $categoria->id_empresa = $idEmpresa;
        }

        $categoria->nombre = $request->nombre;
        $categoria->save();

        return Response()->json($categoria, 200);
    }

    public function delete($id)
    {
        $categoria = Categoria::where('id', $id)
            ->where('id_empresa', $this->idEmpresaUsuario())
            ->firstOrFail();
        $categoria->delete();

        return Response()->json($categoria, 201);
    }


}
