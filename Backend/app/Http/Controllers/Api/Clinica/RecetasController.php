<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Receta;
use App\Services\Clinica\ClinicaPermisos;
use App\Services\Clinica\RecetaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecetasController extends Controller
{
    public function __construct(private RecetaService $recetas)
    {
    }

    public function index(int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::RECETAS_VER) ?? false;
        $lista = Receta::where('id_paciente', $paciente->id)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Receta $r) => $this->recetas->presentar($r, $detalle));

        return response()->json(['data' => $lista]);
    }

    public function show(int $idPaciente, int $idReceta)
    {
        $receta = $this->buscar($idPaciente, $idReceta);
        if ($receta === null) {
            return response()->json(['error' => 'Receta no encontrada'], 404);
        }

        $detalle = auth()->user()?->can(ClinicaPermisos::RECETAS_VER) ?? false;

        return response()->json(['data' => $this->recetas->presentar($receta, $detalle)]);
    }

    public function store(Request $request, int $idPaciente)
    {
        $paciente = Paciente::find($idPaciente);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $receta = $this->recetas->crear(
                (int) $request->user()->id_empresa,
                $request->user()->id,
                $paciente,
                $request->all(),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.recetas: no se pudo crear');

            return response()->json(['message' => 'No se pudo guardar la receta'], 500);
        }

        return response()->json(['data' => $this->recetas->presentar($receta, true)], 201);
    }

    public function update(Request $request, int $idPaciente, int $idReceta)
    {
        $receta = $this->buscar($idPaciente, $idReceta);
        if ($receta === null) {
            return response()->json(['error' => 'Receta no encontrada'], 404);
        }

        try {
            $receta = $this->recetas->actualizar($receta, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.recetas: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo guardar la receta'], 500);
        }

        return response()->json(['data' => $this->recetas->presentar($receta, true)]);
    }

    public function emitir(int $idPaciente, int $idReceta)
    {
        $receta = $this->buscar($idPaciente, $idReceta);
        if ($receta === null) {
            return response()->json(['error' => 'Receta no encontrada'], 404);
        }

        try {
            $receta = $this->recetas->emitir($receta);
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $this->recetas->presentar($receta, true)]);
    }

    public function anular(Request $request, int $idPaciente, int $idReceta)
    {
        $receta = $this->buscar($idPaciente, $idReceta);
        if ($receta === null) {
            return response()->json(['error' => 'Receta no encontrada'], 404);
        }

        try {
            $receta = $this->recetas->anular($receta, (string) $request->input('motivo_anulacion', ''));
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json(['data' => $this->recetas->presentar($receta, true)]);
    }

    public function pdf(int $idPaciente, int $idReceta)
    {
        $receta = $this->buscar($idPaciente, $idReceta);
        if ($receta === null) {
            return response()->json(['error' => 'Receta no encontrada'], 404);
        }
        if ($receta->estado !== 'emitida') {
            return response()->json(['error' => 'Solo recetas emitidas tienen PDF'], 422);
        }

        $paciente = Paciente::findOrFail($idPaciente);
        $empresa = Empresa::findOrFail((int) auth()->user()->id_empresa);

        $datos = $this->recetas->datosPdf($receta, $empresa, $paciente);
        $pdf = app('dompdf.wrapper')->loadView('clinica.receta-pdf', $datos);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('receta-'.$receta->id.'.pdf');
    }

    private function buscar(int $idPaciente, int $id): ?Receta
    {
        return Receta::where('id_paciente', $idPaciente)->where('id', $id)->first();
    }
}
