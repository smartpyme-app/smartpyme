<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\Empresa;

class CalcularMetricasEmpresas extends Command
{

    protected $signature = 'metricas:empresas {--fecha=} {--empresa=} {--actualizar-historico}';

    protected $description = 'Calcula y actualiza las métricas mensuales para empresas';

    public function handle()
    {
        $this->info('Iniciando cálculo de métricas mensuales para empresas...');

        // Determinar fecha a procesar
        $fecha = $this->option('fecha')
            ? Carbon::parse($this->option('fecha'))
            : Carbon::now();

        $mesActual = $fecha->format('Y-m');
        $primerDiaMes = $fecha->startOfMonth()->format('Y-m-d');
        $ultimoDiaMes = $fecha->endOfMonth()->format('Y-m-d');

        $this->info("Procesando métricas para el mes: {$mesActual}");

        // Obtener empresas a procesar
        $query = Empresa::where('activo', 1);

        // Si se especificó una empresa en particular
        if ($this->option('empresa')) {
            $query->where('id', $this->option('empresa'));
        }

        $empresas = $query->get();
        $total = $empresas->count();
        $this->info("Se procesarán {$total} empresas");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $actualizarHistorico = $this->option('actualizar-historico');
        $mesAnterior = Carbon::parse($primerDiaMes)->subMonth();
        $mesAnteriorInicio = $mesAnterior->startOfMonth()->format('Y-m-d');
        $mesAnteriorFin = $mesAnterior->endOfMonth()->format('Y-m-d');

        foreach ($empresas as $empresa) {
            try {
                // Buscar o crear registro de métricas
                $metrica = DB::table('ia_metricas_mensuales_empresas')
                    ->where('id_empresa', $empresa->id)
                    ->where('fecha', $primerDiaMes)
                    ->first();

                if (!$metrica) {
                    $id = DB::table('ia_metricas_mensuales_empresas')->insertGetId([
                        'id_empresa' => $empresa->id,
                        'fecha' => $primerDiaMes,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $metrica = DB::table('ia_metricas_mensuales_empresas')->where('id', $id)->first();
                }

                // 1. Calcular ventas (con y sin IVA)
                $ventas = $this->calcularVentas($empresa->id, $primerDiaMes, $ultimoDiaMes);

                // 2. Calcular egresos (con y sin IVA)
                $egresos = $this->calcularEgresos($empresa->id, $primerDiaMes, $ultimoDiaMes);

                // 3. Calcular costo de venta
                $costoVenta = $this->calcularCostoVenta($empresa->id, $primerDiaMes, $ultimoDiaMes);

                // 4. Calcular CxC
                $cxc = $this->calcularCuentasPorCobrar($empresa->id, $primerDiaMes);

                // 5. Calcular CxP
                $cxp = $this->calcularCuentasPorPagar($empresa->id, $primerDiaMes);

                // 6. Calcular métricas del mes anterior para comparativas
                $metricasAnteriores = [];
                if ($actualizarHistorico) {
                    $metricasAnteriores = $this->calcularMetricasMesAnterior($empresa->id, $mesAnteriorInicio, $mesAnteriorFin);
                } else {
                    $metricasAnteriores = DB::table('ia_metricas_mensuales_empresas')
                        ->where('id_empresa', $empresa->id)
                        ->where('fecha', $mesAnteriorInicio)
                        ->first();
                }

                // 7. Calcular comparativas
                $comparativas = $this->calcularComparativas(
                    $ventas['sin_iva'],
                    $egresos['sin_iva'],
                    $ventas['sin_iva'] - $egresos['sin_iva'],
                    $metricasAnteriores
                );

                // 8. Presupuesto (si existe)
                $presupuesto = DB::table('presupuestos')
                    ->where('id_empresa', $empresa->id)
                    ->where('fecha_inicio', '<=', $primerDiaMes)
                    ->where('fecha_fin', '>=', $ultimoDiaMes)
                    ->first();

                $ventasVsPresupuesto = 0;
                if ($presupuesto && $presupuesto->ingresos > 0) {
                    $ventasVsPresupuesto = (($ventas['sin_iva'] - $presupuesto->ingresos) / $presupuesto->ingresos) * 100;
                }

                // 9. Actualizar registro en la base de datos
                DB::table('ia_metricas_mensuales_empresas')
                    ->where('id', $metrica->id)
                    ->update([
                        'ventas_sin_iva' => $ventas['sin_iva'],
                        'ventas_con_iva' => $ventas['con_iva'],
                        'egresos_sin_iva' => $egresos['sin_iva'],
                        'egresos_con_iva' => $egresos['con_iva'],
                        'costo_venta_sin_iva' => $costoVenta,
                        'flujo_efectivo_sin_iva' => $ventas['sin_iva'] - $egresos['sin_iva'],
                        'flujo_efectivo_con_iva' => $ventas['con_iva'] - $egresos['con_iva'],
                        'rentabilidad_monto' => $ventas['sin_iva'] - $egresos['sin_iva'],
                        'rentabilidad_porcentaje' => $ventas['sin_iva'] > 0
                            ? (($ventas['sin_iva'] - $egresos['sin_iva']) / $ventas['sin_iva']) * 100
                            : 0,
                        'cxc_totales' => $cxc['totales'],
                        'cxc_vencidas' => $cxc['vencidas'],
                        'cxc_vencimiento_30_dias' => $cxc['vencimiento_30_dias'],
                        'cxp_totales' => $cxp['totales'],
                        'cxp_vencidas' => $cxp['vencidas'],
                        'cxp_vencimiento_30_dias' => $cxp['vencimiento_30_dias'],
                        'ventas_vs_mes_anterior' => $comparativas['ventas'],
                        'egresos_vs_mes_anterior' => $comparativas['egresos'],
                        'flujo_efectivo_vs_mes_anterior' => $comparativas['flujo'],
                        'rentabilidad_vs_mes_anterior' => $comparativas['rentabilidad'],
                        'ventas_vs_presupuesto' => $ventasVsPresupuesto,
                        'updated_at' => now()
                    ]);

                // 10. Registrar en historial
                $this->registrarActualizacion('empresa', $metrica->id);
            } catch (\Exception $e) {
                Log::error("Error al procesar métricas para empresa ID {$empresa->id}: " . $e->getMessage());
                $this->error("\nError al procesar empresa ID {$empresa->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nProceso completado correctamente!");

        return 0;
    }

    /**
     * Calcula ventas con y sin IVA para una empresa en un período específico.
     */
    private function calcularVentas($idEmpresa, $fechaInicio, $fechaFin)
    {
        // 1. Obtener ventas (excluyendo cotizaciones)
        $resultadosVentas = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();


        // 2. Obtener abonos a ventas
        $resultadosAbonos = DB::table('abonos_ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulado')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->sum('total');

        // 3. Obtener devoluciones de ventas
        $resultadosDevoluciones = DB::table('devoluciones_venta')
            ->where('id_empresa', $idEmpresa)
            ->where('enable', 1)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 4. Calcular totales
        $sinIva = ($resultadosVentas->sin_iva ?? 0) + $resultadosAbonos - ($resultadosDevoluciones->sin_iva ?? 0);
        $conIva = ($resultadosVentas->con_iva ?? 0) + $resultadosAbonos - ($resultadosDevoluciones->con_iva ?? 0);

        return [
            'sin_iva' => $sinIva,
            'con_iva' => $conIva
        ];
    }
    
    private function calcularEgresos($idEmpresa, $fechaInicio, $fechaFin)
    {
        // 1. Obtener compras
        $compras = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 2. Obtener egresos directos
        $egresosDirectos = DB::table('egresos')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulado')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 3. Obtener abonos a compras
        $abonosCompras = DB::table('abonos_compras')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '!=', 'Anulado')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->sum('total');

        // 4. Obtener devoluciones de compra
        $devolucionesCompra = DB::table('devoluciones_compra')
            ->where('id_empresa', $idEmpresa)
            ->where('enable', 1)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 5. Calcular totales
        $sinIva = ($compras->sin_iva ?? 0) + ($egresosDirectos->sin_iva ?? 0) +
            $abonosCompras - ($devolucionesCompra->sin_iva ?? 0);

        $conIva = ($compras->con_iva ?? 0) + ($egresosDirectos->con_iva ?? 0) +
            $abonosCompras - ($devolucionesCompra->con_iva ?? 0);

        return [
            'sin_iva' => $sinIva,
            'con_iva' => $conIva
        ];
    }

    /**
     * Calcula el costo de venta para una empresa en un período específico.
     */
    private function calcularCostoVenta($idEmpresa, $fechaInicio, $fechaFin)
    {
        $resultado = DB::table('ventas')
            ->join('detalles_venta', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->where('ventas.id_empresa', $idEmpresa)
            ->where('ventas.estado', '!=', 'Anulada')
            ->whereBetween('ventas.fecha', [$fechaInicio, $fechaFin])
            ->sum('detalles_venta.total_costo');

        return $resultado ?? 0;
    }

    private function calcularCuentasPorCobrar($idEmpresa, $fechaCorte)
    {
        $fechaActual = Carbon::parse($fechaCorte);
        $treintaDiasDespues = $fechaActual->copy()->addDays(30)->format('Y-m-d');

        // Totales
        $totalCxC = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha', '<=', $fechaCorte)
            ->sum('total');

        // Vencidas
        $vencidas = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha_pago', '<', $fechaCorte)
            ->sum('total');

        // Por vencer en 30 días
        $porVencer = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '=', 'Pendiente')
            ->whereBetween('fecha_pago', [$fechaCorte, $treintaDiasDespues])
            ->sum('total');

        return [
            'totales' => $totalCxC,
            'vencidas' => $vencidas,
            'vencimiento_30_dias' => $porVencer
        ];
    }

    private function calcularCuentasPorPagar($idEmpresa, $fechaCorte)
    {
        $fechaActual = Carbon::parse($fechaCorte);
        $treintaDiasDespues = $fechaActual->copy()->addDays(30)->format('Y-m-d');

        // Totales CxP de compras
        $totalCxP = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha', '<=', $fechaCorte)
            ->sum('total');

        // Vencidas
        $vencidas = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha_pago', '<', $fechaCorte)
            ->sum('total');

        // Por vencer en 30 días
        $porVencer = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('estado', '=', 'Pendiente')
            ->whereBetween('fecha_pago', [$fechaCorte, $treintaDiasDespues])
            ->sum('total');

        return [
            'totales' => $totalCxP,
            'vencidas' => $vencidas,
            'vencimiento_30_dias' => $porVencer
        ];
    }

    private function calcularMetricasMesAnterior($idEmpresa, $fechaInicio, $fechaFin)
    {
        $ventas = $this->calcularVentas($idEmpresa, $fechaInicio, $fechaFin);
        $egresos = $this->calcularEgresos($idEmpresa, $fechaInicio, $fechaFin);

        return [
            'ventas_sin_iva' => $ventas['sin_iva'],
            'egresos_sin_iva' => $egresos['sin_iva'],
            'flujo_efectivo_sin_iva' => $ventas['sin_iva'] - $egresos['sin_iva'],
            'rentabilidad_monto' => $ventas['sin_iva'] - $egresos['sin_iva']
        ];
    }

    private function calcularComparativas($ventasActual, $egresosActual, $flujoActual, $mesAnterior)
    {
        $ventasAnt = isset($mesAnterior->ventas_sin_iva) ? $mesAnterior->ventas_sin_iva : 0;
        $egresosAnt = isset($mesAnterior->egresos_sin_iva) ? $mesAnterior->egresos_sin_iva : 0;
        $flujoAnt = isset($mesAnterior->flujo_efectivo_sin_iva) ? $mesAnterior->flujo_efectivo_sin_iva : 0;
        $rentabilidadAnt = isset($mesAnterior->rentabilidad_monto) ? $mesAnterior->rentabilidad_monto : 0;

        return [
            'ventas' => $ventasAnt > 0 ? (($ventasActual - $ventasAnt) / $ventasAnt) * 100 : 0,
            'egresos' => $egresosAnt > 0 ? (($egresosActual - $egresosAnt) / $egresosAnt) * 100 : 0,
            'flujo' => $flujoAnt > 0 ? (($flujoActual - $flujoAnt) / $flujoAnt) * 100 : 0,
            'rentabilidad' => $rentabilidadAnt > 0 ? (($flujoActual - $rentabilidadAnt) / $rentabilidadAnt) * 100 : 0
        ];
    }

    private function registrarActualizacion($tipoMetrica, $idMetrica)
    {
        DB::table('ia_metricas_historial')->insert([
            'tipo_metrica' => $tipoMetrica,
            'id_metrica' => $idMetrica,
            'fecha_actualizacion' => now(),
            'usuario_id' => null, // Sistema automático
            'notas' => 'Actualización automatizada por cron'
        ]);
    }
}
