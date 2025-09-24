<?php

namespace App\Console\Commands\cliente360;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalcularClientes360Command extends Command
{
    protected $signature = 'cliente360:actualizar-agregados 
    {--cliente= : ID específico de cliente}
    {--tipo= : Tipo de agregado (rfm, productos, mensuales, fidelizacion, actividad, all)}
    {--masivo : Procesamiento masivo ultra-rápido (recomendado)}
    {--force : Forzar actualización aunque esté reciente}';

    protected $description = 'Actualiza las tablas agregadas para Cliente360';

    private $tiemposEjecucion = [];

    public function handle()
    {
        $tiempoInicio = microtime(true);

        $idCliente = $this->option('cliente');
        $tipo = $this->option('tipo') ?? 'all';
        $force = $this->option('force');
        $masivo = $this->option('masivo');

        $this->info('🚀 Iniciando actualización de agregados Cliente360...');

        if ($masivo && !$idCliente) {
            $this->procesarMasivo($tipo);
        } elseif ($idCliente) {
            $this->info("📊 Procesando cliente ID: {$idCliente}");
            $this->procesarCliente($idCliente, $tipo, $force);
        } else {
            $this->warn('⚠️  Modo individual detectado. Para mejor performance usa --masivo');
            $this->procesarTodosLosClientes($tipo, $force);
        }

        $tiempoTotal = microtime(true) - $tiempoInicio;

        $this->mostrarResumen($tiempoTotal, $tipo);

        return 0;
    }

    private function procesarMasivo($tipo)
    {
        if ($tipo === 'all' || $tipo === 'rfm') {
            $inicio = microtime(true);
            $this->info('📊 Calculando métricas RFM masivas...');
            $this->calcularRFMMasivo();
            $this->tiemposEjecucion['RFM'] = microtime(true) - $inicio;
        }

        if ($tipo === 'all' || $tipo === 'productos') {
            $inicio = microtime(true);
            $this->info('📦 Calculando productos top masivos...');
            $this->calcularProductosTopMasivo();
            $this->tiemposEjecucion['Productos'] = microtime(true) - $inicio;
        }

        if ($tipo === 'all' || $tipo === 'mensuales') {
            $inicio = microtime(true);
            $this->info('📅 Calculando ventas mensuales masivas...');
            $this->calcularVentasMensualesMasivo();
            $this->tiemposEjecucion['Ventas Mensuales'] = microtime(true) - $inicio;
        }

        if ($tipo === 'all' || $tipo === 'fidelizacion') {
            $inicio = microtime(true);
            $this->info('⭐ Calculando fidelización masiva...');
            $this->calcularFidelizacionMasivo();
            $this->tiemposEjecucion['Fidelización'] = microtime(true) - $inicio;
        }

        if ($tipo === 'all' || $tipo === 'actividad') {
            $inicio = microtime(true);
            $this->info('🔄 Calculando actividad reciente masiva...');
            $this->calcularActividadMasivo();
            $this->tiemposEjecucion['Actividad'] = microtime(true) - $inicio;
        }
    }

    private function mostrarResumen($tiempoTotal, $tipo)
    {
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📈 RESUMEN DE EJECUCIÓN');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!empty($this->tiemposEjecucion)) {
            $this->table(
                ['Proceso', 'Tiempo', 'Porcentaje'],
                collect($this->tiemposEjecucion)->map(function ($tiempo, $proceso) use ($tiempoTotal) {
                    return [
                        $proceso,
                        $this->formatearTiempo($tiempo),
                        number_format(($tiempo / $tiempoTotal) * 100, 1) . '%'
                    ];
                })->toArray()
            );
        }

        $this->newLine();
        $this->info("⏱️  Tiempo total: " . $this->formatearTiempo($tiempoTotal));
        $this->info("✅ Actualización completada exitosamente");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    private function formatearTiempo($segundos)
    {
        if ($segundos < 1) {
            return number_format($segundos * 1000, 0) . ' ms';
        } elseif ($segundos < 60) {
            return number_format($segundos, 2) . ' seg';
        } else {
            $minutos = floor($segundos / 60);
            $segs = $segundos % 60;
            return sprintf('%d min %d seg', $minutos, $segs);
        }
    }

    private function calcularRFMMasivo()
    {
        DB::beginTransaction();
        try {
            // Limpiar tabla
            DB::table('cliente_metricas_rfm')->truncate();

            // INSERT MASIVO - Todos los clientes en una query
            DB::statement("
                INSERT INTO cliente_metricas_rfm (
                    id_cliente, fecha_ultima_compra, dias_ultima_compra,
                    total_compras, compras_ultimos_12_meses, compras_ultimos_6_meses, compras_ultimos_3_meses,
                    total_gastado, gasto_ultimos_12_meses, gasto_ultimos_6_meses, gasto_ultimos_3_meses,
                    ticket_promedio, recency_score, frequency_score, monetary_score, health_score,
                    segmento_rfm, fecha_calculo, created_at, updated_at
                )
                SELECT 
                    v.id_cliente,
                    MAX(v.fecha) as fecha_ultima_compra,
                    DATEDIFF(NOW(), MAX(v.fecha)) as dias_ultima_compra,
                    COUNT(*) as total_compras,
                    SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 ELSE 0 END) as compras_12,
                    SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) as compras_6,
                    SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 1 ELSE 0 END) as compras_3,
                    SUM(v.total) as total_gastado,
                    SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN v.total ELSE 0 END) as gasto_12,
                    SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN v.total ELSE 0 END) as gasto_6,
                    SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN v.total ELSE 0 END) as gasto_3,
                    AVG(v.total) as ticket_promedio,
                    GREATEST(0, 100 - (DATEDIFF(NOW(), MAX(v.fecha)) * 2)) as recency_score,
                    LEAST(100, SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 ELSE 0 END) * 10) as frequency_score,
                    LEAST(100, (SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN v.total ELSE 0 END) / 1000) * 10) as monetary_score,
                    ROUND(
                        (GREATEST(0, 100 - (DATEDIFF(NOW(), MAX(v.fecha)) * 2)) * 0.5) + 
                        (LEAST(100, SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 ELSE 0 END) * 10) * 0.3) + 
                        (LEAST(100, (SUM(CASE WHEN v.fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN v.total ELSE 0 END) / 1000) * 10) * 0.2)
                    ) as health_score,
                    'Pending' as segmento_rfm,
                    NOW() as fecha_calculo,
                    NOW() as created_at,
                    NOW() as updated_at
                FROM ventas v
                INNER JOIN clientes c ON v.id_cliente = c.id
                WHERE v.estado != 'anulada'
                GROUP BY v.id_cliente
            ");

            // Actualizar segmentos en segundo paso
            $this->actualizarSegmentosRFM();

            DB::commit();
            $this->info('   ✓ RFM completado');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error en RFM: ' . $e->getMessage());
        }
    }

    private function actualizarSegmentosRFM()
    {
        DB::statement("
            UPDATE cliente_metricas_rfm
            SET segmento_rfm = CASE
                WHEN recency_score >= 80 AND frequency_score >= 80 AND monetary_score >= 80 THEN 'Champions'
                WHEN recency_score >= 60 AND frequency_score >= 60 THEN 'Loyal'
                WHEN recency_score >= 60 AND monetary_score >= 60 THEN 'Big Spenders'
                WHEN recency_score < 40 AND frequency_score >= 60 THEN 'At Risk'
                WHEN recency_score < 40 AND frequency_score < 40 THEN 'Lost'
                WHEN (recency_score + frequency_score + monetary_score) / 3 >= 50 THEN 'Potential'
                ELSE 'New'
            END
        ");
    }

    private function calcularProductosTopMasivo()
    {
        DB::beginTransaction();
        try {
            DB::table('cliente_productos_top')->truncate();

            // Insertar top 10 productos por cliente
            DB::statement("
                INSERT INTO cliente_productos_top (
                    id_cliente, id_producto, total_cantidad, total_monto, total_compras,
                    ultima_compra, dias_ultima_compra, precio_promedio, ranking,
                    created_at, updated_at
                )
                SELECT 
                    id_cliente,
                    id_producto,
                    total_cantidad,
                    total_monto,
                    total_compras,
                    ultima_compra,
                    DATEDIFF(NOW(), ultima_compra) as dias_ultima_compra,
                    precio_promedio,
                    ranking,
                    NOW(),
                    NOW()
                FROM (
                    SELECT 
                        v.id_cliente,
                        dv.id_producto,
                        SUM(dv.cantidad) as total_cantidad,
                        SUM(dv.total) as total_monto,
                        COUNT(DISTINCT v.id) as total_compras,
                        MAX(v.fecha) as ultima_compra,
                        AVG(dv.precio) as precio_promedio,
                        ROW_NUMBER() OVER (PARTITION BY v.id_cliente ORDER BY SUM(dv.cantidad) DESC) as ranking
                    FROM detalles_venta dv
                    INNER JOIN ventas v ON dv.id_venta = v.id
                    INNER JOIN clientes c ON v.id_cliente = c.id
                    INNER JOIN productos p ON dv.id_producto = p.id
                    WHERE v.estado != 'anulada'
                    GROUP BY v.id_cliente, dv.id_producto
                ) ranked
                WHERE ranking <= 10
            ");

            DB::commit();
            $this->info('   ✓ Top Productos completado');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error en Top Productos: ' . $e->getMessage());
        }
    }

    private function calcularVentasMensualesMasivo()
    {
        DB::beginTransaction();
        try {
            DB::table('cliente_ventas_mensuales')->truncate();

            DB::statement("
                INSERT INTO cliente_ventas_mensuales (
                    id_cliente, año, mes, cantidad_ventas, total_ventas, ticket_promedio,
                    productos_unicos, items_totales, es_mes_alto, variacion_mes_anterior,
                    created_at, updated_at
                )
                SELECT 
                    v.id_cliente,
                    YEAR(v.fecha) as año,
                    MONTH(v.fecha) as mes,
                    COUNT(*) as cantidad_ventas,
                    SUM(v.total) as total_ventas,
                    AVG(v.total) as ticket_promedio,
                    0 as productos_unicos,
                    0 as items_totales,
                    FALSE as es_mes_alto,
                    NULL as variacion_mes_anterior,
                    NOW(),
                    NOW()
                FROM ventas v
                INNER JOIN clientes c ON v.id_cliente = c.id
                WHERE v.estado != 'anulada'
                  AND v.fecha >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
                  AND v.fecha >= '1900-01-01'
                  AND v.fecha <= NOW()
                GROUP BY v.id_cliente, YEAR(v.fecha), MONTH(v.fecha)
                ORDER BY v.id_cliente, año, mes
            ");

            DB::commit();
            $this->info('   ✓ Ventas Mensuales completado');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error en Ventas Mensuales: ' . $e->getMessage());
        }
    }

    private function calcularFidelizacionMasivo()
    {
        DB::beginTransaction();
        try {
            DB::table('cliente_fidelizacion_snapshot')->truncate();

            DB::statement("
                INSERT INTO cliente_fidelizacion_snapshot (
                    id_cliente, puntos_disponibles, puntos_totales_ganados, puntos_totales_canjeados,
                    valor_puntos_canjeados, transacciones_ultimos_30_dias, 
                    puntos_ganados_ultimos_30_dias, puntos_canjeados_ultimos_30_dias,
                    fecha_ultima_ganancia, fecha_ultimo_canje, tasa_redencion,
                    fecha_snapshot, created_at, updated_at
                )
                SELECT 
                    pc.id_cliente,
                    pc.puntos_disponibles,
                    pc.puntos_totales_ganados,
                    pc.puntos_totales_canjeados,
                    pc.puntos_totales_canjeados * 0.1 as valor_puntos_canjeados,
                    COALESCE(t30.total_transacciones, 0) as transacciones_30_dias,
                    COALESCE(t30.puntos_ganados, 0) as puntos_ganados_30,
                    COALESCE(t30.puntos_canjeados, 0) as puntos_canjeados_30,
                    ug.ultima_ganancia,
                    uc.ultimo_canje,
                    CASE 
                        WHEN pc.puntos_totales_ganados > 0 
                        THEN ROUND((pc.puntos_totales_canjeados / pc.puntos_totales_ganados) * 100, 2)
                        ELSE 0 
                    END as tasa_redencion,
                    NOW(),
                    NOW(),
                    NOW()
                FROM puntos_cliente pc
                INNER JOIN clientes c ON pc.id_cliente = c.id
                LEFT JOIN (
                    SELECT 
                        id_cliente,
                        COUNT(*) as total_transacciones,
                        SUM(CASE WHEN tipo = 'ganancia' THEN puntos ELSE 0 END) as puntos_ganados,
                        SUM(CASE WHEN tipo = 'canje' THEN puntos ELSE 0 END) as puntos_canjeados
                    FROM transacciones_puntos
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY id_cliente
                ) t30 ON pc.id_cliente = t30.id_cliente
                LEFT JOIN (
                    SELECT id_cliente, MAX(created_at) as ultima_ganancia
                    FROM transacciones_puntos
                    WHERE tipo = 'ganancia'
                    GROUP BY id_cliente
                ) ug ON pc.id_cliente = ug.id_cliente
                LEFT JOIN (
                    SELECT id_cliente, MAX(created_at) as ultimo_canje
                    FROM transacciones_puntos
                    WHERE tipo = 'canje'
                    GROUP BY id_cliente
                ) uc ON pc.id_cliente = uc.id_cliente
            ");

            DB::commit();
            $this->info('   ✓ Fidelización completado');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error en Fidelización: ' . $e->getMessage());
        }
    }

    private function calcularActividadMasivo()
    {
        DB::beginTransaction();
        try {
            DB::table('cliente_actividad_reciente')->truncate();

            // Insertar últimas 10 ventas por cliente
            DB::statement("
                INSERT INTO cliente_actividad_reciente (
                    id_cliente, tipo_actividad, id_referencia, titulo, descripcion,
                    monto, icono, estado, fecha_actividad, created_at, updated_at
                )
                SELECT 
                    id_cliente,
                    'venta' as tipo_actividad,
                    id as id_referencia,
                    'Compra en tienda' as titulo,
                    CONCAT('Factura #', correlativo) as descripcion,
                    total as monto,
                    '$' as icono,
                    'completado' as estado,
                    fecha as fecha_actividad,
                    NOW(),
                    NOW()
                FROM (
                    SELECT 
                        v.*,
                        ROW_NUMBER() OVER (PARTITION BY v.id_cliente ORDER BY v.fecha DESC) as rn
                    FROM ventas v
                    INNER JOIN clientes c ON v.id_cliente = c.id
                    WHERE v.estado != 'anulada'
                      AND v.fecha >= '1900-01-01'
                      AND v.fecha <= NOW()
                ) ranked
                WHERE rn <= 10
            ");

            // Insertar últimas 10 transacciones de puntos por cliente
            DB::statement("
                INSERT INTO cliente_actividad_reciente (
                    id_cliente, tipo_actividad, id_referencia, titulo, descripcion,
                    puntos, icono, estado, fecha_actividad, created_at, updated_at
                )
                SELECT 
                    id_cliente,
                    CASE WHEN tipo = 'ganancia' THEN 'puntos_ganados' ELSE 'puntos_canjeados' END,
                    id as id_referencia,
                    CASE WHEN tipo = 'ganancia' THEN 'Ganancia de puntos' ELSE 'Canje de puntos' END,
                    descripcion,
                    puntos,
                    CASE WHEN tipo = 'ganancia' THEN '🎁' ELSE '💸' END,
                    CASE WHEN tipo = 'ganancia' THEN 'earned' ELSE 'redeemed' END,
                    created_at as fecha_actividad,
                    NOW(),
                    NOW()
                FROM (
                    SELECT 
                        tp.*,
                        ROW_NUMBER() OVER (PARTITION BY tp.id_cliente ORDER BY tp.created_at DESC) as rn
                    FROM transacciones_puntos tp
                    INNER JOIN clientes c ON tp.id_cliente = c.id
                ) ranked
                WHERE rn <= 10
            ");

            DB::commit();
            $this->info('   ✓ Actividad Reciente completado');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error en Actividad: ' . $e->getMessage());
        }
    }

    // ============================================
    // PROCESAMIENTO INDIVIDUAL (para cliente específico)
    // ============================================

    private function procesarCliente($idCliente, $tipo, $force)
    {
        $metodos = $this->obtenerMetodosSegunTipo($tipo);

        foreach ($metodos as $metodo => $nombre) {
            $this->info("  → Actualizando {$nombre}...");
            $this->$metodo($idCliente, $force);
        }
    }

    private function procesarTodosLosClientes($tipo, $force)
    {
        $clientes = DB::table('clientes')
            ->join('ventas', 'clientes.id', '=', 'ventas.id_cliente')
            ->where('ventas.estado', '!=', 'anulada')
            ->select('clientes.id')
            ->distinct()
            ->pluck('id');

        $total = $clientes->count();
        $this->info("Total de clientes a procesar: {$total}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($clientes as $idCliente) {
            $this->procesarCliente($idCliente, $tipo, $force);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function obtenerMetodosSegunTipo($tipo)
    {
        $todosLosMetodos = [
            'actualizarMetricasRFM' => 'Métricas RFM',
            'actualizarTopProductos' => 'Top Productos',
            'actualizarVentasMensuales' => 'Ventas Mensuales',
            'actualizarSnapshotFidelizacion' => 'Snapshot Fidelización',
            'actualizarActividadReciente' => 'Actividad Reciente'
        ];

        if ($tipo === 'all') {
            return $todosLosMetodos;
        }

        $mapeo = [
            'rfm' => ['actualizarMetricasRFM' => 'Métricas RFM'],
            'productos' => ['actualizarTopProductos' => 'Top Productos'],
            'mensuales' => ['actualizarVentasMensuales' => 'Ventas Mensuales'],
            'fidelizacion' => ['actualizarSnapshotFidelizacion' => 'Snapshot Fidelización'],
            'actividad' => ['actualizarActividadReciente' => 'Actividad Reciente']
        ];

        return $mapeo[$tipo] ?? $todosLosMetodos;
    }

    // ============================================
    // ACTUALIZAR MÉTRICAS RFM
    // ============================================
    private function actualizarMetricasRFM($idCliente, $force)
    {
        if (!$force) {
            $ultimaActualizacion = DB::table('cliente_metricas_rfm')
                ->where('id_cliente', $idCliente)
                ->value('fecha_calculo');

            if ($ultimaActualizacion && Carbon::parse($ultimaActualizacion)->diffInHours(now()) < 24) {
                return;
            }
        }

        // UNA SOLA QUERY con todo pre-calculado
        $metricas = DB::table('ventas')
            ->join('clientes', 'ventas.id_cliente', '=', 'clientes.id')
            ->where('id_cliente', $idCliente)
            ->where('estado', '!=', 'anulada')
            ->selectRaw('
                COUNT(*) as total_compras,
                SUM(total) as total_gastado,
                AVG(total) as ticket_promedio,
                MAX(fecha) as fecha_ultima_compra,
                SUM(CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN 1 ELSE 0 END) as compras_12_meses,
                SUM(CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) as compras_6_meses,
                SUM(CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN 1 ELSE 0 END) as compras_3_meses,
                SUM(CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 12 MONTH) THEN total ELSE 0 END) as gasto_12_meses,
                SUM(CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN total ELSE 0 END) as gasto_6_meses,
                SUM(CASE WHEN fecha >= DATE_SUB(NOW(), INTERVAL 3 MONTH) THEN total ELSE 0 END) as gasto_3_meses
            ')
            ->first();

        if (!$metricas || $metricas->total_compras == 0) {
            return;
        }

        $diasUltimaCompra = now()->diffInDays(Carbon::parse($metricas->fecha_ultima_compra));

        $recencyScore = max(0, 100 - ($diasUltimaCompra * 2));
        $frequencyScore = min(100, $metricas->compras_12_meses * 10);
        $monetaryScore = min(100, ($metricas->gasto_12_meses / 1000) * 10);
        $healthScore = round(($recencyScore * 0.5) + ($frequencyScore * 0.3) + ($monetaryScore * 0.2));

        $segmento = $this->determinarSegmentoRFM($recencyScore, $frequencyScore, $monetaryScore);

        DB::table('cliente_metricas_rfm')->updateOrInsert(
            ['id_cliente' => $idCliente],
            [
                'fecha_ultima_compra' => $metricas->fecha_ultima_compra,
                'dias_ultima_compra' => $diasUltimaCompra,
                'total_compras' => $metricas->total_compras,
                'compras_ultimos_12_meses' => $metricas->compras_12_meses,
                'compras_ultimos_6_meses' => $metricas->compras_6_meses,
                'compras_ultimos_3_meses' => $metricas->compras_3_meses,
                'total_gastado' => $metricas->total_gastado,
                'gasto_ultimos_12_meses' => $metricas->gasto_12_meses,
                'gasto_ultimos_6_meses' => $metricas->gasto_6_meses,
                'gasto_ultimos_3_meses' => $metricas->gasto_3_meses,
                'ticket_promedio' => $metricas->ticket_promedio,
                'recency_score' => $recencyScore,
                'frequency_score' => $frequencyScore,
                'monetary_score' => $monetaryScore,
                'health_score' => $healthScore,
                'segmento_rfm' => $segmento,
                'fecha_calculo' => now(),
                'updated_at' => now()
            ]
        );
    }

    // ============================================
    // ACTUALIZAR TOP PRODUCTOS
    // ============================================
    private function actualizarTopProductos($idCliente, $force)
    {
        $topProducts = DB::table('detalles_venta as dv')
            ->join('ventas as v', 'dv.id_venta', '=', 'v.id')
            ->join('clientes as c', 'v.id_cliente', '=', 'c.id')
            ->join('productos as p', 'dv.id_producto', '=', 'p.id')
            ->where('v.id_cliente', $idCliente)
            ->where('v.estado', '!=', 'anulada')
            ->select(
                'dv.id_producto',
                DB::raw('SUM(dv.cantidad) as total_cantidad'),
                DB::raw('SUM(dv.total) as total_monto'),
                DB::raw('COUNT(DISTINCT v.id) as total_compras'),
                DB::raw('MAX(v.fecha) as ultima_compra'),
                DB::raw('AVG(dv.precio) as precio_promedio')
            )
            ->groupBy('dv.id_producto')
            ->orderBy('total_cantidad', 'desc')
            ->limit(10)
            ->get();

        // Limpiar productos anteriores del cliente
        DB::table('cliente_productos_top')
            ->where('id_cliente', $idCliente)
            ->delete();

        // Insertar nuevos top productos
        $ranking = 1;
        foreach ($topProducts as $producto) {
            $diasUltimaCompra = $producto->ultima_compra
                ? now()->diffInDays(Carbon::parse($producto->ultima_compra))
                : null;

            DB::table('cliente_productos_top')->insert([
                'id_cliente' => $idCliente,
                'id_producto' => $producto->id_producto,
                'total_cantidad' => $producto->total_cantidad,
                'total_monto' => $producto->total_monto,
                'total_compras' => $producto->total_compras,
                'ultima_compra' => $producto->ultima_compra,
                'dias_ultima_compra' => $diasUltimaCompra,
                'precio_promedio' => $producto->precio_promedio ?? 0,
                'ranking' => $ranking,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $ranking++;
        }
    }

    // ============================================
    // ACTUALIZAR VENTAS MENSUALES
    // ============================================
    private function actualizarVentasMensuales($idCliente, $force)
    {
        // Calcular ventas de los últimos 24 meses
        $ventasMensuales = DB::table('ventas')
            ->join('clientes', 'ventas.id_cliente', '=', 'clientes.id')
            ->where('id_cliente', $idCliente)
            ->where('estado', '!=', 'anulada')
            ->where('fecha', '>=', now()->subMonths(24))
            ->where('fecha', '>=', '1900-01-01')
            ->where('fecha', '<=', now())
            ->select(
                DB::raw('YEAR(fecha) as año'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('COUNT(*) as cantidad_ventas'),
                DB::raw('SUM(total) as total_ventas'),
                DB::raw('AVG(total) as ticket_promedio')
            )
            ->groupBy(DB::raw('YEAR(fecha), MONTH(fecha)'))
            ->get();

        if ($ventasMensuales->isEmpty()) {
            return;
        }

        // Calcular max para determinar meses altos
        $maxVenta = $ventasMensuales->max('total_ventas');
        $umbralAlto = $maxVenta * 0.8;

        // Limpiar datos anteriores
        DB::table('cliente_ventas_mensuales')
            ->where('id_cliente', $idCliente)
            ->delete();

        // Insertar datos mensuales
        $ventasArray = $ventasMensuales->keyBy(function ($item) {
            return $item->año . '-' . $item->mes;
        });

        foreach ($ventasMensuales as $index => $venta) {
            // Calcular variación respecto al mes anterior
            $variacion = null;
            if ($index > 0) {
                $ventaAnterior = $ventasMensuales[$index - 1];
                if ($ventaAnterior->total_ventas > 0) {
                    $variacion = (($venta->total_ventas - $ventaAnterior->total_ventas) / $ventaAnterior->total_ventas) * 100;
                }
            }

            // Contar productos únicos en ese mes
            $productosUnicos = DB::table('detalles_venta as dv')
                ->join('ventas as v', 'dv.id_venta', '=', 'v.id')
                ->join('productos as p', 'dv.id_producto', '=', 'p.id')
                ->where('v.id_cliente', $idCliente)
                ->where('v.estado', '!=', 'anulada')
                ->whereYear('v.fecha', $venta->año)
                ->whereMonth('v.fecha', $venta->mes)
                ->where('v.fecha', '>=', '1900-01-01')
                ->where('v.fecha', '<=', now())
                ->distinct('dv.id_producto')
                ->count('dv.id_producto');

            $itemsTotales = DB::table('detalles_venta as dv')
                ->join('ventas as v', 'dv.id_venta', '=', 'v.id')
                ->join('productos as p', 'dv.id_producto', '=', 'p.id')
                ->where('v.id_cliente', $idCliente)
                ->where('v.estado', '!=', 'anulada')
                ->whereYear('v.fecha', $venta->año)
                ->whereMonth('v.fecha', $venta->mes)
                ->where('v.fecha', '>=', '1900-01-01')
                ->where('v.fecha', '<=', now())
                ->sum('dv.cantidad');

            DB::table('cliente_ventas_mensuales')->insert([
                'id_cliente' => $idCliente,
                'año' => $venta->año,
                'mes' => $venta->mes,
                'cantidad_ventas' => $venta->cantidad_ventas,
                'total_ventas' => $venta->total_ventas,
                'ticket_promedio' => $venta->ticket_promedio,
                'productos_unicos' => $productosUnicos,
                'items_totales' => $itemsTotales,
                'es_mes_alto' => $venta->total_ventas >= $umbralAlto,
                'variacion_mes_anterior' => $variacion,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    // ============================================
    // ACTUALIZAR SNAPSHOT FIDELIZACIÓN
    // ============================================
    private function actualizarSnapshotFidelizacion($idCliente, $force)
    {
        $puntosCliente = DB::table('puntos_cliente')
            ->join('clientes', 'puntos_cliente.id_cliente', '=', 'clientes.id')
            ->where('puntos_cliente.id_cliente', $idCliente)
            ->select('puntos_cliente.*')
            ->first();

        if (!$puntosCliente) {
            return;
        }

        // Calcular métricas de últimos 30 días
        $hace30Dias = now()->subDays(30);

        $transacciones30Dias = DB::table('transacciones_puntos')
            ->where('id_cliente', $idCliente)
            ->where('created_at', '>=', $hace30Dias)
            ->get();

        $puntosGanados30 = $transacciones30Dias->where('tipo', 'ganancia')->sum('puntos');
        $puntosCanjeados30 = $transacciones30Dias->where('tipo', 'canje')->sum('puntos');

        $ultimaGanancia = DB::table('transacciones_puntos')
            ->where('id_cliente', $idCliente)
            ->where('tipo', 'ganancia')
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        $ultimoCanje = DB::table('transacciones_puntos')
            ->where('id_cliente', $idCliente)
            ->where('tipo', 'canje')
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        $tasaRedencion = $puntosCliente->puntos_totales_ganados > 0
            ? ($puntosCliente->puntos_totales_canjeados / $puntosCliente->puntos_totales_ganados) * 100
            : 0;

        DB::table('cliente_fidelizacion_snapshot')->updateOrInsert(
            ['id_cliente' => $idCliente],
            [
                'puntos_disponibles' => $puntosCliente->puntos_disponibles ?? 0,
                'puntos_totales_ganados' => $puntosCliente->puntos_totales_ganados ?? 0,
                'puntos_totales_canjeados' => $puntosCliente->puntos_totales_canjeados ?? 0,
                'valor_puntos_canjeados' => ($puntosCliente->puntos_totales_canjeados ?? 0) * 0.1,
                'transacciones_ultimos_30_dias' => $transacciones30Dias->count(),
                'puntos_ganados_ultimos_30_dias' => $puntosGanados30,
                'puntos_canjeados_ultimos_30_dias' => $puntosCanjeados30,
                'fecha_ultima_ganancia' => $ultimaGanancia,
                'fecha_ultimo_canje' => $ultimoCanje,
                'tasa_redencion' => round($tasaRedencion, 2),
                'fecha_snapshot' => now(),
                'updated_at' => now()
            ]
        );
    }

    // ============================================
    // ACTUALIZAR ACTIVIDAD RECIENTE
    // ============================================
    private function actualizarActividadReciente($idCliente, $force)
    {
        // Limpiar actividades anteriores
        DB::table('cliente_actividad_reciente')
            ->where('id_cliente', $idCliente)
            ->delete();

        // Obtener últimas ventas (solo fechas válidas)
        $ventas = DB::table('ventas')
            ->where('id_cliente', $idCliente)
            ->where('estado', '!=', 'anulada')
            ->where('fecha', '>=', '1900-01-01')
            ->where('fecha', '<=', now())
            ->orderBy('fecha', 'desc')
            ->limit(10)
            ->get();

        foreach ($ventas as $venta) {
            DB::table('cliente_actividad_reciente')->insert([
                'id_cliente' => $idCliente,
                'tipo_actividad' => 'venta',
                'id_referencia' => $venta->id,
                'titulo' => 'Compra en tienda',
                'descripcion' => 'Factura #' . $venta->correlativo,
                'monto' => $venta->total,
                'icono' => '$',
                'estado' => 'completado',
                'fecha_actividad' => $venta->fecha,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Obtener últimas transacciones de puntos
        $transacciones = DB::table('transacciones_puntos')
            ->where('id_cliente', $idCliente)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($transacciones as $transaccion) {
            DB::table('cliente_actividad_reciente')->insert([
                'id_cliente' => $idCliente,
                'tipo_actividad' => $transaccion->tipo === 'ganancia' ? 'puntos_ganados' : 'puntos_canjeados',
                'id_referencia' => $transaccion->id,
                'titulo' => $transaccion->tipo === 'ganancia' ? 'Ganancia de puntos' : 'Canje de puntos',
                'descripcion' => $transaccion->descripcion,
                'puntos' => $transaccion->puntos,
                'icono' => $transaccion->tipo === 'ganancia' ? '🎁' : '💸',
                'estado' => $transaccion->tipo === 'ganancia' ? 'earned' : 'redeemed',
                'fecha_actividad' => $transaccion->created_at,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    private function determinarSegmentoRFM($recencyScore, $frequencyScore, $monetaryScore)
    {
        $avgScore = ($recencyScore + $frequencyScore + $monetaryScore) / 3;

        if ($recencyScore >= 80 && $frequencyScore >= 80 && $monetaryScore >= 80) {
            return 'Champions';
        } elseif ($recencyScore >= 60 && $frequencyScore >= 60) {
            return 'Loyal';
        } elseif ($recencyScore >= 60 && $monetaryScore >= 60) {
            return 'Big Spenders';
        } elseif ($recencyScore < 40 && $frequencyScore >= 60) {
            return 'At Risk';
        } elseif ($recencyScore < 40 && $frequencyScore < 40) {
            return 'Lost';
        } elseif ($avgScore >= 50) {
            return 'Potential';
        } else {
            return 'New';
        }
    }
}
