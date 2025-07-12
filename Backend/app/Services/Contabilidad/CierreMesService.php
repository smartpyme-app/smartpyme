<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\SaldoMensual;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\Contabilidad\Partidas\Detalle;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class CierreMesService
{
    /**
     * Realizar cierre de mes completo
     */
    public function cerrarMes($year, $month, $usuario_id, $empresa_id)
    {
        DB::beginTransaction();

        try {
            // 1. Validar que el período anterior esté cerrado
            $this->validarPeriodoAnterior($year, $month, $empresa_id);

            // 2. Validar que todas las partidas del período estén aplicadas
            $this->validarPartidasAplicadas($year, $month, $empresa_id);

            // 3. Calcular saldos del período
            $saldos = $this->calcularSaldosPeriodo($year, $month, $empresa_id);

            // 4. Validar cuadre del balance ANTES de guardar
            $this->validarCuadreBalance($saldos);

            // 5. Guardar saldos mensuales
            $this->guardarSaldosMensuales($saldos, $year, $month, $usuario_id, $empresa_id);

            // 6. Cerrar partidas del período
            $this->cerrarPartidasPeriodo($year, $month, $empresa_id);

            // 7. Actualizar saldos iniciales del siguiente período
            $this->actualizarSaldosInicialesSiguientePeriodo($year, $month, $empresa_id);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Cierre de mes realizado exitosamente',
                'periodo' => "{$month}/{$year}",
                'cuentas_procesadas' => count($saldos),
                'fecha_cierre' => now(),
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Validar que el período anterior esté cerrado
     */
    private function validarPeriodoAnterior($year, $month, $empresa_id)
    {
        $periodoAnterior = $this->obtenerPeriodoAnterior($year, $month);

        $saldoAnterior = SaldoMensual::where('year', $periodoAnterior['year'])
            ->where('month', $periodoAnterior['month'])
            ->where('id_empresa', $empresa_id)
            ->where('estado', 'Abierto')
            ->first();

        if ($saldoAnterior) {
            throw new Exception("Debe cerrar primero el período {$periodoAnterior['month']}/{$periodoAnterior['year']}");
        }
    }

    /**
     * Validar que todas las partidas estén aplicadas
     */
    private function validarPartidasAplicadas($year, $month, $empresa_id)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $partidasPendientes = Partida::where('id_empresa', $empresa_id)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->where('estado', 'Pendiente')
            ->count();

        if ($partidasPendientes > 0) {
            throw new Exception("Existen {$partidasPendientes} partidas pendientes por aplicar en el período {$month}/{$year}");
        }
    }

    /**
     * Calcular saldos del período
     */
    private function calcularSaldosPeriodo($year, $month, $empresa_id)
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

        // Obtener saldos iniciales del período
        $saldosIniciales = $this->obtenerSaldosIniciales($year, $month, $empresa_id);

        $saldos = [];
        foreach ($cuentas as $cuenta) {
            // Asegurar que saldo_inicial nunca sea null
            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;
            $debe = $movimientos[$cuenta->id]->total_debe ?? 0;
            $haber = $movimientos[$cuenta->id]->total_haber ?? 0;

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

            $saldos[] = [
                'id_cuenta' => $cuenta->id,
                'codigo_cuenta' => $cuenta->codigo,
                'nombre_cuenta' => $cuenta->nombre,
                'naturaleza' => $cuenta->naturaleza,
                'saldo_inicial' => $saldoInicial,
                'debe' => $debe,
                'haber' => $haber,
                'saldo_final' => $saldoFinal,
            ];
        }

        return $saldos;
    }

    /**
     * Obtener saldos iniciales del período
     */
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
            // Asegurar que el saldo final nunca sea null
            $saldosIniciales[$saldo->id_cuenta] = (float)($saldo->saldo_final ?? 0);
        }

        return $saldosIniciales;
    }

    /**
     * Guardar saldos mensuales
     */
    private function guardarSaldosMensuales($saldos, $year, $month, $usuario_id, $empresa_id)
    {
        foreach ($saldos as $saldo) {
            SaldoMensual::updateOrCreate(
                [
                    'id_cuenta' => $saldo['id_cuenta'],
                    'year' => $year,
                    'month' => $month,
                    'id_empresa' => $empresa_id,
                ],
                [
                    'codigo_cuenta' => $saldo['codigo_cuenta'],
                    'nombre_cuenta' => $saldo['nombre_cuenta'],
                    'naturaleza' => $saldo['naturaleza'],
                    'saldo_inicial' => $saldo['saldo_inicial'],
                    'debe' => $saldo['debe'],
                    'haber' => $saldo['haber'],
                    'saldo_final' => $saldo['saldo_final'],
                    'estado' => 'Cerrado',
                    'id_usuario_cierre' => $usuario_id,
                    'fecha_cierre' => now(),
                ]
            );
        }
    }

    /**
     * Cerrar partidas del período
     */
    private function cerrarPartidasPeriodo($year, $month, $empresa_id)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        Partida::where('id_empresa', $empresa_id)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->where('estado', 'Aplicada')
            ->update(['estado' => 'Cerrada']);
    }

    /**
     * Actualizar saldos iniciales del siguiente período
     */
    private function actualizarSaldosInicialesSiguientePeriodo($year, $month, $empresa_id)
    {
        $siguientePeriodo = $this->obtenerSiguientePeriodo($year, $month);

        $saldosActuales = SaldoMensual::where('year', $year)
            ->where('month', $month)
            ->where('id_empresa', $empresa_id)
            ->get();

        foreach ($saldosActuales as $saldo) {
            // Actualizar saldo inicial en el catálogo de cuentas
            Cuenta::where('id', $saldo->id_cuenta)
                ->where('id_empresa', $empresa_id)
                ->update(['saldo_inicial' => $saldo->saldo_final]);

            // Crear registro para el siguiente período si no existe
            SaldoMensual::firstOrCreate(
                [
                    'id_cuenta' => $saldo->id_cuenta,
                    'year' => $siguientePeriodo['year'],
                    'month' => $siguientePeriodo['month'],
                    'id_empresa' => $empresa_id,
                ],
                [
                    'codigo_cuenta' => $saldo->codigo_cuenta,
                    'nombre_cuenta' => $saldo->nombre_cuenta,
                    'naturaleza' => $saldo->naturaleza,
                    'saldo_inicial' => $saldo->saldo_final,
                    'debe' => 0,
                    'haber' => 0,
                    'saldo_final' => $saldo->saldo_final,
                    'estado' => 'Abierto',
                ]
            );
        }
    }

    /**
     * Validar cuadre del balance usando movimientos debe/haber
     */
    private function validarCuadreBalance($saldos)
    {
        // Sumar movimientos del período (debe vs haber)
        $totalDebe = collect($saldos)->sum('debe');
        $totalHaber = collect($saldos)->sum('haber');
        $diferencia = abs($totalDebe - $totalHaber);

        // Permitir diferencias de hasta $1.00
        if ($diferencia > 1.00) {
            throw new Exception("El balance no cuadra. Diferencia: $" . number_format($diferencia, 2) . ". Debe: $" . number_format($totalDebe, 2) . ", Haber: $" . number_format($totalHaber, 2));
        }
    }

    /**
     * Reabrir período cerrado
     */
    public function reabrirPeriodo($year, $month, $empresa_id)
    {
        DB::beginTransaction();

        try {
            // Validar que el período siguiente no esté cerrado
            $siguientePeriodo = $this->obtenerSiguientePeriodo($year, $month);

            $siguienteCerrado = SaldoMensual::where('year', $siguientePeriodo['year'])
                ->where('month', $siguientePeriodo['month'])
                ->where('id_empresa', $empresa_id)
                ->where('estado', 'Cerrado')
                ->exists();

            if ($siguienteCerrado) {
                throw new Exception("No se puede reabrir el período porque el siguiente período ya está cerrado");
            }

            // Cambiar estado a Abierto
            SaldoMensual::where('year', $year)
                ->where('month', $month)
                ->where('id_empresa', $empresa_id)
                ->update([
                    'estado' => 'Abierto',
                    'fecha_cierre' => null,
                    'id_usuario_cierre' => null,
                ]);

            // Reabrir partidas del período
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            Partida::where('id_empresa', $empresa_id)
                ->whereBetween('fecha', [$startDate, $endDate])
                ->where('estado', 'Cerrada')
                ->update(['estado' => 'Aplicada']);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Período reabierto exitosamente',
                'periodo' => "{$month}/{$year}",
            ];

        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Obtener balance de comprobación usando saldos mensuales
     */
    public function obtenerBalanceComprobacion($year, $month, $empresa_id)
    {
        $saldos = SaldoMensual::where('year', $year)
            ->where('month', $month)
            ->where('id_empresa', $empresa_id)
            ->orderBy('codigo_cuenta')
            ->get();

        $balance = [];
        $totalDeudor = 0;
        $totalAcreedor = 0;
        $totalDebe = 0;
        $totalHaber = 0;

        foreach ($saldos as $saldo) {
            $balance[] = [
                'codigo' => $saldo->codigo_cuenta,
                'nombre' => $saldo->nombre_cuenta,
                'naturaleza' => $saldo->naturaleza,
                'saldo_inicial' => $saldo->saldo_inicial,
                'debe' => $saldo->debe,
                'haber' => $saldo->haber,
                'saldo_final' => $saldo->saldo_final,
                'estado' => $saldo->estado,
            ];

            // Sumar movimientos del período (debe/haber)
            $totalDebe += $saldo->debe;
            $totalHaber += $saldo->haber;

            // Sumar saldos finales por naturaleza
            if ($saldo->naturaleza == 'Deudor') {
                $totalDeudor += $saldo->saldo_final;
            } else {
                $totalAcreedor += $saldo->saldo_final;
            }
        }

                return [
            'balance' => $balance,
            'totales' => [
                // Totales de movimientos del período
                'debe' => $totalDebe,
                'haber' => $totalHaber,
                'diferencia_movimientos' => $totalDebe - $totalHaber,
                'cuadra_movimientos' => abs($totalDebe - $totalHaber) < 0.01,
                'cuadra_movimientos_con_tolerancia' => abs($totalDebe - $totalHaber) <= 1.00,

                // Totales por naturaleza de cuentas
                'deudor' => $totalDeudor,
                'acreedor' => $totalAcreedor,
                'diferencia' => $totalDeudor - $totalAcreedor,
                'cuadra' => abs($totalDeudor - $totalAcreedor) < 0.01,
                'cuadra_con_tolerancia' => abs($totalDeudor - $totalAcreedor) <= 1.00,
            ],
            'periodo' => "{$month}/{$year}",
        ];
    }

    /**
     * Obtener período anterior
     */
    private function obtenerPeriodoAnterior($year, $month)
    {
        if ($month == 1) {
            return ['year' => $year - 1, 'month' => 12];
        }
        return ['year' => $year, 'month' => $month - 1];
    }

    /**
     * Obtener siguiente período
     */
    private function obtenerSiguientePeriodo($year, $month)
    {
        if ($month == 12) {
            return ['year' => $year + 1, 'month' => 1];
        }
        return ['year' => $year, 'month' => $month + 1];
    }

    /**
     * Verificar si un período está cerrado
     */
    public function estaPeriodoCerrado($year, $month, $empresa_id)
    {
        return SaldoMensual::where('year', $year)
            ->where('month', $month)
            ->where('id_empresa', $empresa_id)
            ->where('estado', 'Cerrado')
            ->exists();
    }
}
