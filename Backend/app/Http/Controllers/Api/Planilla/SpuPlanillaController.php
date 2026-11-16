<?php

namespace App\Http\Controllers\Api\Planilla;

use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use App\Services\Planilla\SpuPlanillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SpuPlanillaController extends Controller
{
    private const CAMPOS_FILA = [
        'id_empleado', 'empleado', 'numero_documento', 'tipo_documento', 'afiliacion_isss', 'institucion_previsional',
        'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'apellido_casada',
        'salario', 'pago_adicional', 'monto_vacacion', 'dias', 'horas', 'dias_vacacion', 'codigo_1', 'codigo_2',
    ];

    public function __construct(private SpuPlanillaService $spu)
    {
    }

    public function datosEmpleador(): JsonResponse
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);

        return response()->json([
            'numero_patronal_isss' => $empresa->numero_patronal_isss,
            'tipo_persona' => $empresa->tipo_persona,
            'regimen_especial_isss' => $empresa->regimen_especial_isss,
            'sucursales' => Sucursal::where('id_empresa', $empresa->id)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'correlativo_centro_trabajo_isss']),
        ]);
    }

    public function guardarDatosEmpleador(Request $request): JsonResponse
    {
        $data = $request->validate([
            'numero_patronal_isss' => ['nullable', 'regex:/^\d{1,9}$/'],
            'tipo_persona' => ['nullable', Rule::in(['natural', 'juridica'])],
            'regimen_especial_isss' => ['nullable', Rule::in(['domestico', 'marino_mercante', 'regidores'])],
            'sucursales' => ['array'],
            'sucursales.*.id' => ['required', 'integer'],
            'sucursales.*.correlativo_centro_trabajo_isss' => ['nullable', 'regex:/^\d{1,3}$/'],
        ]);
        $idEmpresa = auth()->user()->id_empresa;

        Empresa::whereKey($idEmpresa)->update(Arr::only($data, ['numero_patronal_isss', 'tipo_persona', 'regimen_especial_isss']));
        foreach ($data['sucursales'] ?? [] as $sucursal) {
            Sucursal::where('id_empresa', $idEmpresa)
                ->whereKey($sucursal['id'])
                ->update(['correlativo_centro_trabajo_isss' => $sucursal['correlativo_centro_trabajo_isss'] ?? null]);
        }

        return $this->datosEmpleador();
    }

    public function preview(Request $request): JsonResponse
    {
        $periodo = $this->periodo($request);
        $empleador = $this->empleador($periodo);
        $filas = $this->spu->filas(auth()->user()->id_empresa, $periodo['id_sucursal'], $periodo['anio'], $periodo['mes']);

        return response()->json([
            'empleador' => $empleador,
            'filas' => $filas,
            'errores' => $this->spu->validar($filas, $empleador, $periodo['complementaria']),
        ]);
    }

    public function validar(Request $request): JsonResponse
    {
        [$periodo, $empleador, $filas] = $this->entrada($request);

        return response()->json(['errores' => $this->spu->validar($filas, $empleador, $periodo['complementaria'])]);
    }

    public function csv(Request $request)
    {
        [$periodo, $empleador, $filas] = $this->entrada($request);
        $errores = $this->spu->validar($filas, $empleador, $periodo['complementaria']);

        if ($errores) {
            return response()->json(['errores' => $errores], 422);
        }

        return $this->spu->descargar($filas, $empleador);
    }

    private function periodo(Request $request): array
    {
        $periodo = $request->validate([
            'anio' => ['required', 'integer', 'between:2006,2100'],
            'mes' => ['required', 'integer', 'between:1,12'],
            'id_sucursal' => ['required', 'integer', Rule::exists('sucursales', 'id')->where('id_empresa', auth()->user()->id_empresa)],
            'complementaria' => ['nullable', 'boolean'],
        ]);

        return [
            'anio' => (int) $periodo['anio'],
            'mes' => (int) $periodo['mes'],
            'id_sucursal' => (int) $periodo['id_sucursal'],
            'complementaria' => (bool) ($periodo['complementaria'] ?? false),
        ];
    }

    private function entrada(Request $request): array
    {
        $periodo = $this->periodo($request);
        $empleador = $this->empleador($periodo);

        if (!$request->exists('filas')) {
            $filas = $this->spu->filas(
                auth()->user()->id_empresa,
                $periodo['id_sucursal'],
                $periodo['anio'],
                $periodo['mes']
            );

            return [$periodo, $empleador, $filas];
        }

        $request->validate([
            'filas' => ['present', 'array', 'max:5000'],
            'filas.*' => ['array'],
        ]);
        $filas = array_map(fn ($fila) => Arr::only($fila, self::CAMPOS_FILA), $request->input('filas', []));

        return [$periodo, $empleador, $filas];
    }

    private function empleador(array $periodo): array
    {
        $empresa = Empresa::findOrFail(auth()->user()->id_empresa);
        $sucursal = Sucursal::where('id_empresa', $empresa->id)->findOrFail($periodo['id_sucursal']);

        return $this->spu->empleador($empresa, $sucursal, $periodo['anio'], $periodo['mes']);
    }
}
