<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Admin\Empresa;
use App\Models\Admin\Sucursal;
use Illuminate\Support\Facades\Log;

class CalcularMetricasSucursales extends Command
{

    protected $signature = 'metricas:sucursales {--fecha=} {--empresa=} {--sucursal=} {--actualizar-historico}';

    protected $description = 'Calcula y actualiza las métricas mensuales para sucursales';

    public function handle()
    {
        $this->info('Iniciando cálculo de métricas mensuales para sucursales...');

        $fecha = $this->option('fecha')
            ? Carbon::parse($this->option('fecha'))
            : Carbon::now();

        $mesActual = $fecha->format('Y-m');
        $primerDiaMes = $fecha->startOfMonth()->format('Y-m-d');
        $ultimoDiaMes = $fecha->endOfMonth()->format('Y-m-d');

        $this->info("Procesando métricas para el mes: {$mesActual}");

        $query = Sucursal::where('activo', 1);

        if ($this->option('empresa')) {
            $query->where('id_empresa', $this->option('empresa'));
        }

        if ($this->option('sucursal')) {
            $query->where('id', $this->option('sucursal'));
        }

        $sucursales = $query->get();
        $total = $sucursales->count();
        $this->info("Se procesarán {$total} sucursales");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $actualizarHistorico = $this->option('actualizar-historico');
        $mesAnterior = Carbon::parse($primerDiaMes)->subMonth();
        $mesAnteriorInicio = $mesAnterior->startOfMonth()->format('Y-m-d');
        $mesAnteriorFin = $mesAnterior->endOfMonth()->format('Y-m-d');

        foreach ($sucursales as $sucursal) {
            try {
                $metrica = DB::table('ia_metricas_mensuales_sucursales')
                    ->where('id_empresa', $sucursal->id_empresa)
                    ->where('id_sucursal', $sucursal->id)
                    ->where('fecha', $primerDiaMes)
                    ->first();

                if (!$metrica) {
                    $id = DB::table('ia_metricas_mensuales_sucursales')->insertGetId([
                        'id_empresa' => $sucursal->id_empresa,
                        'id_sucursal' => $sucursal->id,
                        'fecha' => $primerDiaMes,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $metrica = DB::table('ia_metricas_mensuales_sucursales')->where('id', $id)->first();
                }

                // 1. Calcular ventas (con y sin IVA) para la sucursal
                $ventas = $this->calcularVentas($sucursal->id_empresa, $sucursal->id, $primerDiaMes, $ultimoDiaMes);

                // 2. Calcular egresos (con y sin IVA) para la sucursal
                $egresos = $this->calcularEgresos($sucursal->id_empresa, $sucursal->id, $primerDiaMes, $ultimoDiaMes);

                // 3. Calcular costo de venta para la sucursal
                $costoVenta = $this->calcularCostoVenta($sucursal->id_empresa, $sucursal->id, $primerDiaMes, $ultimoDiaMes);

                // 4. Calcular CxC para la sucursal
                $cxc = $this->calcularCuentasPorCobrar($sucursal->id_empresa, $sucursal->id, $primerDiaMes);

                // 5. Calcular CxP para la sucursal
                $cxp = $this->calcularCuentasPorPagar($sucursal->id_empresa, $sucursal->id, $primerDiaMes);

                // 6. Calcular flujo de efectivo
                $flujoEfectivo = $this->calcularFlujoEfectivoConIva($sucursal->id_empresa, $sucursal->id, $primerDiaMes, $ultimoDiaMes);

                // 7. Calcular rentabilidad
                $rentabilidad = $this->calcularRentabilidadMonto($sucursal->id_empresa, $sucursal->id, $primerDiaMes, $ultimoDiaMes);

                // 8. Calcular métricas del mes anterior para comparativas
                $metricasAnteriores = [];
                if ($actualizarHistorico) {
                    $metricasAnteriores = $this->calcularMetricasMesAnterior(
                        $sucursal->id_empresa,
                        $sucursal->id,
                        $mesAnteriorInicio,
                        $mesAnteriorFin
                    );
                } else {
                    $metricasAnteriores = DB::table('ia_metricas_mensuales_sucursales')
                        ->where('id_empresa', $sucursal->id_empresa)
                        ->where('id_sucursal', $sucursal->id)
                        ->where('fecha', $mesAnteriorInicio)
                        ->first();
                }

                // 9. Calcular comparativas
                $comparativas = $this->calcularComparativas(
                    $ventas['sin_iva'],
                    $egresos['sin_iva'],
                    $flujoEfectivo['sin_iva'],
                    $metricasAnteriores
                );

                // 10. Presupuesto para la sucursal (osea la empresa)
                $presupuesto = DB::table('presupuestos')
                    ->where('id_empresa', $sucursal->id_empresa)
                    // ->where('id_sucursal', $sucursal->id)
                    ->where('fecha_inicio', '<=', $primerDiaMes)
                    ->where('fecha_fin', '>=', $ultimoDiaMes)
                    ->first();

                $ventasVsPresupuesto = 0;
                if ($presupuesto && $presupuesto->ingresos > 0) {
                    $ventasVsPresupuesto = min(9999.99, max(-9999.99, (($ventas['sin_iva'] - $presupuesto->ingresos) / $presupuesto->ingresos) * 100));
                }

                // 11. Actualizar registro en la base de datos
                DB::table('ia_metricas_mensuales_sucursales')
                    ->where('id', $metrica->id)
                    ->update([
                        'ventas_sin_iva' => $ventas['sin_iva'],
                        'ventas_con_iva' => $ventas['con_iva'],
                        'egresos_sin_iva' => $egresos['sin_iva'],
                        'egresos_con_iva' => $egresos['con_iva'],
                        'costo_venta_sin_iva' => $costoVenta,
                        'flujo_efectivo_sin_iva' => $flujoEfectivo['sin_iva'],
                        'flujo_efectivo_con_iva' => $flujoEfectivo['con_iva'],
                        'rentabilidad_monto' => $rentabilidad['monto_sin_iva'],
                        'rentabilidad_porcentaje' => $rentabilidad['porcentaje_sin_iva'],
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

                // 12. Registrar en historial
                $this->registrarActualizacion('sucursal', $metrica->id);
            } catch (\Exception $e) {
                Log::error("Error al procesar métricas para sucursal ID {$sucursal->id}: " . $e->getMessage());
                $this->error("\nError al procesar sucursal ID {$sucursal->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nProceso completado correctamente!");

        return 0;
    }

    /**
     * Calcula ventas con y sin IVA para una sucursal en un período específico.
     */
    private function calcularVentas($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        // 1. Obtener ventas (excluyendo cotizaciones)
        $resultadosVentas = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulada')
            ->where('cotizacion', 0)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 2. Obtener devoluciones de ventas
        $resultadosDevoluciones = DB::table('devoluciones_venta')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('enable', 1)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 3. Calcular totales
        $sinIva = ($resultadosVentas->sin_iva ?? 0) - ($resultadosDevoluciones->sin_iva ?? 0);
        $conIva = ($resultadosVentas->con_iva ?? 0) - ($resultadosDevoluciones->con_iva ?? 0);

        return [
            'sin_iva' => $sinIva,
            'con_iva' => $conIva
        ];
    }

    private function calcularEgresos($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        // 1. Obtener compras
        $compras = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
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
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulado')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 3. Obtener devoluciones de compra
        $devolucionesCompra = DB::table('devoluciones_compra')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('enable', 1)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 4. Calcular totales
        $sinIva = ($compras->sin_iva ?? 0) + ($egresosDirectos->sin_iva ?? 0) - ($devolucionesCompra->sin_iva ?? 0);
        $conIva = ($compras->con_iva ?? 0) + ($egresosDirectos->con_iva ?? 0) - ($devolucionesCompra->con_iva ?? 0);

        return [
            'sin_iva' => $sinIva,
            'con_iva' => $conIva
        ];
    }

    private function calcularCostoVenta($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        // Excluir ventas que tienen devoluciones asociadas
        $resultado = DB::table('ventas')
            ->join('detalles_venta', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->where('ventas.id_empresa', $idEmpresa)
            ->where('ventas.id_sucursal', $idSucursal)
            ->where('ventas.estado', '!=', 'Anulada')
            ->whereNotIn('ventas.id', function ($query) {
                $query->select('id_venta')
                    ->from('devoluciones_venta')
                    ->where('estado', '!=', 'Anulada');
            })
            ->whereBetween('ventas.fecha', [$fechaInicio, $fechaFin])
            ->sum('detalles_venta.total_costo');

        return $resultado ?? 0;
    }

    /**
     * Calcula cuentas por cobrar para una sucursal.
     */
    private function calcularCuentasPorCobrar($idEmpresa, $idSucursal, $fechaCorte)
    {
        $fechaActual = Carbon::parse($fechaCorte);
        $treintaDiasDespues = $fechaActual->copy()->addDays(30)->format('Y-m-d');

        // Totales
        $totalCxC = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            // ->where('fecha', '<=', $fechaCorte) // No es necesario si queremos todas las pendientes
            ->sum('total');

        // Vencidas
        $vencidas = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha_pago', '<', $fechaCorte)
            ->sum('total');

        // Por vencer en 30 días
        $porVencer = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->whereBetween('fecha_pago', [$fechaCorte, $treintaDiasDespues])
            ->sum('total');

        return [
            'totales' => $totalCxC,
            'vencidas' => $vencidas,
            'vencimiento_30_dias' => $porVencer
        ];
    }

    /**
     * Calcula cuentas por pagar para una sucursal.
     */
    private function calcularCuentasPorPagar($idEmpresa, $idSucursal, $fechaCorte)
    {
        $fechaActual = Carbon::parse($fechaCorte);
        $treintaDiasDespues = $fechaActual->copy()->addDays(30)->format('Y-m-d');

        // 1. Totales CxP de compras
        $totalCompras = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->sum('total');

        // 2. Totales CxP de egresos (gastos)
        $totalEgresos = DB::table('egresos')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->sum('total');

        // 3. Vencidas de compras
        $vencidasCompras = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha_pago', '<', $fechaCorte)
            ->sum('total');

        // 4. Vencidas de egresos
        $vencidasEgresos = DB::table('egresos')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha_pago', '<', $fechaCorte)
            ->sum('total');

        // 5. Por vencer en 30 días de compras
        $porVencerCompras = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->whereBetween('fecha_pago', [$fechaCorte, $treintaDiasDespues])
            ->sum('total');

        // 6. Por vencer en 30 días de egresos
        $porVencerEgresos = DB::table('egresos')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->whereBetween('fecha_pago', [$fechaCorte, $treintaDiasDespues])
            ->sum('total');

        // Calcular totales combinados
        $totalCxP = $totalCompras + $totalEgresos;
        $vencidas = $vencidasCompras + $vencidasEgresos;
        $porVencer = $porVencerCompras + $porVencerEgresos;

        return [
            'totales' => $totalCxP,
            'vencidas' => $vencidas,
            'vencimiento_30_dias' => $porVencer
        ];
    }

    /**
     * Calcula el flujo de efectivo con y sin IVA para una sucursal en un período específico.
     */
    private function calcularFlujoEfectivoConIva($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        // 1. Ventas pagadas en el periodo (estado "Pagada" o similar)
        $ventasPagadas = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', 'Pagada')
            ->whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 2. Abonos recibidos en el periodo
        $abonosRecibidos = DB::table('abonos_ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulado')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->sum('total');

        // 3. Egresos pagados en el periodo (de contado)
        $egresosPagados = DB::table('egresos')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulado')
            ->where('forma_pago', 'Contado') // O el campo equivalente que indica pago de contado
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 4. Compras pagadas en el periodo (de contado)
        $comprasPagadas = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulada')
            ->where('forma_pago', 'Contado') // O el campo equivalente que indica pago de contado
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 5. Abonos a compras y gastos realizados en el periodo
        $abonosRealizados = DB::table('abonos_compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulado')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->sum('total');

        // Calcular flujo de efectivo con IVA
        $flujoConIva = ($ventasPagadas->con_iva ?? 0) + $abonosRecibidos
            - (($egresosPagados->con_iva ?? 0) + ($comprasPagadas->con_iva ?? 0) + $abonosRealizados);

        // Calcular flujo de efectivo sin IVA
        $flujoSinIva = ($ventasPagadas->sin_iva ?? 0) + $abonosRecibidos
            - (($egresosPagados->sin_iva ?? 0) + ($comprasPagadas->sin_iva ?? 0) + $abonosRealizados);

        return [
            'con_iva' => $flujoConIva,
            'sin_iva' => $flujoSinIva
        ];
    }

    /**
     * Calcula la rentabilidad para una sucursal en un período específico.
     */
    private function calcularRentabilidadMonto($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        // 1. Obtener ventas
        $ventas = $this->calcularVentas($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);

        // 2. Obtener costo de venta
        $costoVenta = $this->calcularCostoVenta($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);

        // 3. Filtrar gastos que no son costo de venta
        $gastosNoCosteables = DB::table('egresos')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulado')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->whereNotIn('id_categoria', function ($query) {
                $query->select('id')
                    ->from('gastos_categorias')
                    ->where('es_costo_venta', 1); // Suponiendo que tienes un indicador
            })
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();

        // 4. Calcular rentabilidad sin IVA
        $rentabilidadSinIva = $ventas['sin_iva'] - ($gastosNoCosteables->sin_iva ?? 0) - $costoVenta;

        // 5. Calcular rentabilidad con IVA
        $rentabilidadConIva = $ventas['con_iva'] - ($gastosNoCosteables->con_iva ?? 0) - $costoVenta;

        // 6. Calcular porcentaje de rentabilidad
        $porcentajeSinIva = $ventas['sin_iva'] > 0
            ? ($rentabilidadSinIva / $ventas['sin_iva']) * 100
            : 0;

        $porcentajeConIva = $ventas['con_iva'] > 0
            ? ($rentabilidadConIva / $ventas['con_iva']) * 100
            : 0;

        return [
            'monto_sin_iva' => $rentabilidadSinIva,
            'monto_con_iva' => $rentabilidadConIva,
            'porcentaje_sin_iva' => $porcentajeSinIva,
            'porcentaje_con_iva' => $porcentajeConIva
        ];
    }

    /**
     * Calcula métricas para el mes anterior (en caso de necesitar recalcular).
     */
    private function calcularMetricasMesAnterior($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        $ventas = $this->calcularVentas($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);
        $egresos = $this->calcularEgresos($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);

        // Calcular flujo de efectivo
        $flujoEfectivo = $this->calcularFlujoEfectivoConIva($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);

        // Calcular rentabilidad
        $rentabilidad = $this->calcularRentabilidadMonto($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);

        return [
            'ventas_sin_iva' => $ventas['sin_iva'],
            'ventas_con_iva' => $ventas['con_iva'],
            'egresos_sin_iva' => $egresos['sin_iva'],
            'egresos_con_iva' => $egresos['con_iva'],
            'flujo_efectivo_sin_iva' => $flujoEfectivo['sin_iva'],
            'flujo_efectivo_con_iva' => $flujoEfectivo['con_iva'],
            'rentabilidad_monto' => $rentabilidad['monto_sin_iva'],
            'rentabilidad_con_iva' => $rentabilidad['monto_con_iva'],
            'rentabilidad_porcentaje' => $rentabilidad['porcentaje_sin_iva']
        ];
    }

    /**
     * Calcula porcentajes comparativos con el mes anterior.
     */
    private function calcularComparativas($ventasActual, $egresosActual, $flujoActual, $mesAnterior)
    {
        $ventasAnt = isset($mesAnterior->ventas_sin_iva) ? $mesAnterior->ventas_sin_iva : 0;
        $egresosAnt = isset($mesAnterior->egresos_sin_iva) ? $mesAnterior->egresos_sin_iva : 0;
        $flujoAnt = isset($mesAnterior->flujo_efectivo_sin_iva) ? $mesAnterior->flujo_efectivo_sin_iva : 0;
        $rentabilidadAnt = isset($mesAnterior->rentabilidad_monto) ? $mesAnterior->rentabilidad_monto : 0;

        $resultado = [];

        // Ventas
        if ($ventasAnt < 0.01) {
            $resultado['ventas'] = $ventasActual > 0 ? 100 : 0; // 100% de incremento si hay ventas nuevas
        } else {
            $resultado['ventas'] = min(9999.99, max(-9999.99, (($ventasActual - $ventasAnt) / $ventasAnt) * 100));
        }

        // Egresos
        if ($egresosAnt < 0.01) {
            $resultado['egresos'] = $egresosActual > 0 ? 100 : 0;
            // Egresos
            if ($egresosAnt < 0.01) {
                $resultado['egresos'] = $egresosActual > 0 ? 100 : 0; // 100% de incremento si hay egresos nuevos
            } else {
                $resultado['egresos'] = min(9999.99, max(-9999.99, (($egresosActual - $egresosAnt) / $egresosAnt) * 100));
            }

            // Flujo
            if (abs($flujoAnt) < 0.01) {
                $resultado['flujo'] = $flujoActual > 0 ? 100 : ($flujoActual < 0 ? -100 : 0);
            } else {
                $resultado['flujo'] = min(9999.99, max(-9999.99, (($flujoActual - $flujoAnt) / abs($flujoAnt)) * 100));
            }

            // Rentabilidad
            if (abs($rentabilidadAnt) < 0.01) {
                $resultado['rentabilidad'] = $flujoActual > 0 ? 100 : ($flujoActual < 0 ? -100 : 0);
            } else {
                $resultado['rentabilidad'] = min(9999.99, max(-9999.99, (($flujoActual - $rentabilidadAnt) / abs($rentabilidadAnt)) * 100));
            }

            return $resultado;
        }
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
