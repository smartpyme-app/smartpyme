<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Consulta;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\ClinicaPermisos;
use App\Services\Clinica\ConsultaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConsultasController extends Controller
{
    public function __construct(private ConsultaService $consultas)
    {
    }

    public function index(int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $texto = auth()->user()?->can(ClinicaPermisos::CONSULTAS_VER) ?? false;
        $lista = Consulta::where('id_paciente', $paciente->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Consulta $c) => $this->consultas->presentar($c, $texto));

        return response()->json(['data' => $lista]);
    }

    public function show(int $idPaciente, int $idConsulta)
    {
        $consulta = $this->buscar($idPaciente, $idConsulta);
        if ($consulta === null) {
            return response()->json(['error' => 'Consulta no encontrada'], 404);
        }

        $texto = auth()->user()?->can(ClinicaPermisos::CONSULTAS_VER) ?? false;

        return response()->json(['data' => $this->consultas->presentar($consulta, $texto)]);
    }

    public function store(Request $request, int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $consulta = $this->consultas->crear(
                (int) $request->user()->id_empresa,
                $request->user()->id,
                $paciente,
                $request->all(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.consultas: no se pudo crear');

            return response()->json(['message' => 'No se pudo guardar la consulta'], 500);
        }

        return response()->json([
            'data' => $this->consultas->presentar($consulta, true),
        ], 201);
    }

    public function update(Request $request, int $idPaciente, int $idConsulta)
    {
        $consulta = $this->buscar($idPaciente, $idConsulta);
        if ($consulta === null) {
            return response()->json(['error' => 'Consulta no encontrada'], 404);
        }

        try {
            $consulta = $this->consultas->actualizar($consulta, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.consultas: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo guardar la consulta'], 500);
        }

        return response()->json(['data' => $this->consultas->presentar($consulta, true)]);
    }

    public function cerrar(int $idPaciente, int $idConsulta)
    {
        $consulta = $this->buscar($idPaciente, $idConsulta);
        if ($consulta === null) {
            return response()->json(['error' => 'Consulta no encontrada'], 404);
        }

        try {
            $consulta = $this->consultas->cerrar($consulta);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.consultas: no se pudo cerrar');

            return response()->json(['message' => 'No se pudo cerrar la consulta'], 500);
        }

        return response()->json(['data' => $this->consultas->presentar($consulta, true)]);
    }

    public function anular(Request $request, int $idPaciente, int $idConsulta)
    {
        $consulta = $this->buscar($idPaciente, $idConsulta);
        if ($consulta === null) {
            return response()->json(['error' => 'Consulta no encontrada'], 404);
        }

        try {
            $consulta = $this->consultas->anular($consulta, (string) $request->input('motivo_anulacion', ''));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.consultas: no se pudo anular');

            return response()->json(['message' => 'No se pudo anular la consulta'], 500);
        }

        return response()->json(['data' => $this->consultas->presentar($consulta, true)]);
    }

    private function buscar(int $idPaciente, int $id): ?Consulta
    {
        return Consulta::where('id_paciente', $idPaciente)->where('id', $id)->first();
    }
}
