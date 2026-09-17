<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contabilidad\StoreActivoPlantillaRequest;
use App\Models\Contabilidad\PaisActivoCategoriaPlantilla;
use Illuminate\Http\Request;

class ActivosPlantillasController extends Controller
{
    public function index(Request $request, string $codPais)
    {
        $plantillas = PaisActivoCategoriaPlantilla::query()
            ->where('cod_pais', strtoupper($codPais))
            ->orderBy('nombre')
            ->get();

        return response()->json($plantillas, 200);
    }

    public function store(StoreActivoPlantillaRequest $request)
    {
        $plantilla = $request->id
            ? PaisActivoCategoriaPlantilla::findOrFail($request->id)
            : new PaisActivoCategoriaPlantilla;

        $plantilla->fill($request->validated());
        $plantilla->cod_pais = strtoupper($request->input('cod_pais'));
        $plantilla->metodo_depreciacion = $plantilla->metodo_depreciacion ?: 'linea_recta';
        $plantilla->activo = $request->boolean('activo', true);
        $plantilla->save();

        return response()->json($plantilla, 200);
    }

    public function delete($id)
    {
        $plantilla = PaisActivoCategoriaPlantilla::findOrFail($id);
        $plantilla->delete();

        return response()->json($plantilla, 201);
    }
}
