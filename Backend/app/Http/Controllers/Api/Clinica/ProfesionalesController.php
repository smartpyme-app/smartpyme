<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Profesional;
use App\Services\Clinica\ProfesionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProfesionalesController extends Controller
{
    public function __construct(private ProfesionalService $profesionales)
    {
    }

    public function index(Request $request)
    {
        $empresaId = (int) $request->user()->id_empresa;
        $sucursal = $request->input('id_sucursal');
        $lista = $this->profesionales->listar(
            $empresaId,
            $request->has('estado') ? (string) $request->input('estado') : '1',
            $sucursal === null || $sucursal === '' ? null : (int) $sucursal,
            $request->boolean('disponibles')
        );

        return response()->json(['data' => $lista]);
    }

    public function show(int $id)
    {
        $profesional = Profesional::find($id);
        if ($profesional === null) {
            return response()->json(['error' => 'Profesional no encontrado'], 404);
        }

        return response()->json(['data' => $this->profesionales->ver($profesional)]);
    }

    public function store(Request $request)
    {
        try {
            $profesional = $this->profesionales->guardar((int) $request->user()->id_empresa, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.profesionales: no se pudo guardar');

            return response()->json(['message' => 'No se pudo guardar el profesional'], 500);
        }

        return response()->json(['data' => $this->profesionales->ver($profesional)], 201);
    }

    public function estado(int $id)
    {
        $profesional = Profesional::find($id);
        if ($profesional === null) {
            return response()->json(['error' => 'Profesional no encontrado'], 404);
        }

        $profesional = $this->profesionales->desactivar($profesional);

        return response()->json(['data' => $this->profesionales->ver($profesional)]);
    }

    public function candidatos(Request $request)
    {
        return response()->json([
            'data' => $this->profesionales->candidatos((int) $request->user()->id_empresa),
        ]);
    }
}
