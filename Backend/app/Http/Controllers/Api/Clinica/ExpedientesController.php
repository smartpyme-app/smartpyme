<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\ExpedienteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExpedientesController extends Controller
{
    public function __construct(private ExpedienteService $expedientes)
    {
    }

    public function show(int $id)
    {
        $paciente = Paciente::find($id);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $expediente = $this->expedientes->dePaciente($paciente);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.expediente: no se pudo consultar');

            return response()->json(['message' => 'No se pudo cargar el expediente'], 500);
        }

        return response()->json(['data' => $this->expedientes->presentar($expediente)]);
    }

    public function estado(Request $request, int $id)
    {
        $paciente = Paciente::find($id);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $expediente = $this->expedientes->dePaciente($paciente);
            $expediente = $this->expedientes->cambiarEstado($expediente, (string) $request->input('estado'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.expediente: no se pudo cambiar estado');

            return response()->json(['message' => 'No se pudo actualizar el expediente'], 500);
        }

        return response()->json(['data' => $this->expedientes->presentar($expediente)]);
    }
}
