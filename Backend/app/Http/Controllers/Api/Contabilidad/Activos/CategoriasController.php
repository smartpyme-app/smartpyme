<?php

namespace App\Http\Controllers\Api\Contabilidad\Activos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\StoreActivoCategoriaRequest;
use App\Models\Admin\Empresa;
use App\Models\Contabilidad\ActivoCategoria;
use App\Services\Contabilidad\ActivosCategoriasBootstrapService;
use Illuminate\Http\Request;
use JWTAuth;

class CategoriasController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivoCategoria::orderBy('nombre');

        if ($request->boolean('list')) {
            return response()->json($query->get([
                'id', 'nombre', 'vida_util_anios', 'porcentaje_anual',
                'valor_residual_default', 'permite_bien_usado',
            ]), 200);
        }

        return response()->json($query->get(), 200);
    }

    public function store(StoreActivoCategoriaRequest $request)
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        $categoria = $request->id
            ? ActivoCategoria::findOrFail($request->id)
            : new ActivoCategoria;

        $categoria->fill($request->validated());
        $categoria->id_empresa = $usuario->id_empresa;
        $categoria->metodo_depreciacion = $categoria->metodo_depreciacion ?: 'linea_recta';
        $categoria->save();

        return response()->json($categoria, 200);
    }

    public function importarPlantillas(ActivosCategoriasBootstrapService $bootstrap)
    {
        $usuario = JWTAuth::parseToken()->authenticate();
        $empresa = Empresa::findOrFail($usuario->id_empresa);
        $copiadas = $bootstrap->copiarPlantillasEmpresa($empresa, false);

        return response()->json([
            'copiadas' => $copiadas,
            'message' => $copiadas
                ? "Se importaron {$copiadas} categoría(s) desde plantillas del país."
                : 'No hay plantillas nuevas para importar.',
        ], 200);
    }

    public function delete($id)
    {
        $categoria = ActivoCategoria::findOrFail($id);

        if ($categoria->activos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría con activos asociados.',
            ], 422);
        }

        $categoria->delete();

        return response()->json($categoria, 201);
    }
}
