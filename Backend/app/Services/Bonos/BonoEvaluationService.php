<?php

namespace App\Services\Bonos;

use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\Bonos\BonoEvaluacion;
use App\Models\Bonos\BonoGenerado;
use App\Models\Bonos\BonoRegla;
use App\Services\Bonos\Calculators\BonoCalculatorFactory;
use App\Services\Bonos\Calculators\GrupalCalculator;
use App\Services\Comisiones\ComisionReglaScope;
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

    /** @var Closure(int, int, string, string): array<int> */
    private Closure $obtenerVendedoresConPendiente;

    /** @var Closure(array<string, mixed>): ?object */
    private Closure $buscarBonoGenerado;

    /** @var Closure(array<string, mixed>): object */
    private Closure $crearBonoGenerado;

    /** @var Closure(object, array<string, mixed>): void */
    private Closure $actualizarBonoGenerado;

    /** @var Closure(object): void */
    private Closure $eliminarBonoGenerado;

    /** @var Closure(array<string, mixed>): void */
    private Closure $registrarEvaluacion;

    /**
     * @param  Closure(?int): array<int>|null  $obtenerEmpresasActivas
     * @param  Closure(int): Collection<int, BonoRegla>|null  $obtenerReglasActivas
     * @param  Closure(int, string, string): array<int>|null  $obtenerVendedoresConVentas
     * @param  Closure(int, int, string, string): array<int>|null  $obtenerVendedoresConPendiente
     * @param  Closure(array<string, mixed>): ?object|null  $buscarBonoGenerado
     * @param  Closure(array<string, mixed>): object|null  $crearBonoGenerado
     * @param  Closure(object, array<string, mixed>): void|null  $actualizarBonoGenerado
     * @param  Closure(object): void|null  $eliminarBonoGenerado
     * @param  Closure(array<string, mixed>): void|null  $registrarEvaluacion
     */
    public function __construct(
        private BonoMetaCalculator $metaCalculator,
        private BonoReglaEvaluator $evaluator,
        ?Closure $obtenerEmpresasActivas = null,
        ?Closure $obtenerReglasActivas = null,
        ?Closure $obtenerVendedoresConVentas = null,
        ?Closure $obtenerVendedoresConPendiente = null,
        ?Closure $buscarBonoGenerado = null,
        ?Closure $crearBonoGenerado = null,
        ?Closure $actualizarBonoGenerado = null,
        ?Closure $eliminarBonoGenerado = null,
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
        $this->obtenerVendedoresConPendiente = $obtenerVendedoresConPendiente
            ?? fn (int $idEmpresa, int $idRegla, string $desde, string $hasta) => $this->vendedoresConPendienteEnPeriodo($idEmpresa, $idRegla, $desde, $hasta);
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
        $this->eliminarBonoGenerado = $eliminarBonoGenerado
            ?? function (object $bono): void {
                BonoGenerado::withoutGlobalScope('empresa')
                    ->where('id', $bono->id)
                    ->delete();
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
        $vendedoresConVentas = ($this->obtenerVendedoresConVentas)($idEmpresa, $desde, $hasta);
        $reglas = ($this->obtenerReglasActivas)($idEmpresa);
        $scope = new ComisionReglaScope();
        $reglasScope = [];
        foreach ($reglas as $regla) {
            if (($regla->tipo ?? '') === BonoRegla::TIPO_CUALITATIVO_MANUAL) {
                continue;
            }
            $reglasScope[] = $this->reglaParaScope($regla);
        }

        foreach ($reglas as $regla) {
            ++$resumen['reglas_evaluadas'];

            if (($regla->tipo ?? '') === BonoRegla::TIPO_CUALITATIVO_MANUAL) {
                continue;
            }

            if (($regla->tipo ?? '') === BonoRegla::TIPO_GRUPAL) {
                $this->evaluarGrupal($idEmpresa, $regla, $desde, $hasta, $scope, $reglasScope, $resumen);
                continue;
            }

            $candidatos = $this->vendedoresParaRegla($regla, $idEmpresa, $desde, $hasta, $vendedoresConVentas);

            foreach ($candidatos as $idVendedor) {
                if (! $this->reglaAplicaAVendedor($scope, $reglasScope, $regla, (int) $idVendedor)) {
                    if (BonoRegla::alcanceEfectivo($regla) === BonoRegla::ALCANCE_GLOBAL) {
                        $resultado = $this->persistirBono($idEmpresa, (int) $idVendedor, $regla, $desde, $hasta, 0.0, 0.0);
                        ++$resumen[$resultado];
                    }
                    continue;
                }
                ++$resumen['vendedores_procesados'];
                $ventas = $this->metaCalculator->ventasVendedorPeriodo($idEmpresa, (int) $idVendedor, $desde, $hasta);
                $monto = $this->evaluator->calcular($regla->tipo, $regla->config ?? [], $ventas);
                $resultado = $this->persistirBono($idEmpresa, (int) $idVendedor, $regla, $desde, $hasta, $ventas, $monto);
                ++$resumen[$resultado];
            }
        }

        return $resumen;
    }

    /**
     * @param  array<int, object>  $reglasScope
     * @param  array<string, int>  $resumen
     */
    private function evaluarGrupal(
        int $idEmpresa,
        object $regla,
        string $desde,
        string $hasta,
        ComisionReglaScope $scope,
        array $reglasScope,
        array &$resumen,
    ): void {
        $ids = array_map('intval', (array) ($regla->id_vendedores ?? []));
        $pendientes = ($this->obtenerVendedoresConPendiente)($idEmpresa, (int) $regla->id, $desde, $hasta);
        $ventasPorVendedor = [];
        foreach ($ids as $idVendedor) {
            $ventasPorVendedor[$idVendedor] = $this->metaCalculator->ventasVendedorPeriodo($idEmpresa, $idVendedor, $desde, $hasta);
        }

        $calc = (new BonoCalculatorFactory())->for('grupal');
        $reparto = $calc instanceof GrupalCalculator
            ? $calc->repartir($regla->config ?? [], $ventasPorVendedor)
            : [];

        foreach (array_values(array_unique(array_merge($ids, $pendientes))) as $idVendedor) {
            if (! $this->reglaAplicaAVendedor($scope, $reglasScope, $regla, (int) $idVendedor)) {
                continue;
            }
            ++$resumen['vendedores_procesados'];
            $ventas = $ventasPorVendedor[$idVendedor] ?? 0.0;
            $monto = $reparto[$idVendedor] ?? 0.0;
            $resultado = $this->persistirBono($idEmpresa, (int) $idVendedor, $regla, $desde, $hasta, $ventas, $monto);
            ++$resumen[$resultado];
        }
    }

    /**
     * @param  array<int>  $vendedoresConVentas
     * @return array<int>
     */
    private function vendedoresParaRegla(object $regla, int $idEmpresa, string $desde, string $hasta, array $vendedoresConVentas): array
    {
        $alcance = BonoRegla::alcanceEfectivo($regla);
        $pendientes = ($this->obtenerVendedoresConPendiente)($idEmpresa, (int) $regla->id, $desde, $hasta);

        if (in_array($alcance, [BonoRegla::ALCANCE_INDIVIDUAL, BonoRegla::ALCANCE_EQUIPO, BonoRegla::ALCANCE_VENDEDORES], true)) {
            $ids = array_map('intval', (array) ($regla->id_vendedores ?? []));

            return array_values(array_unique(array_merge($ids, $pendientes)));
        }

        return array_values(array_unique(array_merge($vendedoresConVentas, $pendientes)));
    }

    /** @param  array<int, object>  $reglasScope */
    private function reglaAplicaAVendedor(ComisionReglaScope $scope, array $reglasScope, object $regla, int $idVendedor): bool
    {
        $ids = array_map(fn ($r) => $r->id, $scope->aplicables($reglasScope, $idVendedor));

        return in_array($regla->id, $ids, true);
    }

    private function reglaParaScope(object $regla): object
    {
        return (object) [
            'id' => $regla->id,
            'alcance' => BonoRegla::alcanceEfectivo($regla),
            'id_vendedores' => $regla->id_vendedores ?? null,
            'reemplaza_global' => $regla->reemplaza_global ?? false,
        ];
    }

    /** @return 'creados'|'actualizados'|'omitidos_monto'|'protegidos'|'eliminados' */
    private function persistirBono(
        int $idEmpresa,
        int $idVendedor,
        object $regla,
        string $desde,
        string $hasta,
        float $ventas,
        float $monto,
    ): string {
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

        if ($monto <= 0) {
            if ($existing && $existing->estado === BonoGenerado::ESTADO_PENDIENTE) {
                ($this->eliminarBonoGenerado)($existing);

                return 'eliminados';
            }

            return 'omitidos_monto';
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

        ($this->crearBonoGenerado)(array_merge($unique, $values, [
            'origen' => BonoGenerado::ORIGEN_EVALUACION,
        ]));

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

    /** @return array<int> */
    private function vendedoresConPendienteEnPeriodo(int $idEmpresa, int $idRegla, string $desde, string $hasta): array
    {
        return BonoGenerado::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_regla', $idRegla)
            ->where('periodo_inicio', $desde)
            ->where('periodo_fin', $hasta)
            ->where('estado', BonoGenerado::ESTADO_PENDIENTE)
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
            'eliminados' => 0,
        ];
    }

    /** @param  array<string, int>  $target
     * @param  array<string, int>  $partial */
    private function mergeResumen(array &$target, array $partial): void
    {
        ++$target['empresas'];

        foreach (['reglas_evaluadas', 'vendedores_procesados', 'creados', 'actualizados', 'omitidos_monto', 'protegidos', 'eliminados'] as $key) {
            $target[$key] += $partial[$key] ?? 0;
        }
    }
}
