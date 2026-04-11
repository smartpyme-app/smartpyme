<?php

namespace App\Http\Controllers\Api\Compras\Gastos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use JWTAuth;
use App\Models\Compras\Gastos\Categoria;
use App\Http\Requests\Compras\Gastos\StoreCategoriaRequest;

class CategoriasController extends Controller
{

    private function idEmpresaUsuario(): int
    {
        return (int) auth()->user()->id_empresa;
    }

    public function index() {

        $categorias = Categoria::with('cuenta')
            ->where('id_empresa', $this->idEmpresaUsuario())
            ->orderBy('nombre', 'asc')->get();

        return Response()->json($categorias, 200);

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

    public function store(StoreCategoriaRequest $request)
    {

        if($request->id)
            $categoria = Categoria::findOrFail($request->id);
        else
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
