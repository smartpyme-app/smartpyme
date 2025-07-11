<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\SaldoMensual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SimulacionCierreService
{
    /**
     * Simular cierre de mes sin modificar datos
     */
    public function simularCierreMes($year, $month, $empresa_id)
    {
        try {
            Log::info("Iniciando simulación de cierre", [
                'year' => $year,
                'month' => $month,
                'empresa_id' => $empresa_id
            ]);

            // 1. Ejecutar todas las validaciones
            $validaciones = $this->ejecutarValidaciones($year, $month, $empresa_id);
            Log::info("Validaciones completadas", $validaciones);

            // 2. Simular cálculos de saldos
            $saldosSimulados = $this->simularCalculoSaldos($year, $month, $empresa_id);
            Log::info("Saldos simulados calculados", ['count' => count($saldosSimulados)]);

            // 3. Simular balance de comprobación
            $balanceSimulado = $this->simularBalanceComprobacion($saldosSimulados);
            Log::info("Balance simulado", $balanceSimulado);

            // 4. Generar reporte de impacto
            $reporteImpacto = $this->generarReporteImpacto($saldosSimulados, $year, $month, $empresa_id);

            // 5. Calcular métricas de la simulación
            $metricas = $this->calcularMetricas($saldosSimulados, $validaciones);

            Log::info("Simulación completada exitosamente");

            return [
                'simulacion_exitosa' => true,
                'periodo' => "{$month}/{$year}",
                'fecha_simulacion' => now(),
                'validaciones' => $validaciones,
                'saldos_proyectados' => $saldosSimulados,
                'balance_proyectado' => $balanceSimulado,
                'reporte_impacto' => $reporteImpacto,
                'metricas' => $metricas,
                'advertencias' => $this->generarAdvertencias($validaciones, $balanceSimulado),
                'recomendaciones' => $this->generarRecomendaciones($validaciones, $balanceSimulado),
            ];

        } catch (Exception $e) {
            Log::error("Error en simulación de cierre", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'year' => $year,
                'month' => $month,
                'empresa_id' => $empresa_id
            ]);

            return [
                'simulacion_exitosa' => false,
                'error' => $e->getMessage(),
                'periodo' => "{$month}/{$year}",
                'fecha_simulacion' => now(),
            ];
        }
    }

    /**
     * Ejecutar todas las validaciones previas
     */
    private function ejecutarValidaciones($year, $month, $empresa_id)
    {
        $validaciones = [
            'periodo_anterior_cerrado' => false,
            'partidas_pendientes' => 0,
            'partidas_aplicadas' => 0,
            'total_partidas' => 0,
            'balance_cuadra' => false,
            'diferencia_balance' => 0,
            'cuentas_sin_movimiento' => 0,
            'cuentas_con_movimiento' => 0,
        ];

        // Validar período anterior
        $periodoAnterior = $this->obtenerPeriodoAnterior($year, $month);
        $saldoAnterior = SaldoMensual::where('year', $periodoAnterior['year'])
            ->where('month', $periodoAnterior['month'])
            ->where('id_empresa', $empresa_id)
            ->where('estado', 'Abierto')
            ->exists();

        $validaciones['periodo_anterior_cerrado'] = !$saldoAnterior;

        // Contar partidas
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $partidasPendientes = Partida::where('id_empresa', $empresa_id)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->where('estado', 'Pendiente')
            ->count();

        $partidasAplicadas = Partida::where('id_empresa', $empresa_id)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->where('estado', 'Aplicada')
            ->count();

        $totalPartidas = Partida::where('id_empresa', $empresa_id)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->count();

        $validaciones['partidas_pendientes'] = $partidasPendientes;
        $validaciones['partidas_aplicadas'] = $partidasAplicadas;
        $validaciones['total_partidas'] = $totalPartidas;

        // Simular balance correctamente
        $saldosTemp = $this->simularCalculoSaldos($year, $month, $empresa_id);
        $balanceSimulado = $this->simularBalanceComprobacion($saldosTemp);
        $diferencia = abs($balanceSimulado['diferencia']);

        $validaciones['balance_cuadra'] = $balanceSimulado['cuadra'];
        $validaciones['diferencia_balance'] = $diferencia;

        // Contar cuentas
        $saldosCollection = collect($saldosTemp);
        $validaciones['cuentas_sin_movimiento'] = $saldosCollection
            ->filter(function($item) {
                return (float)$item['debe'] == 0 && (float)$item['haber'] == 0;
            })
            ->count();

        $validaciones['cuentas_con_movimiento'] = $saldosCollection
            ->filter(function($item) {
                return (float)$item['debe'] > 0 || (float)$item['haber'] > 0;
            })
            ->count();

        return $validaciones;
    }

    /**
     * Simular cálculo de saldos sin modificar la BD
     */
    private function simularCalculoSaldos($year, $month, $empresa_id)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Obtener todas las cuentas
        $cuentas = Cuenta::where('id_empresa', $empresa_id)->get();

        // Obtener movimientos del período
        $movimientos = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->where('partidas.estado', 'Aplicada')
            ->whereBetween('partidas.fecha', [$startDate, $endDate])
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta');

        // Obtener saldos iniciales
        $saldosIniciales = $this->obtenerSaldosIniciales($year, $month, $empresa_id);

        $saldosSimulados = [];
        foreach ($cuentas as $cuenta) {
            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial;
            $movimiento = $movimientos->get($cuenta->id);
            $debe = $movimiento ? (float)$movimiento->total_debe : 0;
            $haber = $movimiento ? (float)$movimiento->total_haber : 0;

            // Convertir a float para asegurar tipos numéricos
            $saldoInicial = (float)$saldoInicial;
            $debe = (float)$debe;
            $haber = (float)$haber;

            // Calcular saldo final según naturaleza
            if ($cuenta->naturaleza == 'Deudor') {
                $saldoFinal = $saldoInicial + $debe - $haber;
            } else {
                $saldoFinal = $saldoInicial - $debe + $haber;
            }

            $variacion = $saldoFinal - $saldoInicial;
            $porcentajeVariacion = $saldoInicial != 0 ? ($variacion / $saldoInicial) * 100 : 0;

            $saldosSimulados[] = [
                'id_cuenta' => $cuenta->id,
                'codigo_cuenta' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'naturaleza' => $cuenta->naturaleza,
                'saldo_inicial' => $saldoInicial,
                'debe' => $debe,
                'haber' => $haber,
                'saldo_final' => $saldoFinal,
                'variacion' => $variacion,
                'porcentaje_variacion' => $porcentajeVariacion,
            ];
        }

        return $saldosSimulados;
    }

    /**
     * Simular balance de comprobación
     */
    private function simularBalanceComprobacion($saldosSimulados)
    {
        // ✅ CORRECCIÓN: Sumar movimientos debe/haber del período
        $totalDebe = collect($saldosSimulados)->sum('debe');
        $totalHaber = collect($saldosSimulados)->sum('haber');
        $diferencia = $totalDebe - $totalHaber;

        // También calcular totales por naturaleza para validación adicional
        $totalDeudor = collect($saldosSimulados)->where('naturaleza', 'Deudor')->sum('saldo_final');
        $totalAcreedor = collect($saldosSimulados)->where('naturaleza', 'Acreedor')->sum('saldo_final');
        $diferenciaSaldos = $totalDeudor - $totalAcreedor;

        return [
            // Totales de movimientos del período (lo que realmente importa para el balance)
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'diferencia' => $diferencia,
            'cuadra' => abs($diferencia) < 0.01,
            'porcentaje_error' => $totalDebe != 0 ? abs($diferencia / $totalDebe) * 100 : 0,

            // Totales por naturaleza de cuentas (para validación adicional)
            'total_deudor' => $totalDeudor,
            'total_acreedor' => $totalAcreedor,
            'diferencia_saldos' => $diferenciaSaldos,
            'cuadra_saldos' => abs($diferenciaSaldos) < 0.01,
        ];
    }

    /**
     * Generar reporte de impacto
     */
    private function generarReporteImpacto($saldosSimulados, $year, $month, $empresa_id)
    {
        $saldosCollection = collect($saldosSimulados);

        // Filtrar cuentas con cambio significativo
        $cuentasConCambioSignificativo = $saldosCollection
            ->filter(function($saldo) {
                return abs((float)$saldo['porcentaje_variacion']) > 10;
            })
            ->sortByDesc('porcentaje_variacion')
            ->take(10)
            ->values();

        // Cuentas nuevas con saldo
        $cuentasNuevasSinSaldo = $saldosCollection
            ->filter(function($saldo) {
                return (float)$saldo['saldo_inicial'] == 0 && (float)$saldo['saldo_final'] > 0;
            })
            ->sortByDesc('saldo_final')
            ->take(5)
            ->values();

        // Cuentas que se liquidarían
        $cuentasQueSeLiquidarian = $saldosCollection
            ->filter(function($saldo) {
                return (float)$saldo['saldo_inicial'] > 0 && (float)$saldo['saldo_final'] == 0;
            })
            ->sortByDesc('saldo_inicial')
            ->take(5)
            ->values();

        // Calcular totales
        $totalCuentasAfectadas = $saldosCollection
            ->filter(function($saldo) {
                return (float)$saldo['variacion'] != 0;
            })
            ->count();

        $montoTotalMovimientos = $saldosCollection
            ->sum(function($saldo) {
                return (float)$saldo['debe'] + (float)$saldo['haber'];
            });

        return [
            'cuentas_cambio_significativo' => $cuentasConCambioSignificativo->toArray(),
            'cuentas_nuevas_con_saldo' => $cuentasNuevasSinSaldo->toArray(),
            'cuentas_que_se_liquidarian' => $cuentasQueSeLiquidarian->toArray(),
            'total_cuentas_afectadas' => $totalCuentasAfectadas,
            'monto_total_movimientos' => $montoTotalMovimientos,
        ];
    }

    /**
     * Calcular métricas de la simulación
     */
    private function calcularMetricas($saldosSimulados, $validaciones)
    {
        $saldosCollection = collect($saldosSimulados);

        $cuentasActivas = $saldosCollection
            ->filter(function($saldo) {
                return (float)$saldo['debe'] > 0 || (float)$saldo['haber'] > 0;
            })
            ->count();

        return [
            'tiempo_estimado_cierre' => $this->estimarTiempoCierre($validaciones),
            'nivel_riesgo' => $this->calcularNivelRiesgo($validaciones),
            'puntuacion_calidad' => $this->calcularPuntuacionCalidad($validaciones),
            'cuentas_totales' => count($saldosSimulados),
            'cuentas_activas' => $cuentasActivas,
        ];
    }

    /**
     * Generar advertencias
     */
    private function generarAdvertencias($validaciones, $balance)
    {
        $advertencias = [];

        if (!$validaciones['periodo_anterior_cerrado']) {
            $advertencias[] = [
                'tipo' => 'error',
                'mensaje' => 'El período anterior no está cerrado',
                'accion' => 'Debe cerrar primero el período anterior'
            ];
        }

        if ($validaciones['partidas_pendientes'] > 0) {
            $advertencias[] = [
                'tipo' => 'warning',
                'mensaje' => "{$validaciones['partidas_pendientes']} partidas pendientes de aplicar",
                'accion' => 'Aplique todas las partidas antes del cierre'
            ];
        }

        if (!$balance['cuadra']) {
            $advertencias[] = [
                'tipo' => 'error',
                'mensaje' => "Balance descuadrado por " . number_format($balance['diferencia'], 2),
                'accion' => 'Revise las partidas que causan el descuadre'
            ];
        }

        if ($validaciones['cuentas_sin_movimiento'] > ($validaciones['cuentas_con_movimiento'] * 0.8)) {
            $advertencias[] = [
                'tipo' => 'info',
                'mensaje' => 'Muchas cuentas sin movimiento en el período',
                'accion' => 'Revise si todas las transacciones están registradas'
            ];
        }

        return $advertencias;
    }

    /**
     * Generar recomendaciones
     */
    private function generarRecomendaciones($validaciones, $balance)
    {
        $recomendaciones = [];

        if ($validaciones['balance_cuadra'] && $validaciones['partidas_pendientes'] == 0) {
            $recomendaciones[] = 'El período está listo para cierre - todas las validaciones pasaron';
        }

        if ($balance['porcentaje_error'] < 0.1) {
            $recomendaciones[] = 'El balance tiene una precisión excelente';
        }

        $recomendaciones[] = 'Genere un respaldo de la base de datos antes del cierre';
        $recomendaciones[] = 'Descargue todos los reportes necesarios del período';

        return $recomendaciones;
    }

    // Métodos auxiliares
    private function obtenerPeriodoAnterior($year, $month)
    {
        if ($month == 1) {
            return ['year' => $year - 1, 'month' => 12];
        }
        return ['year' => $year, 'month' => $month - 1];
    }

    private function obtenerSaldosIniciales($year, $month, $empresa_id)
    {
        $periodoAnterior = $this->obtenerPeriodoAnterior($year, $month);

        $saldosAnteriores = SaldoMensual::where('year', $periodoAnterior['year'])
            ->where('month', $periodoAnterior['month'])
            ->where('id_empresa', $empresa_id)
            ->get()
            ->keyBy('id_cuenta');

        $saldosIniciales = [];
        foreach ($saldosAnteriores as $saldo) {
            $saldosIniciales[$saldo->id_cuenta] = $saldo->saldo_final;
        }

        return $saldosIniciales;
    }

    private function estimarTiempoCierre($validaciones)
    {
        $tiempoBase = 30; // segundos base
        $tiempoExtra = $validaciones['partidas_pendientes'] * 5; // 5 seg por partida pendiente

        return $tiempoBase + $tiempoExtra;
    }

    private function calcularNivelRiesgo($validaciones)
    {
        $riesgo = 'bajo';

        if (!$validaciones['periodo_anterior_cerrado'] || !$validaciones['balance_cuadra']) {
            $riesgo = 'alto';
        } elseif ($validaciones['partidas_pendientes'] > 5 || $validaciones['diferencia_balance'] > 1) {
            $riesgo = 'medio';
        }

        return $riesgo;
    }

    private function calcularPuntuacionCalidad($validaciones)
    {
        $puntuacion = 100;

        if (!$validaciones['periodo_anterior_cerrado']) $puntuacion -= 30;
        if (!$validaciones['balance_cuadra']) $puntuacion -= 25;
        if ($validaciones['partidas_pendientes'] > 0) $puntuacion -= ($validaciones['partidas_pendientes'] * 2);

        return max(0, $puntuacion);
    }
}
