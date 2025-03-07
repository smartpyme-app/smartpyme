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
                
                // 6. Calcular métricas del mes anterior para comparativas
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
                
                // 7. Calcular comparativas
                $comparativas = $this->calcularComparativas(
                    $ventas['sin_iva'], 
                    $egresos['sin_iva'], 
                    $ventas['sin_iva'] - $egresos['sin_iva'],
                    $metricasAnteriores
                );
                
                // 8. Presupuesto para la sucursal (osea la empresa)
                $presupuesto = DB::table('presupuestos')
                    ->where('id_empresa', $sucursal->id_empresa)
                    // ->where('id_sucursal', $sucursal->id)
                    ->where('fecha_inicio', '<=', $primerDiaMes)
                    ->where('fecha_fin', '>=', $ultimoDiaMes)
                    ->first();
                
                $ventasVsPresupuesto = 0;
                if ($presupuesto && $presupuesto->ingresos > 0) {
                    $ventasVsPresupuesto = (($ventas['sin_iva'] - $presupuesto->ingresos) / $presupuesto->ingresos) * 100;
                }
                
                // 9. Actualizar registro en la base de datos
                DB::table('ia_metricas_mensuales_sucursales')
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
        $resultados = DB::table('ventas')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '!=', 'Anulada')
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->select(
                DB::raw('SUM(sub_total) as sin_iva'),
                DB::raw('SUM(total) as con_iva')
            )
            ->first();
        
        return [
            'sin_iva' => $resultados->sin_iva ?? 0,
            'con_iva' => $resultados->con_iva ?? 0
        ];
    }
    
    private function calcularEgresos($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        // Combinar egresos directos y compras
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
        
        return [
            'sin_iva' => ($egresosDirectos->sin_iva ?? 0) + ($compras->sin_iva ?? 0),
            'con_iva' => ($egresosDirectos->con_iva ?? 0) + ($compras->con_iva ?? 0)
        ];
    }

    private function calcularCostoVenta($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        $resultado = DB::table('ventas')
            ->join('detalles_venta', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->where('ventas.id_empresa', $idEmpresa)
            ->where('ventas.id_sucursal', $idSucursal)
            ->where('ventas.estado', '!=', 'Anulada')
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
            ->where('fecha', '<=', $fechaCorte)
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
        
        // Totales CxP de compras
        $totalCxP = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha', '<=', $fechaCorte)
            ->sum('total');
            
        // Vencidas
        $vencidas = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->where('fecha_pago', '<', $fechaCorte)
            ->sum('total');
            
        // Por vencer en 30 días
        $porVencer = DB::table('compras')
            ->where('id_empresa', $idEmpresa)
            ->where('id_sucursal', $idSucursal)
            ->where('estado', '=', 'Pendiente')
            ->whereBetween('fecha_pago', [$fechaCorte, $treintaDiasDespues])
            ->sum('total');
            
        return [
            'totales' => $totalCxP,
            'vencidas' => $vencidas,
            'vencimiento_30_dias' => $porVencer
        ];
    }
    
    /**
     * Calcula métricas para el mes anterior (en caso de necesitar recalcular).
     */
    private function calcularMetricasMesAnterior($idEmpresa, $idSucursal, $fechaInicio, $fechaFin)
    {
        $ventas = $this->calcularVentas($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);
        $egresos = $this->calcularEgresos($idEmpresa, $idSucursal, $fechaInicio, $fechaFin);
        
        return [
            'ventas_sin_iva' => $ventas['sin_iva'],
            'egresos_sin_iva' => $egresos['sin_iva'],
            'flujo_efectivo_sin_iva' => $ventas['sin_iva'] - $egresos['sin_iva'],
            'rentabilidad_monto' => $ventas['sin_iva'] - $egresos['sin_iva']
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
        
        return [
            'ventas' => $ventasAnt > 0 ? (($ventasActual - $ventasAnt) / $ventasAnt) * 100 : 0,
            'egresos' => $egresosAnt > 0 ? (($egresosActual - $egresosAnt) / $egresosAnt) * 100 : 0,
            'flujo' => $flujoAnt > 0 ? (($flujoActual - $flujoAnt) / $flujoAnt) * 100 : 0,
            'rentabilidad' => $rentabilidadAnt > 0 ? (($flujoActual - $rentabilidadAnt) / $rentabilidadAnt) * 100 : 0
        ];
    }
    
    /**
     * Registra la actualización en el historial.
     */
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