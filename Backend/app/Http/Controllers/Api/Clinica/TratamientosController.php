<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Tratamiento;
use App\Services\Clinica\ClinicaPermisos;
use App\Services\Clinica\TratamientoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TratamientosController extends Controller
{
    public function __construct(private TratamientoService $tratamientos)
    {
    }

    public function index(int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::TRATAMIENTOS_VER) ?? false;
        $lista = Tratamiento::where('id_paciente', $paciente->id)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Tratamiento $t) => $this->tratamientos->presentar($t, $detalle));

        return response()->json(['data' => $lista]);
    }

    public function show(int $idPaciente, int $idTratamiento)
    {
        $tratamiento = $this->buscar($idPaciente, $idTratamiento);
        if ($tratamiento === null) {
            return response()->json(['error' => 'Tratamiento no encontrado'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::TRATAMIENTOS_VER) ?? false;

        return response()->json(['data' => $this->tratamientos->presentar($tratamiento, $detalle)]);
    }

    public function store(Request $request, int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $tratamiento = $this->tratamientos->crear(
                (int) $request->user()->id_empresa,
                $request->user()->id,
                $paciente,
                $request->all(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.tratamientos: no se pudo crear');

            return response()->json(['message' => 'No se pudo guardar el tratamiento'], 500);
        }

        return response()->json(['data' => $this->tratamientos->presentar($tratamiento, true)], 201);
    }

    public function update(Request $request, int $idPaciente, int $idTratamiento)
    {
        $tratamiento = $this->buscar($idPaciente, $idTratamiento);
        if ($tratamiento === null) {
            return response()->json(['error' => 'Tratamiento no encontrado'], 404);
        }

        try {
            $tratamiento = $this->tratamientos->actualizar($tratamiento, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.tratamientos: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo guardar el tratamiento'], 500);
        }

        return response()->json(['data' => $this->tratamientos->presentar($tratamiento, true)]);
    }

    public function iniciar(int $idPaciente, int $idTratamiento)
    {
        $tratamiento = $this->buscar($idPaciente, $idTratamiento);
        if ($tratamiento === null) {
            return response()->json(['error' => 'Tratamiento no encontrado'], 404);
        }

        try {
            $tratamiento = $this->tratamientos->iniciar($tratamiento);
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $this->tratamientos->presentar($tratamiento, true)]);
    }

    public function suspender(Request $request, int $idPaciente, int $idTratamiento)
    {
        $tratamiento = $this->buscar($idPaciente, $idTratamiento);
        if ($tratamiento === null) {
            return response()->json(['error' => 'Tratamiento no encontrado'], 404);
        }

        try {
            $tratamiento = $this->tratamientos->suspender($tratamiento, (string) $request->input('motivo_suspension', ''));
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $this->tratamientos->presentar($tratamiento, true)]);
    }

    public function finalizar(Request $request, int $idPaciente, int $idTratamiento)
    {
        $tratamiento = $this->buscar($idPaciente, $idTratamiento);
        if ($tratamiento === null) {
            return response()->json(['error' => 'Tratamiento no encontrado'], 404);
        }

        try {
            $tratamiento = $this->tratamientos->finalizar($tratamiento, (string) $request->input('motivo_cierre', ''));
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $this->tratamientos->presentar($tratamiento, true)]);
    }

    public function storeAvance(Request $request, int $idPaciente, int $idTratamiento)
    {
        $tratamiento = $this->buscar($idPaciente, $idTratamiento);
        if ($tratamiento === null) {
            return response()->json(['error' => 'Tratamiento no encontrado'], 404);
        }

        try {
            $avance = $this->tratamientos->registrarAvance(
                $tratamiento,
                (int) $request->user()->id_empresa,
                (int) $request->user()->id,
                $request->all(),
            );
            $tratamiento = $tratamiento->fresh();
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'data' => $this->tratamientos->presentar($tratamiento, true),
            'avance_id' => $avance->id,
        ], 201);
    }

    public function storeTerapia(Request $request, int $idPaciente, int $idTratamiento)
    {
        $tratamiento = $this->buscar($idPaciente, $idTratamiento);
        if ($tratamiento === null) {
            return response()->json(['error' => 'Tratamiento no encontrado'], 404);
        }

        try {
            $terapia = $this->tratamientos->registrarTerapia(
                $tratamiento,
                (int) $request->user()->id_empresa,
                (int) $request->user()->id,
                $request->all(),
            );
            $tratamiento = $tratamiento->fresh();
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'data' => $this->tratamientos->presentar($tratamiento, true),
            'terapia_id' => $terapia->id,
        ], 201);
    }

    private function buscar(int $idPaciente, int $id): ?Tratamiento
    {
        return Tratamiento::where('id_paciente', $idPaciente)->where('id', $id)->first();
    }
}
