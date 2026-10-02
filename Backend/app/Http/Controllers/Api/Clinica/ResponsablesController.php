<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\PacienteResponsable;
use App\Services\Clinica\PacienteService;
use App\Services\Clinica\ResponsableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ResponsablesController extends Controller
{
    public function __construct(
        private ResponsableService $responsables,
        private PacienteService $pacientes,
    ) {
    }

    public function store(Request $request, int $id)
    {
        $paciente = Paciente::find($id);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $this->responsables->vincular($paciente, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.responsables: no se pudo vincular');

            return response()->json(['message' => 'No se pudo guardar el responsable'], 500);
        }

        return response()->json(['data' => $this->pacientes->presentar($paciente->fresh())], 201);
    }

    public function update(Request $request, int $id, int $idVinculo)
    {
        $paciente = Paciente::find($id);
        $vinculo = $paciente ? PacienteResponsable::where('id_paciente', $paciente->id)->find($idVinculo) : null;
        if ($paciente === null || $vinculo === null) {
            return response()->json(['error' => 'Responsable no encontrado'], 404);
        }

        try {
            $this->responsables->actualizar($paciente, $vinculo, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.responsables: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo guardar el responsable'], 500);
        }

        return response()->json(['data' => $this->pacientes->presentar($paciente->fresh())]);
    }

    public function desactivar(int $id, int $idVinculo)
    {
        $paciente = Paciente::find($id);
        $vinculo = $paciente ? PacienteResponsable::where('id_paciente', $paciente->id)->find($idVinculo) : null;
        if ($paciente === null || $vinculo === null) {
            return response()->json(['error' => 'Responsable no encontrado'], 404);
        }

        try {
            $this->responsables->desactivar($paciente, $vinculo);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.responsables: no se pudo desactivar');

            return response()->json(['message' => 'No se pudo desactivar el responsable'], 500);
        }

        return response()->json(['data' => $this->pacientes->presentar($paciente->fresh())]);
    }

    public function cerrarAlta(Request $request, int $id)
    {
        $paciente = Paciente::find($id);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $paciente = $this->responsables->cerrarAlta($paciente, $request->boolean('cerrada'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.pacientes: no se pudo cerrar el alta');

            return response()->json(['message' => 'No se pudo actualizar el alta'], 500);
        }

        return response()->json(['data' => $this->pacientes->presentar($paciente)]);
    }

    public function deCliente(int $idCliente)
    {
        $empresaId = (int) auth()->user()->id_empresa;
        $lista = $this->responsables->pacientesDeCliente($empresaId, $idCliente);

        return response()->json(['data' => $lista]);
    }
}
