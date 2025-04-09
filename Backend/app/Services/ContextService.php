<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ContextService
{
    public function generateSystemPrompt($empresa, $metricas, $basePrompt)
    {
        if (!$empresa) {
            return $basePrompt;
        }
        
        $contextInfo = "Información sobre la empresa:\n";
        $contextInfo .= "Nombre: {$empresa->nombre}\n";
        $contextInfo .= "que trabaja en El salvador, con sus legislaciones, comerciales y leyes del pais\n";
        
        if (!empty($empresa->industria)) {
            $contextInfo .= "Industria: {$empresa->industria}\n";
        }
        
        if (!empty($empresa->giro)) {
            $contextInfo .= "Giro: {$empresa->giro}\n";
        }
        
        if ($metricas && count($metricas) > 0) {
            $contextInfo .= "\nMétricas financieras recientes:\n";
            
            foreach ($metricas as $index => $metrica) {
                $fecha = Carbon::parse($metrica->fecha)->format('Y-m');
                $contextInfo .= "- Período {$fecha}:\n";
                $contextInfo .= "  * Ventas: $" . number_format($metrica->ventas_con_iva, 2) . "\n";
                $contextInfo .= "  * Egresos: $" . number_format($metrica->egresos_con_iva, 2) . "\n";
                $contextInfo .= "  * Rentabilidad: " . number_format($metrica->rentabilidad_porcentaje, 2) . "%\n";
                
                // Se limita hacia los ultimos 3 meses para no sobrecargar el prompt
                if ($index >= 2) break;
            }
        }
        
        $customPrompt = $basePrompt . "\n\nTienes acceso a la siguiente información contextual sobre la empresa del usuario. Utiliza esta información para proporcionar respuestas más precisas y personalizadas sobre su situación financiera:\n\n" . $contextInfo;
        
        $customPrompt .= "\n\nNo menciones explícitamente que tienes esta información a menos que el usuario la solicite. Usa estos datos para contextualizar tus respuestas y dar mejores consejos financieros.";
        
        return $customPrompt;
    }
    
    public function enrichContextWithQueryData($systemPrompt, $empresa, $consulta)
    {
        if (!$empresa) {
            return $systemPrompt;
        }
        
        try {
            $datosEspecificos = $this->obtenerDatosFinancierosPorConsulta($empresa, $consulta);
            
            if ($datosEspecificos) {
                $systemPrompt .= "\n\nDatos específicos relevantes para esta consulta:\n";
                
                if (is_object($datosEspecificos)) {
                    foreach ((array)$datosEspecificos as $clave => $valor) {
                        if (!is_null($valor) && !is_array($valor) && !is_object($valor)) {
                            if (is_numeric($valor) && strpos($clave, 'porcentaje') === false) {
                                $valor = '$' . number_format($valor, 2);
                            } elseif (is_numeric($valor) && strpos($clave, 'porcentaje') !== false) {
                                $valor = number_format($valor, 2) . '%';
                            }
                            
                            $clave = str_replace('_', ' ', $clave);
                            $clave = ucfirst($clave);
                            
                            $systemPrompt .= "- {$clave}: {$valor}\n";
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error al enriquecer contexto con datos específicos: ' . $e->getMessage());
            // Si hay un error, simplemente retornamos el prompt original sin enriquecimiento
        }
        
        return $systemPrompt;
    }
    
    public function obtenerDatosFinancierosPorConsulta($empresa, $tipoConsulta)
    {
        if (!$empresa) {
            return null;
        }
        
        $tipoConsulta = strtolower($tipoConsulta);
        $fechaActual = now();
        $inicioMes = $fechaActual->copy()->startOfMonth();
        $finMes = $fechaActual->copy()->endOfMonth();
        $mesAnterior = $fechaActual->copy()->subMonth();
        $inicioMesAnterior = $mesAnterior->copy()->startOfMonth();
        $finMesAnterior = $mesAnterior->copy()->endOfMonth();
        
        // Consultas relacionadas con ventas
        if (strpos($tipoConsulta, 'venta') !== false || 
            strpos($tipoConsulta, 'ingreso') !== false || 
            strpos($tipoConsulta, 'factura') !== false) {
            
            // Ventas del mes actual
            if (strpos($tipoConsulta, 'mes') !== false && 
                (strpos($tipoConsulta, 'actual') !== false || strpos($tipoConsulta, 'este') !== false)) {
                return DB::table('ventas')
                    ->where('id_empresa', $empresa->id)
                    ->where('estado', '!=', 'Anulada')
                    ->whereBetween('fecha', [$inicioMes, $finMes])
                    ->select(
                        DB::raw('SUM(total) as total_ventas'), 
                        DB::raw('SUM(sub_total) as subtotal_ventas'),
                        DB::raw('COUNT(*) as num_ventas'),
                        DB::raw('AVG(total) as promedio_venta')
                    )
                    ->first();
            }
            
            // Ventas del mes anterior
            if (strpos($tipoConsulta, 'mes anterior') !== false || strpos($tipoConsulta, 'último mes') !== false) {
                return DB::table('ventas')
                    ->where('id_empresa', $empresa->id)
                    ->where('estado', '!=', 'Anulada')
                    ->whereBetween('fecha', [$inicioMesAnterior, $finMesAnterior])
                    ->select(
                        DB::raw('SUM(total) as total_ventas'), 
                        DB::raw('SUM(sub_total) as subtotal_ventas'),
                        DB::raw('COUNT(*) as num_ventas'),
                        DB::raw('AVG(total) as promedio_venta')
                    )
                    ->first();
            }
            
            // Ventas de los últimos 30 días
            return DB::table('ventas')
                ->where('id_empresa', $empresa->id)
                ->where('estado', '!=', 'Anulada')
                ->whereBetween('fecha', [$fechaActual->copy()->subDays(30), $fechaActual])
                ->select(
                    DB::raw('SUM(total) as total_ventas'), 
                    DB::raw('SUM(sub_total) as subtotal_ventas'),
                    DB::raw('COUNT(*) as num_ventas'),
                    DB::raw('AVG(total) as promedio_venta')
                )
                ->first();
        }
        
        // Consultas relacionadas con costos o egresos
        if (strpos($tipoConsulta, 'gasto') !== false || 
            strpos($tipoConsulta, 'egreso') !== false || 
            strpos($tipoConsulta, 'costo') !== false ||
            strpos($tipoConsulta, 'compra') !== false) {
            
            // Egresos del mes actual
            if (strpos($tipoConsulta, 'mes') !== false && 
                (strpos($tipoConsulta, 'actual') !== false || strpos($tipoConsulta, 'este') !== false)) {
                
                // Combinar egresos directos y compras
                $egresosDirectos = DB::table('egresos')
                    ->where('id_empresa', $empresa->id)
                    ->where('estado', '!=', 'Anulado')
                    ->whereBetween('fecha', [$inicioMes, $finMes])
                    ->select(
                        DB::raw('SUM(sub_total) as subtotal'),
                        DB::raw('SUM(total) as total'),
                        DB::raw('COUNT(*) as cantidad')
                    )
                    ->first();
                    
                $compras = DB::table('compras')
                    ->where('id_empresa', $empresa->id)
                    ->where('estado', '!=', 'Anulada')
                    ->whereBetween('fecha', [$inicioMes, $finMes])
                    ->select(
                        DB::raw('SUM(sub_total) as subtotal'),
                        DB::raw('SUM(total) as total'),
                        DB::raw('COUNT(*) as cantidad')
                    )
                    ->first();
                
                return (object)[
                    'total_egresos' => ($egresosDirectos->total ?? 0) + ($compras->total ?? 0),
                    'subtotal_egresos' => ($egresosDirectos->subtotal ?? 0) + ($compras->subtotal ?? 0),
                    'num_egresos' => ($egresosDirectos->cantidad ?? 0) + ($compras->cantidad ?? 0)
                ];
            }
            
            // Egresos de los últimos 30 días por defecto
            $egresosDirectos = DB::table('egresos')
                ->where('id_empresa', $empresa->id)
                ->where('estado', '!=', 'Anulado')
                ->whereBetween('fecha', [$fechaActual->copy()->subDays(30), $fechaActual])
                ->select(
                    DB::raw('SUM(sub_total) as subtotal'),
                    DB::raw('SUM(total) as total'),
                    DB::raw('COUNT(*) as cantidad')
                )
                ->first();
                
            $compras = DB::table('compras')
                ->where('id_empresa', $empresa->id)
                ->where('estado', '!=', 'Anulada')
                ->whereBetween('fecha', [$fechaActual->copy()->subDays(30), $fechaActual])
                ->select(
                    DB::raw('SUM(sub_total) as subtotal'),
                    DB::raw('SUM(total) as total'),
                    DB::raw('COUNT(*) as cantidad')
                )
                ->first();
            
            return (object)[
                'total_egresos' => ($egresosDirectos->total ?? 0) + ($compras->total ?? 0),
                'subtotal_egresos' => ($egresosDirectos->subtotal ?? 0) + ($compras->subtotal ?? 0),
                'num_egresos' => ($egresosDirectos->cantidad ?? 0) + ($compras->cantidad ?? 0)
            ];
        }
        
        // Consultas relacionadas con rentabilidad
        if (strpos($tipoConsulta, 'rentabilidad') !== false || 
            strpos($tipoConsulta, 'ganancias') !== false || 
            strpos($tipoConsulta, 'utilidad') !== false || 
            strpos($tipoConsulta, 'margen') !== false) {
            
            return DB::table('ia_metricas_mensuales_empresas')
                ->where('id_empresa', $empresa->id)
                ->orderBy('fecha', 'desc')
                ->limit(3)
                ->get(['fecha', 'rentabilidad_porcentaje', 'rentabilidad_monto', 'ventas_sin_iva', 'egresos_sin_iva'])
                ->map(function($item) {
                    $item->fecha = Carbon::parse($item->fecha)->format('Y-m');
                    return $item;
                });
        }
        
        // Consultas relacionadas con cuentas por cobrar
        if ((strpos($tipoConsulta, 'cuenta') !== false || strpos($tipoConsulta, 'cxc') !== false) && 
            strpos($tipoConsulta, 'cobrar') !== false) {
            
            $metricas = DB::table('ia_metricas_mensuales_empresas')
                ->where('id_empresa', $empresa->id)
                ->orderBy('fecha', 'desc')
                ->first(['cxc_totales', 'cxc_vencidas', 'cxc_vencimiento_30_dias']);
                
            // Complementar con datos detallados de ventas pendientes
            $ventasPendientes = DB::table('ventas')
                ->where('id_empresa', $empresa->id)
                ->where('estado', '=', 'Pendiente')
                ->select(
                    DB::raw('COUNT(*) as cantidad_pendiente'),
                    DB::raw('MIN(fecha) as factura_mas_antigua'),
                    DB::raw('MAX(fecha) as factura_mas_reciente')
                )
                ->first();
                
            return (object)[
                'cxc_totales' => $metricas->cxc_totales ?? 0,
                'cxc_vencidas' => $metricas->cxc_vencidas ?? 0,
                'cxc_por_vencer_30_dias' => $metricas->cxc_vencimiento_30_dias ?? 0,
                'cantidad_facturas_pendientes' => $ventasPendientes->cantidad_pendiente ?? 0,
                'factura_mas_antigua' => $ventasPendientes->factura_mas_antigua ?? null,
                'factura_mas_reciente' => $ventasPendientes->factura_mas_reciente ?? null
            ];
        }
        
        // Consultas relacionadas con cuentas por pagar
        if ((strpos($tipoConsulta, 'cuenta') !== false || strpos($tipoConsulta, 'cxp') !== false) && 
            strpos($tipoConsulta, 'pagar') !== false) {
            
            $metricas = DB::table('ia_metricas_mensuales_empresas')
                ->where('id_empresa', $empresa->id)
                ->orderBy('fecha', 'desc')
                ->first(['cxp_totales', 'cxp_vencidas', 'cxp_vencimiento_30_dias']);
                
            // Complementar con datos detallados de compras pendientes
            $comprasPendientes = DB::table('compras')
                ->where('id_empresa', $empresa->id)
                ->where('estado', '=', 'Pendiente')
                ->select(
                    DB::raw('COUNT(*) as cantidad_pendiente'),
                    DB::raw('MIN(fecha) as factura_mas_antigua'),
                    DB::raw('MAX(fecha) as factura_mas_reciente')
                )
                ->first();
                
            return (object)[
                'cxp_totales' => $metricas->cxp_totales ?? 0,
                'cxp_vencidas' => $metricas->cxp_vencidas ?? 0,
                'cxp_por_vencer_30_dias' => $metricas->cxp_vencimiento_30_dias ?? 0,
                'cantidad_facturas_pendientes' => $comprasPendientes->cantidad_pendiente ?? 0,
                'factura_mas_antigua' => $comprasPendientes->factura_mas_antigua ?? null,
                'factura_mas_reciente' => $comprasPendientes->factura_mas_reciente ?? null
            ];
        }
        
        // Consultas relacionadas con inventario
        if (strpos($tipoConsulta, 'inventario') !== false || 
            strpos($tipoConsulta, 'stock') !== false || 
            strpos($tipoConsulta, 'existencia') !== false || 
            strpos($tipoConsulta, 'producto') !== false) {
            
            // Estadísticas generales del inventario
            $resumenInventario = DB::table('productos')
                ->where('productos.id_empresa', $empresa->id)
                ->where('productos.enable', 1)
                ->join('inventario', 'productos.id', '=', 'inventario.id_producto')
                ->select(
                    DB::raw('COUNT(DISTINCT productos.id) as total_productos'),
                    DB::raw('SUM(inventario.stock) as stock_total'),
                    DB::raw('AVG(productos.costo) as costo_promedio'),
                    DB::raw('SUM(productos.costo * inventario.stock) as valor_inventario')
                )
                ->first();
                
            // Productos con bajo stock
            $productosStockBajo = DB::table('productos')
                ->where('productos.id_empresa', $empresa->id)
                ->where('productos.enable', 1)
                ->join('inventario', 'productos.id', '=', 'inventario.id_producto')
                ->whereRaw('inventario.stock <= inventario.stock_minimo')
                ->count();
                
            // Productos sin existencias
            $productosSinExistencias = DB::table('productos')
                ->where('productos.id_empresa', $empresa->id)
                ->where('productos.enable', 1)
                ->join('inventario', 'productos.id', '=', 'inventario.id_producto')
                ->where('inventario.stock', '<=', 0)
                ->count();
                
            return (object)[
                'total_productos' => $resumenInventario->total_productos ?? 0,
                'stock_total' => $resumenInventario->stock_total ?? 0,
                'costo_promedio' => $resumenInventario->costo_promedio ?? 0,
                'valor_inventario' => $resumenInventario->valor_inventario ?? 0,
                'productos_bajo_stock' => $productosStockBajo ?? 0,
                'productos_sin_existencias' => $productosSinExistencias ?? 0
            ];
        }
        
        // Si no hay coincidencia específica, retornar un resumen general
        return DB::table('ia_metricas_mensuales_empresas')
            ->where('id_empresa', $empresa->id)
            ->orderBy('fecha', 'desc')
            ->first([
                'fecha',
                'ventas_sin_iva',
                'ventas_con_iva',
                'egresos_sin_iva',
                'egresos_con_iva',
                'costo_venta_sin_iva',
                'flujo_efectivo_sin_iva',
                'rentabilidad_porcentaje',
                'rentabilidad_monto',
                'cxc_totales',
                'cxp_totales'
            ]);
    }
    
    /**
     * Obtiene las métricas recientes de una empresa
     *
     * @param int $empresaId ID de la empresa
     * @param int $limit Número de meses a obtener
     * @return \Illuminate\Support\Collection Colección de métricas
     */
    public function obtenerMetricasRecientes($empresaId, $limit = 3)
    {
        return DB::table('ia_metricas_mensuales_empresas')
            ->where('id_empresa', $empresaId)
            ->orderBy('fecha', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Obtiene información básica de la empresa
     *
     * @param int $empresaId ID de la empresa
     * @return object|null Datos de la empresa
     */
    public function obtenerInformacionEmpresa($empresaId)
    {
        return DB::table('empresas')->find($empresaId);
    }
}