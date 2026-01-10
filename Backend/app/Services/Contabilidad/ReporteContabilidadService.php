<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use App\Models\Contabilidad\SaldoMensual;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteContabilidadService
{
    /**
     * Ordena las cuentas jerárquicamente en un array plano (padre seguido de sus hijos)
     *
     * @param \Illuminate\Support\Collection $cuentas
     * @param int|null $padreId
     * @param int $nivel
     * @return array
     */
    public function ordenarJerarquicamente($cuentas, $padreId = null, $nivel = 0): array
    {
        $resultado = [];
        foreach ($cuentas as $cuenta) {
            if (
                ($padreId === null && ($cuenta->id_cuenta_padre === null || $cuenta->id_cuenta_padre == 0)) ||
                ($cuenta->id_cuenta_padre == $padreId && $padreId !== null)
            ) {
                $cuenta->nivel_visual = $nivel;
                $resultado[] = $cuenta;
                $hijos = $this->ordenarJerarquicamente($cuentas, $cuenta->id, $nivel + 1);
                foreach ($hijos as $hijo) {
                    $resultado[] = $hijo;
                }
            }
        }
        return $resultado;
    }

    /**
     * Obtener saldos iniciales del período
     *
     * @param int $year
     * @param int $month
     * @param int $empresa_id
     * @return array Array con saldos iniciales indexados por id_cuenta
     */
    public function obtenerSaldosIniciales(int $year, int $month, int $empresa_id): array
    {
        // Si es enero del primer año, usar catálogo
        if ($month == 1) {
            // Verificar si existe algún período anterior en cualquier año
            $hayPeriodoAnterior = SaldoMensual::where('id_empresa', $empresa_id)
                ->where(function($q) use ($year, $month) {
                    $q->where('year', '<', $year)
                      ->orWhere(function($q2) use ($year, $month) {
                          $q2->where('year', $year)->where('month', '<', $month);
                      });
                })
                ->exists();

            if (!$hayPeriodoAnterior) {
                // Primer período de la empresa - usar catálogo
                return [];
            }
        }

        // Obtener período anterior
        $periodoAnterior = $this->obtenerPeriodoAnterior($year, $month);

        $saldosAnteriores = SaldoMensual::where('year', $periodoAnterior['year'])
            ->where('month', $periodoAnterior['month'])
            ->where('id_empresa', $empresa_id)
            ->get()
            ->keyBy('id_cuenta');

        $saldosIniciales = [];
        foreach ($saldosAnteriores as $saldo) {
            // Asegurar que el saldo final nunca sea null
            $saldosIniciales[$saldo->id_cuenta] = (float)($saldo->saldo_final ?? 0);
        }

        return $saldosIniciales;
    }

    /**
     * Obtener período anterior
     *
     * @param int $year
     * @param int $month
     * @return array Array con 'year' y 'month'
     */
    public function obtenerPeriodoAnterior(int $year, int $month): array
    {
        if ($month == 1) {
            return ['year' => $year - 1, 'month' => 12];
        }
        return ['year' => $year, 'month' => $month - 1];
    }

    /**
     * Calcula el saldo final según la naturaleza de la cuenta
     *
     * @param float $saldo_inicial
     * @param float $debe
     * @param float $haber
     * @param string $naturaleza 'Deudor' o 'Acreedor'
     * @return float
     */
    public function calcularSaldoFinal(float $saldo_inicial, float $debe, float $haber, string $naturaleza): float
    {
        if ($naturaleza == 'Deudor') {
            return $saldo_inicial + $debe - $haber;
        } else {
            return $saldo_inicial + $haber - $debe;
        }
    }

    /**
     * Calcula las operaciones del mes según la naturaleza de la cuenta
     *
     * @param float $debe
     * @param float $haber
     * @param string $naturaleza 'Deudor' o 'Acreedor'
     * @return float
     */
    public function calcularOperacionesMes(float $debe, float $haber, string $naturaleza): float
    {
        if ($naturaleza == 'Deudor') {
            return $debe - $haber;
        } else {
            return $haber - $debe;
        }
    }

    /**
     * Obtiene los movimientos de partidas para un rango de fechas
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $empresa_id
     * @return \Illuminate\Support\Collection Collection indexada por id_cuenta con total_debe y total_haber
     */
    public function obtenerMovimientosPartidas(Carbon $startDate, Carbon $endDate, int $empresa_id)
    {
        return Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresa_id)
            ->whereIn('partidas.estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('partidas.fecha', [$startDate, $endDate])
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta');
    }

    /**
     * Consolidar saldos hacia cuentas padre
     *
     * @param \Illuminate\Support\Collection $cuentas
     * @param array $cuentas_saldos Array indexado por código de cuenta
     * @param array $idACodigo Mapa de ID a código
     * @return void Modifica $cuentas_saldos por referencia
     */
    public function consolidarSaldosHaciaPadre($cuentas, array &$cuentas_saldos, array $idACodigo): void
    {
        foreach ($cuentas->sortByDesc('nivel') as $cuenta) {
            if($cuenta->id_cuenta_padre && isset($idACodigo[$cuenta->id_cuenta_padre])) {
                $codigo_padre = $idACodigo[$cuenta->id_cuenta_padre];
                if (isset($cuentas_saldos[$codigo_padre]) && isset($cuentas_saldos[$cuenta->codigo])) {
                    $cuentas_saldos[$codigo_padre]['saldo_inicial'] += $cuentas_saldos[$cuenta->codigo]['saldo_inicial'] ?? 0;
                    $cuentas_saldos[$codigo_padre]['debe'] += $cuentas_saldos[$cuenta->codigo]['debe'] ?? 0;
                    $cuentas_saldos[$codigo_padre]['haber'] += $cuentas_saldos[$cuenta->codigo]['haber'] ?? 0;
                    if (isset($cuentas_saldos[$codigo_padre]['saldoFinal'])) {
                        $cuentas_saldos[$codigo_padre]['saldoFinal'] += $cuentas_saldos[$cuenta->codigo]['saldoFinal'] ?? 0;
                    }
                }
            }
        }
    }

    /**
     * Procesa cuentas padre y genera reporte de libro mayor
     * Filtra partidas por código de cuenta padre, calcula totales y saldos progresivos
     *
     * @param \Illuminate\Support\Collection $cuentas_padre
     * @param \Illuminate\Support\Collection $partidas
     * @return array Array de objetos CuentaReporte
     */
    public function procesarCuentasPadreParaLibroMayor($cuentas_padre, $partidas): array
    {
        $cuentas = [];

        foreach ($cuentas_padre->pluck('codigo') as $cod_padre) {
            $partidasFiltradas = $partidas->filter(function ($detalle) use ($cod_padre) {
                return strpos($detalle->codigo, (string)$cod_padre) === 0;
            });

            $partidasFiltradas = $partidasFiltradas->values();

            // Calcular totales de debe y haber
            $sum_deb = 0;
            $sum_hab = 0;
            foreach ($partidasFiltradas as $det_part) {
                $sum_deb += $det_part->debe;
                $sum_hab += $det_part->haber;
            }

            if (count($partidasFiltradas) != 0) {
                $cnt = $cuentas_padre->firstWhere('codigo', $cod_padre);

                $cuenta_reporte = new \App\Models\Contabilidad\Catalogo\CuentaReporte();
                $cuenta_reporte->cuenta = $cod_padre;
                $cuenta_reporte->nombre = $cnt->nombre;
                $cuenta_reporte->naturaleza = $cnt->naturaleza;
                $cuenta_reporte->cargo = $sum_deb;
                $cuenta_reporte->abono = $sum_hab;
                $cuenta_reporte->saldo_actual = 0;
                $cuenta_reporte->saldo_anterior = 0;

                // Calcular saldos progresivos para cada detalle según naturaleza de la cuenta
                $saldo_actual = 0;
                foreach ($partidasFiltradas as $detalle) {
                    $debe_valor = (float)($detalle->debe ?? 0);
                    $haber_valor = (float)($detalle->haber ?? 0);
                    
                    // Calcular saldo según naturaleza de la cuenta
                    if ($cnt->naturaleza == 'Deudor') {
                        $saldo_actual = $saldo_actual + $debe_valor - $haber_valor;
                    } else {
                        $saldo_actual = $saldo_actual - $debe_valor + $haber_valor;
                    }
                    
                    // Agregar el saldo calculado al detalle
                    $detalle->saldo_calculado = $saldo_actual;
                }
                
                // Actualizar el saldo final de la cuenta para totales
                $cuenta_reporte->saldo_actual = $saldo_actual;
                $cuenta_reporte->detalles = $partidasFiltradas;

                $cuentas[] = $cuenta_reporte;
            }
        }

        return $cuentas;
    }

    /**
     * Clasifica cuentas por rubros del Balance General
     *
     * @param \Illuminate\Support\Collection $cuentas
     * @param array $cuentas_saldos Array indexado por código de cuenta con saldos
     * @return array Array con estructura: ['activos' => [], 'pasivos' => [], 'patrimonio' => [], 'totales' => [...]]
     */
    public function clasificarCuentasPorRubroBalanceGeneral($cuentas, array $cuentas_saldos): array
    {
        $balance_general = [
            'activos' => [],
            'pasivos' => [],
            'patrimonio' => [],
            'totales' => [
                'activos' => 0,
                'pasivos' => 0,
                'patrimonio' => 0
            ]
        ];

        foreach ($cuentas as $cuenta) {
            $codigo = $cuenta->codigo;
            $saldo_inicial = $cuentas_saldos[$codigo]['saldo_inicial'] ?? 0;
            $debe = $cuentas_saldos[$codigo]['debe'] ?? 0;
            $haber = $cuentas_saldos[$codigo]['haber'] ?? 0;

            // Calcular saldo final según naturaleza de la cuenta
            $saldo_final = $this->calcularSaldoFinal($saldo_inicial, $debe, $haber, $cuenta->naturaleza);

            $cuenta_data = [
                'codigo' => $codigo,
                'nombre' => $cuenta->nombre,
                'saldo_final' => $saldo_final,
                'naturaleza' => $cuenta->naturaleza
            ];

            // Clasificar según rubro
            $rubro = strtolower(trim($cuenta->rubro));

            if (strpos($rubro, 'activo') !== false) {
                $balance_general['activos'][] = $cuenta_data;
                $balance_general['totales']['activos'] += $saldo_final;
            } elseif (strpos($rubro, 'pasivo') !== false) {
                $balance_general['pasivos'][] = $cuenta_data;
                $balance_general['totales']['pasivos'] += $saldo_final;
            } elseif (strpos($rubro, 'capital') !== false ||
                    strpos($rubro, 'patrimonio') !== false ||
                    strpos($rubro, 'resultado') !== false) {
                $balance_general['patrimonio'][] = $cuenta_data;
                $balance_general['totales']['patrimonio'] += $saldo_final;
            }
        }

        // Verificar ecuación contable
        $balance_general['ecuacion_cuadra'] = abs($balance_general['totales']['activos'] -
            ($balance_general['totales']['pasivos'] + $balance_general['totales']['patrimonio'])) < 0.01;

        return $balance_general;
    }

    /**
     * Clasifica cuentas por rubros del Estado de Resultados
     *
     * @param \Illuminate\Support\Collection $cuentas
     * @param \Illuminate\Support\Collection $partida_detalles Movimientos indexados por id_cuenta
     * @return array Array con estructura: ['ingresos' => [], 'costos_gastos' => [], 'totales' => [...]]
     */
    public function clasificarCuentasPorRubroEstadoResultados($cuentas, $partida_detalles): array
    {
        $estado_resultados = [
            'ingresos' => [],
            'costos_gastos' => [],
            'totales' => [
                'ingresos' => 0,
                'costos_gastos' => 0,
                'utilidad_perdida' => 0
            ]
        ];

        // Procesar cada cuenta
        foreach ($cuentas as $cuenta) {
            $movimientos = $partida_detalles->get($cuenta->id);

            if (!$movimientos) {
                $debe = 0;
                $haber = 0;
            } else {
                $debe = $movimientos->total_debe ?? 0;
                $haber = $movimientos->total_haber ?? 0;
            }

            // Calcular saldo según naturaleza de la cuenta
            $saldo_final = $this->calcularOperacionesMes($debe, $haber, $cuenta->naturaleza);

            // Solo incluir cuentas con saldo diferente de cero
            if ($saldo_final != 0) {
                $cuenta_info = [
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'saldo_final' => $saldo_final,
                    'naturaleza' => $cuenta->naturaleza,
                    'rubro' => $cuenta->rubro
                ];

                // Clasificar según el rubro
                if ($cuenta->rubro === 'Ingresos') {
                    $estado_resultados['ingresos'][] = $cuenta_info;
                    $estado_resultados['totales']['ingresos'] += abs($saldo_final);
                } elseif ($cuenta->rubro === 'Costos y gastos') {
                    $estado_resultados['costos_gastos'][] = $cuenta_info;
                    $estado_resultados['totales']['costos_gastos'] += abs($saldo_final);
                }
            }
        }

        // Calcular utilidad/pérdida
        $estado_resultados['totales']['utilidad_perdida'] =
            $estado_resultados['totales']['ingresos'] - $estado_resultados['totales']['costos_gastos'];

        return $estado_resultados;
    }
}

