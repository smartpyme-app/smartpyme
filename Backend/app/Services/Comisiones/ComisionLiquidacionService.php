<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionLiquidacion;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
use App\Models\Comisiones\ComisionRegla;
use App\Models\EmpresaConfiguracionPlanilla;
use App\Models\User;
use App\Services\Comisiones\Calculators\ComisionCalculatorFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComisionLiquidacionService
{
    private const ORIGENES_FUERA_TOTAL_COMISION = [
        ComisionMovimiento::ORIGEN_SALARIO_BASE,
        ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO,
    ];

    private ComisionCalculatorFactory $factory;

    private ComisionReglaScope $reglaScope;

    private ComisionVentasPeriodo $ventasPeriodo;

    public function __construct(
        ?ComisionCalculatorFactory $factory = null,
        ?ComisionReglaScope $reglaScope = null,
        ?ComisionVentasPeriodo $ventasPeriodo = null,
    ) {
        $this->factory = $factory ?? new ComisionCalculatorFactory(new ComisionPorcentajeResolver());
        $this->reglaScope = $reglaScope ?? new ComisionReglaScope();
        $this->ventasPeriodo = $ventasPeriodo ?? new ComisionVentasPeriodo();
    }

    /** @return EloquentCollection<int, ComisionPeriodo> */
    public function listarPeriodos(int $idEmpresa, ?string $estado = null): EloquentCollection
    {
        $query = ComisionPeriodo::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['liquidaciones.vendedor'])
            ->orderByDesc('fecha_inicio');

        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        return $query->get();
    }

    public function obtenerPeriodo(int $idEmpresa, int $idPeriodo): ComisionPeriodo
    {
        return ComisionPeriodo::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['liquidaciones.vendedor'])
            ->findOrFail($idPeriodo);
    }

    public function cerrarPeriodo(int $idEmpresa, int $idPeriodo): ComisionPeriodo
    {
        return DB::transaction(function () use ($idEmpresa, $idPeriodo) {
            $periodo = ComisionPeriodo::query()
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->findOrFail($idPeriodo);

            if ($periodo->estado !== ComisionPeriodo::ESTADO_ABIERTO) {
                throw ValidationException::withMessages([
                    'periodo' => ['Solo se pueden cerrar períodos en estado abierto.'],
                ]);
            }

            $reglas = ComisionRegla::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('activo', true)
                ->get()
                ->all();

            $minimo = $this->salarioMinimoEmpresa($idEmpresa);
            $inicio = $periodo->fecha_inicio->toDateString();
            $fin = $periodo->fecha_fin->toDateString();

            foreach ($this->idsVendedoresCierre($idEmpresa, $periodo, $reglas) as $idVendedor) {
                $this->persistirCierreVendedor(
                    $idEmpresa,
                    $periodo,
                    $idVendedor,
                    $reglas,
                    $inicio,
                    $fin,
                    $minimo
                );
            }

            $periodo->update(['estado' => ComisionPeriodo::ESTADO_CERRADO]);

            return $periodo->fresh(['liquidaciones.vendedor']);
        });
    }

    public function recalcularParaVendedorPeriodo(int $idEmpresa, int $idPeriodo, int $idVendedor): void
    {
        $periodo = ComisionPeriodo::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->find($idPeriodo);

        if ($periodo === null || $periodo->estado !== ComisionPeriodo::ESTADO_CERRADO) {
            return;
        }

        $total = $this->sumarTotalComision($idEmpresa, $idPeriodo, $idVendedor);
        $existente = ComisionLiquidacion::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_periodo', $idPeriodo)
            ->where('id_vendedor', $idVendedor)
            ->first();
        $salarioBase = (float) ($existente->salario_base ?? 0);
        $ajuste = (float) ($existente->ajuste_salario_minimo ?? 0);

        ComisionLiquidacion::withoutGlobalScope('empresa')->updateOrCreate(
            [
                'id_empresa' => $idEmpresa,
                'id_periodo' => $idPeriodo,
                'id_vendedor' => $idVendedor,
            ],
            [
                'total_comision' => $total,
                'total_a_pagar' => round($total + $salarioBase + $ajuste, 4),
            ]
        );
    }

    public function marcarLiquidacionPagada(int $idEmpresa, int $idLiquidacion): ComisionLiquidacion
    {
        return DB::transaction(function () use ($idEmpresa, $idLiquidacion) {
            $liquidacion = ComisionLiquidacion::query()
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->findOrFail($idLiquidacion);

            if ($liquidacion->pagado_at !== null) {
                throw ValidationException::withMessages([
                    'liquidacion' => ['La liquidación ya está marcada como pagada.'],
                ]);
            }

            $liquidacion->update(['pagado_at' => Carbon::now()]);

            $periodo = ComisionPeriodo::query()
                ->where('id_empresa', $idEmpresa)
                ->lockForUpdate()
                ->findOrFail($liquidacion->id_periodo);

            $pendientes = ComisionLiquidacion::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_periodo', $periodo->id)
                ->whereNull('pagado_at')
                ->exists();

            if (! $pendientes && $periodo->estado === ComisionPeriodo::ESTADO_CERRADO) {
                $periodo->update(['estado' => ComisionPeriodo::ESTADO_PAGADO]);
            }

            return $liquidacion->fresh(['vendedor', 'periodo']);
        });
    }

    /**
     * @param  list<object>  $reglas
     * @return list<int>
     */
    private function idsVendedoresCierre(int $idEmpresa, ComisionPeriodo $periodo, array $reglas): array
    {
        $idsMovimientos = ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_periodo', $periodo->id)
            ->distinct()
            ->pluck('id_vendedor')
            ->map(fn ($id) => (int) $id)
            ->all();

        $idsVendedoresEmpresa = [];
        foreach ($reglas as $regla) {
            if (
                ComisionVendedoresCierre::esReglaPeriodoOBase($regla)
                && ($regla->alcance ?? ComisionRegla::ALCANCE_GLOBAL) === ComisionRegla::ALCANCE_GLOBAL
            ) {
                $idsVendedoresEmpresa = $this->idsVendedoresEmpresa($idEmpresa);
                break;
            }
        }

        return ComisionVendedoresCierre::unir($idsMovimientos, $reglas, $idsVendedoresEmpresa);
    }

    /** @return list<int> */
    private function idsVendedoresEmpresa(int $idEmpresa): array
    {
        return User::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->whereIn('tipo', ['Ventas', 'Ventas Limitado'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<object>  $reglas
     */
    private function persistirCierreVendedor(
        int $idEmpresa,
        ComisionPeriodo $periodo,
        int $idVendedor,
        array $reglas,
        string $inicio,
        string $fin,
        ?float $minimo,
    ): void {
        $aplicables = $this->reglaScope->aplicables($reglas, $idVendedor);
        $ventas = null;

        foreach ($aplicables as $regla) {
            if (($regla->tipo_calculo ?? '') === ComisionRegla::TIPO_POR_VOLUMEN) {
                $ventas ??= $this->ventasPeriodo->total($idEmpresa, $idVendedor, $inicio, $fin);
                foreach ($this->factory->for(ComisionRegla::TIPO_POR_VOLUMEN)->calcularEnCierre((object) [
                    'id_empresa' => $idEmpresa,
                    'id_vendedor' => $idVendedor,
                    'ventas' => $ventas,
                    'regla' => $regla,
                ]) as $resultado) {
                    if ($resultado->montoComision == 0.0) {
                        continue;
                    }
                    $this->persistirMovimientoPeriodo(
                        $idEmpresa,
                        (int) $periodo->id,
                        $idVendedor,
                        ComisionMovimiento::ORIGEN_AJUSTE_PERIODO,
                        isset($regla->id) ? (int) $regla->id : null,
                        $resultado->montoBase,
                        $resultado->porcentaje,
                        $resultado->montoComision,
                        $periodo->fecha_fin
                    );
                }
            }

            $salarioBaseRegla = (float) ($regla->config['salario_base'] ?? 0);
            if ($salarioBaseRegla > 0) {
                $this->persistirMovimientoPeriodo(
                    $idEmpresa,
                    (int) $periodo->id,
                    $idVendedor,
                    ComisionMovimiento::ORIGEN_SALARIO_BASE,
                    isset($regla->id) ? (int) $regla->id : null,
                    $salarioBaseRegla,
                    0.0,
                    $salarioBaseRegla,
                    $periodo->fecha_fin
                );
            }
        }

        $totalComision = $this->sumarTotalComision($idEmpresa, (int) $periodo->id, $idVendedor);
        $salarioBase = $this->sumarOrigen(
            $idEmpresa,
            (int) $periodo->id,
            $idVendedor,
            ComisionMovimiento::ORIGEN_SALARIO_BASE
        );
        $ajuste = ComisionSalarioMinimo::ajuste($totalComision + $salarioBase, $minimo);
        if ($ajuste > 0) {
            $this->persistirMovimientoPeriodo(
                $idEmpresa,
                (int) $periodo->id,
                $idVendedor,
                ComisionMovimiento::ORIGEN_AJUSTE_SALARIO_MINIMO,
                null,
                $minimo ?? 0.0,
                0.0,
                $ajuste,
                $periodo->fecha_fin
            );
        }

        ComisionLiquidacion::withoutGlobalScope('empresa')->updateOrCreate(
            [
                'id_empresa' => $idEmpresa,
                'id_periodo' => $periodo->id,
                'id_vendedor' => $idVendedor,
            ],
            [
                'total_comision' => $totalComision,
                'salario_base' => round($salarioBase, 4),
                'ajuste_salario_minimo' => round($ajuste, 4),
                'salario_minimo_aplicado' => $minimo,
                'total_a_pagar' => round($totalComision + $salarioBase + $ajuste, 4),
            ]
        );
    }

    private function persistirMovimientoPeriodo(
        int $idEmpresa,
        int $idPeriodo,
        int $idVendedor,
        string $origen,
        ?int $idRegla,
        float $montoBase,
        float $porcentaje,
        float $monto,
        mixed $fechaEvento,
    ): void {
        ComisionMovimiento::withoutGlobalScope('empresa')->updateOrCreate(
            [
                'id_empresa' => $idEmpresa,
                'origen' => $origen,
                'id_periodo' => $idPeriodo,
                'id_vendedor' => $idVendedor,
                'id_regla' => $idRegla,
            ],
            [
                'monto_base' => round($montoBase, 4),
                'porcentaje_aplicado' => round($porcentaje, 4),
                'monto_comision' => round($monto, 4),
                'fecha_evento' => $fechaEvento,
            ]
        );
    }

    private function sumarTotalComision(int $idEmpresa, int $idPeriodo, int $idVendedor): float
    {
        return round((float) ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_periodo', $idPeriodo)
            ->where('id_vendedor', $idVendedor)
            ->whereNotIn('origen', self::ORIGENES_FUERA_TOTAL_COMISION)
            ->sum('monto_comision'), 4);
    }

    private function sumarOrigen(int $idEmpresa, int $idPeriodo, int $idVendedor, string $origen): float
    {
        return (float) ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_periodo', $idPeriodo)
            ->where('id_vendedor', $idVendedor)
            ->where('origen', $origen)
            ->sum('monto_comision');
    }

    private function salarioMinimoEmpresa(int $idEmpresa): ?float
    {
        return ComisionSalarioMinimo::minimoDePlanilla(
            EmpresaConfiguracionPlanilla::obtenerConfiguracion($idEmpresa)
        );
    }
}
