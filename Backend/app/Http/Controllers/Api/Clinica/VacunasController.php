<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\VacunaAplicacion;
use App\Models\Clinica\VacunaCatalogo;
use App\Services\Clinica\ClinicaPermisos;
use App\Services\Clinica\VacunaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VacunasController extends Controller
{
    public function __construct(private VacunaService $vacunas)
    {
    }

    public function indexCatalogo(Request $request)
    {
        $idEmpresa = (int) $request->user()->id_empresa;
        $lista = $this->vacunas->listarCatalogoAdmin($idEmpresa, $request->query('q'));

        return response()->json(['data' => $lista]);
    }

    public function storeCatalogo(Request $request)
    {
        try {
            $item = $this->vacunas->guardarCatalogo((int) $request->user()->id_empresa, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.vacunas.catalogo: no se pudo crear');

            return response()->json(['message' => 'No se pudo guardar'], 500);
        }

        return response()->json(['data' => [
            'id' => $item->id,
            'nombre' => $item->nombre,
            'tipo' => $item->tipo,
            'tipo_paciente' => $item->tipo_paciente,
            'id_especie' => $item->id_especie,
            'intervalo_dias' => $item->intervalo_dias,
            'activo' => (bool) $item->activo,
        ]], 201);
    }

    public function updateCatalogo(Request $request, int $idCatalogo)
    {
        $item = VacunaCatalogo::find($idCatalogo);
        if ($item === null) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }

        try {
            $item = $this->vacunas->guardarCatalogo((int) $request->user()->id_empresa, $request->all(), $item);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.vacunas.catalogo: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo guardar'], 500);
        }

        return response()->json(['data' => [
            'id' => $item->id,
            'nombre' => $item->nombre,
            'tipo' => $item->tipo,
            'tipo_paciente' => $item->tipo_paciente,
            'id_especie' => $item->id_especie,
            'intervalo_dias' => $item->intervalo_dias,
            'activo' => (bool) $item->activo,
        ]]);
    }

    public function catalogoPaciente(Request $request, int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $lista = $this->vacunas->listarCatalogo(
            (int) $request->user()->id_empresa,
            $paciente,
            $request->query('q'),
        );

        return response()->json(['data' => $lista]);
    }

    public function index(int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::VACUNAS_VER) ?? false;

        return response()->json(['data' => $this->vacunas->listarAplicaciones($paciente, $detalle)]);
    }

    public function show(int $idPaciente, int $idVacuna)
    {
        $aplicacion = $this->buscar($idPaciente, $idVacuna);
        if ($aplicacion === null) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::VACUNAS_VER) ?? false;

        return response()->json(['data' => $this->vacunas->presentar($aplicacion, $detalle)]);
    }

    public function store(Request $request, int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $aplicacion = $this->vacunas->registrar(
                (int) $request->user()->id_empresa,
                $request->user()->id,
                $paciente,
                $request->all(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.vacunas: no se pudo registrar');

            return response()->json(['message' => 'No se pudo registrar la vacuna'], 500);
        }

        return response()->json(['data' => $this->vacunas->presentar($aplicacion, true)], 201);
    }

    public function anular(Request $request, int $idPaciente, int $idVacuna)
    {
        $aplicacion = $this->buscar($idPaciente, $idVacuna);
        if ($aplicacion === null) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }

        try {
            $aplicacion = $this->vacunas->anular($aplicacion, (string) $request->input('motivo_anulacion', ''));
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $this->vacunas->presentar($aplicacion, true)]);
    }

    private function buscar(int $idPaciente, int $id): ?VacunaAplicacion
    {
        return VacunaAplicacion::where('id_paciente', $idPaciente)->where('id', $id)->first();
    }
}
