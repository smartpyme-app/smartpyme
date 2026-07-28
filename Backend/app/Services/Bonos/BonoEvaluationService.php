<?php

namespace App\Services\Bonos;

use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\Bonos\BonoEvaluacion;
use App\Models\Bonos\BonoGenerado;
use App\Models\Bonos\BonoRegla;
use App\Services\Ventas\VentaMontosPorVendedorService;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BonoEvaluationService
{
    private const SLUG = 'bonos-vendedores';

    /** @var Closure(?int): array<int> */
    private Closure $obtenerEmpresasActivas;

    /** @var Closure(int): Collection<int, BonoRegla> */
    private Closure $obtenerReglasActivas;

    /** @var Closure(int, string, string): array<int> */
    private Closure $obtenerVendedoresConVentas;

    /** @var Closure(array<string, mixed>): ?object */
    private Closure $buscarBonoGenerado;

    /** @var Closure(array<string, mixed>): object */
    private Closure $crearBonoGenerado;

    /** @var Closure(object, array<string, mixed>): void */
    private Closure $actualizarBonoGenerado;

    /** @var Closure(array<string, mixed>): void */
    private Closure $registrarEvaluacion;

    /**
     * @param  Closure(?int): array<int>|null  $obtenerEmpresasActivas
     * @param  Closure(int): Collection<int, BonoRegla>|null  $obtenerReglasActivas
     * @param  Closure(int, string, string): array<int>|null  $obtenerVendedoresConVentas
     * @param  Closure(array<string, mixed>): ?object|null  $buscarBonoGenerado
     * @param  Closure(array<string, mixed>): object|null  $crearBonoGenerado
     * @param  Closure(object, array<string, mixed>): void|null  $actualizarBonoGenerado
     * @param  Closure(array<string, mixed>): void|null  $registrarEvaluacion
     */
    public function __construct(
        private BonoMetaCalculator $metaCalculator,
        private BonoReglaEvaluator $evaluator,
        ?Closure $obtenerEmpresasActivas = null,
        ?Closure $obtenerReglasActivas = null,
        ?Closure $obtenerVendedoresConVentas = null,
        ?Closure $buscarBonoGenerado = null,
        ?Closure $crearBonoGenerado = null,
        ?Closure $actualizarBonoGenerado = null,
        ?Closure $registrarEvaluacion = null,
    ) {
        $this->obtenerEmpresasActivas = $obtenerEmpresasActivas
            ?? fn (?int $idEmpresa) => $this->empresasConBonosActivos($idEmpresa);
        $this->obtenerReglasActivas = $obtenerReglasActivas
            ?? fn (int $idEmpresa) => BonoRegla::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('activo', true)
                ->get();
        $this->obtenerVendedoresConVentas = $obtenerVendedoresConVentas
            ?? fn (int $idEmpresa, string $desde, string $hasta) => $this->vendedoresConVentasEnPeriodo($idEmpresa, $desde, $hasta);
        $this->buscarBonoGenerado = $buscarBonoGenerado
            ?? fn (array $unique) => BonoGenerado::withoutGlobalScope('empresa')->where($unique)->first();
        $this->crearBonoGenerado = $crearBonoGenerado
            ?? fn (array $payload) => BonoGenerado::withoutGlobalScope('empresa')->create($payload);
        $this->actualizarBonoGenerado = $actualizarBonoGenerado
            ?? function (object $bono, array $values): void {
                BonoGenerado::withoutGlobalScope('empresa')
                    ->where('id', $bono->id)
                    ->update($values);
            };
        $this->registrarEvaluacion = $registrarEvaluacion
            ?? fn (array $payload) => BonoEvaluacion::withoutGlobalScope('empresa')->create($payload);
    }

    /** @return array<string, mixed> */
    public function evaluar(
        ?int $idEmpresa = null,
        ?string $desde = null,
        ?string $hasta = null,
        string $origen = BonoEvaluacion::ORIGEN_JOB,
        ?int $idUsuario = null,
    ): array {
        $periodo = $this->resolverPeriodo($desde, $hasta);
        $resumenGlobal = $this->resumenVacio();
        $resumenGlobal['periodo'] = $periodo;

        foreach (($this->obtenerEmpresasActivas)($idEmpresa) as $idEmp) {
            $resumen = $this->evaluarEmpresa((int) $idEmp, $periodo['inicio'], $periodo['fin']);
            ($this->registrarEvaluacion)([
                'id_empresa' => (int) $idEmp,
                'periodo_inicio' => $periodo['inicio'],
                'periodo_fin' => $periodo['fin'],
                'origen' => $origen,
                'id_usuario' => $idUsuario,
                'resumen' => $resumen,
            ]);
            $this->mergeResumen($resumenGlobal, $resumen);
        }

        return $resumenGlobal;
    }

    /** @return array<string, int> */
    private function evaluarEmpresa(int $idEmpresa, string $desde, string $hasta): array
    {
        $resumen = $this->resumenVacio();
        $vendedores = ($this->obtenerVendedoresConVentas)($idEmpresa, $desde, $hasta);

        foreach (($this->obtenerReglasActivas)($idEmpresa) as $regla) {
            ++$resumen['reglas_evaluadas'];

            foreach ($vendedores as $idVendedor) {
                ++$resumen['vendedores_procesados'];
                $ventas = $this->metaCalculator->ventasVendedorPeriodo($idEmpresa, (int) $idVendedor, $desde, $hasta);
                $monto = $this->evaluator->calcular($regla->tipo, $regla->config ?? [], $ventas);
                $resultado = $this->persistirBono($idEmpresa, (int) $idVendedor, $regla, $desde, $hasta, $ventas, $monto);
                ++$resumen[$resultado];
            }
        }

        return $resumen;
    }

    /** @return 'creados'|'actualizados'|'omitidos_monto'|'protegidos' */
    private function persistirBono(
        int $idEmpresa,
        int $idVendedor,
        object $regla,
        string $desde,
        string $hasta,
        float $ventas,
        float $monto,
    ): string {
        if ($monto <= 0) {
            return 'omitidos_monto';
        }

        $unique = [
            'id_empresa' => $idEmpresa,
            'id_vendedor' => $idVendedor,
            'id_regla' => $regla->id,
            'periodo_inicio' => $desde,
            'periodo_fin' => $hasta,
        ];

        $existing = ($this->buscarBonoGenerado)($unique);

        if ($existing && in_array($existing->estado, [BonoGenerado::ESTADO_APROBADO, BonoGenerado::ESTADO_PAGADO], true)) {
            return 'protegidos';
        }

        $values = [
            'monto' => $monto,
            'monto_ventas_base' => $ventas,
            'estado' => BonoGenerado::ESTADO_PENDIENTE,
        ];

        if ($existing) {
            ($this->actualizarBonoGenerado)($existing, $values);

            return 'actualizados';
        }

        ($this->crearBonoGenerado)(array_merge($unique, $values));

        return 'creados';
    }

    /** @return array{inicio: string, fin: string} */
    private function resolverPeriodo(?string $desde, ?string $hasta): array
    {
        if ($desde && $hasta) {
            return ['inicio' => $desde, 'fin' => $hasta];
        }

        $now = Carbon::now();

        return [
            'inicio' => $now->copy()->startOfMonth()->toDateString(),
            'fin' => $now->copy()->endOfMonth()->toDateString(),
        ];
    }

    /** @return array<int> */
    private function empresasConBonosActivos(?int $idEmpresa): array
    {
        return EmpresaFuncionalidad::query()
            ->where('activo', true)
            ->whereHas('funcionalidad', fn ($q) => $q->where('slug', self::SLUG))
            ->when($idEmpresa !== null, fn ($q) => $q->where('id_empresa', $idEmpresa))
            ->pluck('id_empresa')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<int> */
    private function vendedoresConVentasEnPeriodo(int $idEmpresa, string $desde, string $hasta): array
    {
        $exprVendedor = VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v');

        return DB::table('detalles_venta as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.id_venta')
            ->where('v.id_empresa', $idEmpresa)
            ->where('v.estado', 'Pagada')
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->selectRaw("DISTINCT {$exprVendedor} as id_vendedor")
            ->having('id_vendedor', '>', 0)
            ->pluck('id_vendedor')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<string, int> */
    private function resumenVacio(): array
    {
        return [
            'empresas' => 0,
            'reglas_evaluadas' => 0,
            'vendedores_procesados' => 0,
            'creados' => 0,
            'actualizados' => 0,
            'omitidos_monto' => 0,
            'protegidos' => 0,
        ];
    }

    /** @param  array<string, int>  $target
     * @param  array<string, int>  $partial */
    private function mergeResumen(array &$target, array $partial): void
    {
        ++$target['empresas'];

        foreach (['reglas_evaluadas', 'vendedores_procesados', 'creados', 'actualizados', 'omitidos_monto', 'protegidos'] as $key) {
            $target[$key] += $partial[$key] ?? 0;
        }
    }
}
