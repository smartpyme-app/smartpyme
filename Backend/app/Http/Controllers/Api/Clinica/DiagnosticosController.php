<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Diagnostico;
use App\Models\Clinica\DiagnosticoCatalogo;
use App\Models\Clinica\Paciente;
use App\Services\Clinica\ClinicaPermisos;
use App\Services\Clinica\DiagnosticoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DiagnosticosController extends Controller
{
    public function __construct(private DiagnosticoService $diagnosticos)
    {
    }

    public function indexCatalogo(Request $request)
    {
        $lista = $this->diagnosticos->listarCatalogo(
            (int) $request->user()->id_empresa,
            $request->input('q'),
        );

        return response()->json(['data' => $lista]);
    }

    public function storeCatalogo(Request $request)
    {
        try {
            $item = $this->diagnosticos->guardarCatalogo((int) $request->user()->id_empresa, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.diagnosticos.catalogo: no se pudo guardar');

            return response()->json(['message' => 'No se pudo guardar el catálogo'], 500);
        }

        return response()->json(['data' => $item], 201);
    }

    public function updateCatalogo(Request $request, int $id)
    {
        $item = DiagnosticoCatalogo::find($id);
        if ($item === null || (int) $item->id_empresa !== (int) $request->user()->id_empresa) {
            return response()->json(['error' => 'Entrada de catálogo no encontrada'], 404);
        }

        try {
            $item = $this->diagnosticos->guardarCatalogo((int) $request->user()->id_empresa, $request->all(), $item);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.diagnosticos.catalogo: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo actualizar el catálogo'], 500);
        }

        return response()->json(['data' => $item]);
    }

    public function index(Request $request, int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::DIAGNOSTICOS_VER) ?? false;
        $consulta = Diagnostico::query()
            ->where('id_paciente', $paciente->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($request->filled('id_consulta')) {
            $consulta->where('id_consulta', (int) $request->input('id_consulta'));
        }

        $lista = $consulta->get()->map(fn (Diagnostico $d) => $this->diagnosticos->presentar($d, $detalle));

        return response()->json(['data' => $lista]);
    }

    public function show(int $idPaciente, int $idDiagnostico)
    {
        $diagnostico = $this->buscar($idPaciente, $idDiagnostico);
        if ($diagnostico === null) {
            return response()->json(['error' => 'Diagnóstico no encontrado'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::DIAGNOSTICOS_VER) ?? false;

        return response()->json(['data' => $this->diagnosticos->presentar($diagnostico, $detalle)]);
    }

    public function store(Request $request, int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $diagnostico = $this->diagnosticos->crear(
                (int) $request->user()->id_empresa,
                $request->user()->id,
                $paciente,
                $request->all(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.diagnosticos: no se pudo crear');

            return response()->json(['message' => 'No se pudo guardar el diagnóstico'], 500);
        }

        return response()->json(['data' => $this->diagnosticos->presentar($diagnostico, true)], 201);
    }

    public function update(Request $request, int $idPaciente, int $idDiagnostico)
    {
        $diagnostico = $this->buscar($idPaciente, $idDiagnostico);
        if ($diagnostico === null) {
            return response()->json(['error' => 'Diagnóstico no encontrado'], 404);
        }

        try {
            $diagnostico = $this->diagnosticos->actualizar($diagnostico, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.diagnosticos: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo actualizar el diagnóstico'], 500);
        }

        return response()->json(['data' => $this->diagnosticos->presentar($diagnostico, true)]);
    }

    public function cerrar(int $idPaciente, int $idDiagnostico)
    {
        $diagnostico = $this->buscar($idPaciente, $idDiagnostico);
        if ($diagnostico === null) {
            return response()->json(['error' => 'Diagnóstico no encontrado'], 404);
        }

        try {
            $diagnostico = $this->diagnosticos->cerrar($diagnostico);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.diagnosticos: no se pudo cerrar');

            return response()->json(['message' => 'No se pudo cerrar el diagnóstico'], 500);
        }

        return response()->json(['data' => $this->diagnosticos->presentar($diagnostico, true)]);
    }

    public function anular(Request $request, int $idPaciente, int $idDiagnostico)
    {
        $diagnostico = $this->buscar($idPaciente, $idDiagnostico);
        if ($diagnostico === null) {
            return response()->json(['error' => 'Diagnóstico no encontrado'], 404);
        }

        try {
            $diagnostico = $this->diagnosticos->anular($diagnostico, (string) $request->input('motivo_anulacion', ''));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.diagnosticos: no se pudo anular');

            return response()->json(['message' => 'No se pudo anular el diagnóstico'], 500);
        }

        return response()->json(['data' => $this->diagnosticos->presentar($diagnostico, true)]);
    }

    public function corregir(Request $request, int $idPaciente, int $idDiagnostico)
    {
        $diagnostico = $this->buscar($idPaciente, $idDiagnostico);
        if ($diagnostico === null) {
            return response()->json(['error' => 'Diagnóstico no encontrado'], 404);
        }

        try {
            $nuevo = $this->diagnosticos->corregir(
                $diagnostico,
                (int) $request->user()->id_empresa,
                $request->user()->id,
                (string) $request->input('motivo', ''),
                $request->all(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.diagnosticos: no se pudo corregir');

            return response()->json(['message' => 'No se pudo registrar la corrección'], 500);
        }

        return response()->json(['data' => $this->diagnosticos->presentar($nuevo, true)], 201);
    }

    private function buscar(int $idPaciente, int $idDiagnostico): ?Diagnostico
    {
        return Diagnostico::where('id_paciente', $idPaciente)->where('id', $idDiagnostico)->first();
    }
}
