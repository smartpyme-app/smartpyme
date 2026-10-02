<?php

namespace App\Http\Controllers\Api\Clinica;

use App\Http\Controllers\Controller;
use App\Models\Clinica\Especie;
use App\Models\Clinica\Paciente;
use App\Models\Clinica\Raza;
use App\Services\Clinica\ClinicaCatalogoBootstrap;
use App\Services\Clinica\PacienteReglas;
use App\Services\Clinica\PacienteService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PacientesController extends Controller
{
    public function __construct(private PacienteService $pacientes)
    {
    }

    public function index(Request $request)
    {
        $estado = (string) $request->input('estado', '1');
        $consulta = Paciente::query()->with(['expediente', 'especie', 'raza', 'sucursal']);

        if ($estado === '1' || $estado === 'activo') {
            $consulta->where('activo', true);
        } elseif ($estado === '0' || $estado === 'inactivo') {
            $consulta->where('activo', false);
        }

        if ($request->filled('tipo')) {
            $consulta->where('tipo', strtoupper((string) $request->input('tipo')));
        }
        if ($request->filled('id_sucursal')) {
            $consulta->where('id_sucursal', $request->input('id_sucursal'));
        }
        if ($request->filled('id_especie')) {
            $consulta->where('id_especie', $request->input('id_especie'));
        }

        $busqueda = trim((string) ($request->input('buscador') ?: $request->input('q', '')));
        if ($busqueda !== '') {
            $consulta->where(function ($q) use ($busqueda) {
                $q->where('nombres', 'like', '%'.$busqueda.'%')
                    ->orWhere('apellidos', 'like', '%'.$busqueda.'%')
                    ->orWhere('nombre', 'like', '%'.$busqueda.'%')
                    ->orWhere('documento', 'like', '%'.$busqueda.'%')
                    ->orWhere('microchip', 'like', '%'.$busqueda.'%')
                    ->orWhereHas('expediente', fn ($expediente) => $expediente->where('numero', 'like', '%'.$busqueda.'%'));
            });
        }

        $porPagina = (int) $request->input('paginate', 25);
        if (! in_array($porPagina, [10, 25, 50, 100], true)) {
            $porPagina = 25;
        }

        $pagina = $consulta->orderByDesc('id')->paginate($porPagina);
        $pagina->setCollection(
            $pagina->getCollection()->map(fn (Paciente $paciente) => $this->pacientes->presentar($paciente))
        );

        return response()->json($pagina);
    }

    public function show(int $id)
    {
        $paciente = Paciente::with(['expediente', 'especie', 'raza', 'sucursal'])->find($id);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        return response()->json(['data' => $this->pacientes->presentar($paciente)]);
    }

    public function store(Request $request)
    {
        $empresaId = (int) $request->user()->id_empresa;

        try {
            $paciente = $this->pacientes->crear($empresaId, $request->user()->id, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.pacientes: no se pudo crear');

            return response()->json(['message' => 'No se pudo guardar el paciente'], 500);
        }

        return response()->json(['data' => $this->pacientes->presentar($paciente)], 201);
    }

    public function update(Request $request, int $id)
    {
        $paciente = Paciente::find($id);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        try {
            $paciente = $this->pacientes->actualizar($paciente, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('clinica.pacientes: no se pudo actualizar');

            return response()->json(['message' => 'No se pudo guardar el paciente'], 500);
        }

        return response()->json(['data' => $this->pacientes->presentar($paciente)]);
    }

    public function estado(Request $request, int $id)
    {
        $paciente = Paciente::find($id);
        if ($paciente === null) {
            return response()->json(['error' => 'Paciente no encontrado'], 404);
        }

        $paciente->activo = $request->boolean('activo');
        $paciente->save();

        return response()->json(['data' => $this->pacientes->presentar($paciente)]);
    }

    public function especies()
    {
        $empresaId = (int) auth()->user()->id_empresa;
        ClinicaCatalogoBootstrap::asegurar($empresaId);

        $especies = Especie::with('razas')->orderBy('nombre')->get()->map(fn (Especie $especie) => [
            'id' => $especie->id,
            'nombre' => $especie->nombre,
            'razas' => $especie->razas->map(fn (Raza $raza) => [
                'id' => $raza->id,
                'nombre' => $raza->nombre,
            ])->values(),
        ]);

        return response()->json(['data' => $especies]);
    }

    public function storeEspecie(Request $request)
    {
        $nombre = PacienteReglas::vacio($request->input('nombre'));
        if ($nombre === null) {
            throw ValidationException::withMessages(['nombre' => 'El nombre de la especie es obligatorio.']);
        }

        try {
            $especie = Especie::create([
                'id_empresa' => (int) $request->user()->id_empresa,
                'nombre' => $nombre,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['nombre' => 'Esa especie ya existe en la empresa.']);
        }

        return response()->json(['data' => ['id' => $especie->id, 'nombre' => $especie->nombre, 'razas' => []]], 201);
    }

    public function storeRaza(Request $request, int $idEspecie)
    {
        $especie = Especie::find($idEspecie);
        if ($especie === null) {
            return response()->json(['error' => 'Especie no encontrada'], 404);
        }

        $nombre = PacienteReglas::vacio($request->input('nombre'));
        if ($nombre === null) {
            throw ValidationException::withMessages(['nombre' => 'El nombre de la raza es obligatorio.']);
        }

        try {
            $raza = Raza::create([
                'id_empresa' => (int) $request->user()->id_empresa,
                'id_especie' => $especie->id,
                'nombre' => $nombre,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['nombre' => 'Esa raza ya existe para la especie.']);
        }

        return response()->json(['data' => ['id' => $raza->id, 'nombre' => $raza->nombre]], 201);
    }
}
