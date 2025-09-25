<?php

namespace App\Services\Cliente360;

use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Models\FidelizacionClientes\PuntosCliente;
use App\Models\FidelizacionClientes\TransaccionPuntos;
use Illuminate\Support\Facades\DB;

class Cliente360Service
{
    /**
     * Obtener datos del cliente usando tablas agregadas (OPTIMIZADO)
     */
    public function getClienteData($id_cliente)
    {
        $cliente = Cliente::with([
            'contactos',
            'puntosCliente',
            'tipoCliente.tipoBase',
            'actividadEconomica',
            'empresa'
        ])->find($id_cliente);

        if (!$cliente) {
            return null;
        }

        // Usar tablas agregadas en lugar de calcular en tiempo real
        $ventasMetrics = $this->getVentasMetrics($id_cliente);
        $fidelizacionData = $this->getFidelizacionData($id_cliente);
        $transacciones = $this->getTransacciones($id_cliente);
        $topProducts = $this->getTopProducts($id_cliente);
        $ventasMensuales = $this->getVentasMensuales($id_cliente);
        $categorias = $this->getCategoriasPreferidas($id_cliente);

        return [
            'cliente' => $cliente,
            'metrics' => $ventasMetrics,
            'fidelizacion' => $fidelizacionData,
            'transacciones' => $transacciones,
            'topProducts' => $topProducts,
            'ventasMensuales' => $ventasMensuales,
            'categorias' => $categorias
        ];
    }

    /**
     * Obtener métricas de ventas desde tabla agregada (OPTIMIZADO)
     */
    private function getVentasMetrics($id_cliente)
    {
        // Consulta simple a tabla agregada
        $metricas = DB::table('cliente_metricas_rfm')
            ->where('id_cliente', $id_cliente)
            ->first();

        if (!$metricas) {
            // Si no existe, calcular y guardar
            return $this->calcularYGuardarMetricasRFM($id_cliente);
        }

        // Verificar si los datos están desactualizados (más de 2 días para ser menos agresivo)
        $fechaCalculo = \Carbon\Carbon::parse($metricas->fecha_calculo);
        if ($fechaCalculo->diffInHours(now()) > 48) {
            return $this->calcularYGuardarMetricasRFM($id_cliente);
        }

        return [
            'clv' => $metricas->total_gastado,
            'averagePurchase' => $metricas->ticket_promedio,
            'healthScore' => $metricas->health_score,
            'recency' => $metricas->dias_ultima_compra,
            'frequency' => $metricas->compras_ultimos_12_meses,
            'monetary' => $metricas->gasto_ultimos_12_meses,
            'totalVentas' => $metricas->total_gastado,
            'cantidadVentas' => $metricas->total_compras,
            'segmento' => $metricas->segmento_rfm
        ];
    }

    /**
     * Obtener datos de fidelización desde snapshot (OPTIMIZADO)
     */
    private function getFidelizacionData($id_cliente)
    {
        $snapshot = DB::table('cliente_fidelizacion_snapshot')
            ->where('id_cliente', $id_cliente)
            ->first();

        if (!$snapshot) {
            return $this->calcularYGuardarSnapshotFidelizacion($id_cliente);
        }

        // Verificar si los datos están desactualizados (más de 1 día)
        $fechaSnapshot = \Carbon\Carbon::parse($snapshot->fecha_snapshot);
        if ($fechaSnapshot->diffInHours(now()) > 24) {
            return $this->calcularYGuardarSnapshotFidelizacion($id_cliente);
        }

        return [
            'balance' => $snapshot->puntos_disponibles,
            'redeemed' => $snapshot->puntos_totales_canjeados,
            'saved' => $snapshot->valor_puntos_canjeados,
            'puntosDisponibles' => $snapshot->puntos_disponibles,
            'puntosTotalesGanados' => $snapshot->puntos_totales_ganados,
            'puntosTotalesCanjeados' => $snapshot->puntos_totales_canjeados,
            'fechaUltimaActividad' => $snapshot->fecha_ultima_ganancia,
            'tasaRedencion' => $snapshot->tasa_redencion,
            'actividadReciente' => [
                'transacciones_30_dias' => $snapshot->transacciones_ultimos_30_dias,
                'puntos_ganados_30_dias' => $snapshot->puntos_ganados_ultimos_30_dias,
                'puntos_canjeados_30_dias' => $snapshot->puntos_canjeados_ultimos_30_dias
            ]
        ];
    }

    /**
     * Obtener transacciones desde caché (OPTIMIZADO)
     */
    private function getTransacciones($id_cliente)
    {
        // Leer directamente de la tabla de actividad reciente
        $actividades = DB::table('cliente_actividad_reciente')
            ->where('id_cliente', $id_cliente)
            ->orderBy('fecha_actividad', 'desc')
            ->limit(10)
            ->get();

        if ($actividades->isEmpty()) {
            // Si no hay caché, construir y guardar
            return $this->construirYGuardarActividadReciente($id_cliente);
        }

        return $actividades->map(function ($actividad) {
            return [
                'icon' => $actividad->icono,
                'type' => $actividad->tipo_actividad,
                'title' => $actividad->titulo,
                'date' => \Carbon\Carbon::parse($actividad->fecha_actividad)->format('d M Y, h:i A'),
                'reference' => $actividad->descripcion,
                'amount' => $actividad->monto ?? $actividad->puntos,
                'status' => $actividad->estado,
                'fecha' => $actividad->fecha_actividad
            ];
        });
    }

    /**
     * Obtener top productos desde tabla agregada (OPTIMIZADO)
     */
    private function getTopProducts($id_cliente)
    {
        $topProducts = DB::table('cliente_productos_top as cpt')
            ->join('productos as p', 'cpt.id_producto', '=', 'p.id')
            ->where('cpt.id_cliente', $id_cliente)
            ->where('cpt.ranking', '<=', 5)
            ->orderBy('cpt.ranking', 'asc')
            ->select(
                'p.nombre',
                'p.codigo',
                'cpt.total_cantidad',
                'cpt.total_monto',
                'cpt.total_compras',
                'cpt.dias_ultima_compra',
                'cpt.ranking'
            )
            ->get();

        if ($topProducts->isEmpty()) {
            return $this->calcularYGuardarTopProductos($id_cliente);
        }

        return $topProducts->map(function ($producto) {
            return [
                'rank' => $producto->ranking,
                'emoji' => '📦',
                'name' => $producto->nombre,
                'purchases' => $producto->total_compras,
                'lastPurchase' => $producto->dias_ultima_compra ? "hace {$producto->dias_ultima_compra} días" : 'Nunca',
                'total' => $producto->total_monto
            ];
        });
    }

    /**
     * Obtener ventas mensuales desde tabla agregada (OPTIMIZADO)
     */
    private function getVentasMensuales($id_cliente)
    {
        $ventasMensuales = DB::table('cliente_ventas_mensuales')
            ->where('id_cliente', $id_cliente)
            ->where('año', '>=', now()->subMonths(12)->year)
            ->orderBy('año', 'asc')
            ->orderBy('mes', 'asc')
            ->get();

        if ($ventasMensuales->isEmpty()) {
            return $this->calcularYGuardarVentasMensuales($id_cliente);
        }

        $meses = [
            1 => 'Ene',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Abr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Ago',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dic'
        ];

        $ventasPorMes = [];
        $maxVenta = $ventasMensuales->max('total_ventas') ?: 1;

        for ($i = 11; $i >= 0; $i--) {
            $fecha = now()->subMonths($i);
            $mes = $fecha->month;
            $año = $fecha->year;

            $venta = $ventasMensuales->where('mes', $mes)->where('año', $año)->first();
            $total = $venta ? $venta->total_ventas : 0;
            $height = $maxVenta > 0 ? round(($total / $maxVenta) * 100) : 0;

            $ventasPorMes[] = [
                'month' => $meses[$mes],
                'amount' => round($total, 2),
                'height' => $height,
                'high' => $venta ? $venta->es_mes_alto : false
            ];
        }

        return $ventasPorMes;
    }

    private function getCategoriasPreferidas($id_cliente)
    {
        $categorias = DB::table('cliente_categorias_preferidas')
            ->where('id_cliente', $id_cliente)
            ->orderBy('ranking', 'asc')
            ->limit(5)
            ->get();

        if ($categorias->isEmpty()) {
            return $this->calcularYGuardarCategoriasPreferidas($id_cliente);
        }

        return $categorias->map(function ($categoria) {
            return [
                'rank' => $categoria->ranking,
                'name' => $categoria->nombre_categoria,
                'percentage' => $categoria->porcentaje_gasto,
                'total' => $categoria->total_gastado,
                'products' => $categoria->cantidad_productos,
                'purchases' => $categoria->total_compras,
                'emoji' => '🏷️'
            ];
        });
    }

    // ============================================
    // MÉTODOS DE CÁLCULO Y GUARDADO (Fallback)
    // ============================================

    private function calcularYGuardarMetricasRFM($id_cliente)
    {
        $ventas = Venta::where('id_cliente', $id_cliente)
            ->where('estado', '!=', 'anulada')
            ->get();

        $totalVentas = $ventas->sum('total');
        $cantidadVentas = $ventas->count();
        $promedioCompra = $cantidadVentas > 0 ? $totalVentas / $cantidadVentas : 0;

        $ultimaVenta = $ventas->sortByDesc('fecha')->first();
        $fechaUltimaCompra = null;
        $diasUltimaCompra = null;

        if ($ultimaVenta) {
            $fechaUltimaCompra = $ultimaVenta->fecha;
            $diasUltimaCompra = now()->diffInDays($fechaUltimaCompra);
        }

        $fechaHace12Meses = now()->subMonths(12);
        $fechaHace6Meses = now()->subMonths(6);
        $fechaHace3Meses = now()->subMonths(3);

        $compras12Meses = $ventas->where('fecha', '>=', $fechaHace12Meses)->count();
        $compras6Meses = $ventas->where('fecha', '>=', $fechaHace6Meses)->count();
        $compras3Meses = $ventas->where('fecha', '>=', $fechaHace3Meses)->count();

        $gasto12Meses = $ventas->where('fecha', '>=', $fechaHace12Meses)->sum('total');
        $gasto6Meses = $ventas->where('fecha', '>=', $fechaHace6Meses)->sum('total');
        $gasto3Meses = $ventas->where('fecha', '>=', $fechaHace3Meses)->sum('total');

        // Calcular scores
        $recencyScore = $diasUltimaCompra !== null ? max(0, 100 - ($diasUltimaCompra * 2)) : 0;
        $frequencyScore = min(100, $compras12Meses * 10);
        $monetaryScore = min(100, ($gasto12Meses / 1000) * 10);
        $healthScore = round(($recencyScore * 0.5) + ($frequencyScore * 0.3) + ($monetaryScore * 0.2));

        // Determinar segmento RFM
        $segmento = $this->determinarSegmentoRFM($recencyScore, $frequencyScore, $monetaryScore);

        // Guardar en tabla agregada
        DB::table('cliente_metricas_rfm')->updateOrInsert(
            ['id_cliente' => $id_cliente],
            [
                'fecha_ultima_compra' => $fechaUltimaCompra,
                'dias_ultima_compra' => $diasUltimaCompra,
                'total_compras' => $cantidadVentas,
                'compras_ultimos_12_meses' => $compras12Meses,
                'compras_ultimos_6_meses' => $compras6Meses,
                'compras_ultimos_3_meses' => $compras3Meses,
                'total_gastado' => $totalVentas,
                'gasto_ultimos_12_meses' => $gasto12Meses,
                'gasto_ultimos_6_meses' => $gasto6Meses,
                'gasto_ultimos_3_meses' => $gasto3Meses,
                'ticket_promedio' => $promedioCompra,
                'recency_score' => $recencyScore,
                'frequency_score' => $frequencyScore,
                'monetary_score' => $monetaryScore,
                'health_score' => $healthScore,
                'segmento_rfm' => $segmento,
                'fecha_calculo' => now(),
                'updated_at' => now()
            ]
        );

        return [
            'clv' => $totalVentas,
            'averagePurchase' => round($promedioCompra, 2),
            'healthScore' => $healthScore,
            'recency' => $diasUltimaCompra,
            'frequency' => $compras12Meses,
            'monetary' => round($gasto12Meses, 2),
            'totalVentas' => $totalVentas,
            'cantidadVentas' => $cantidadVentas,
            'segmento' => $segmento
        ];
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

    private function calcularYGuardarSnapshotFidelizacion($id_cliente)
    {
        $puntosCliente = PuntosCliente::where('id_cliente', $id_cliente)->first();

        if (!$puntosCliente) {
            return [
                'balance' => 0,
                'redeemed' => 0,
                'saved' => 0,
                'puntosDisponibles' => 0,
                'puntosTotalesGanados' => 0,
                'puntosTotalesCanjeados' => 0,
                'fechaUltimaActividad' => null,
                'tasaRedencion' => 0,
                'actividadReciente' => [
                    'transacciones_30_dias' => 0,
                    'puntos_ganados_30_dias' => 0,
                    'puntos_canjeados_30_dias' => 0
                ]
            ];
        }

        // Calcular métricas de últimos 30 días
        $hace30Dias = now()->subDays(30);
        $transacciones30Dias = TransaccionPuntos::where('id_cliente', $id_cliente)
            ->where('created_at', '>=', $hace30Dias)
            ->get();

        $puntosGanados30 = $transacciones30Dias->where('tipo', 'ganancia')->sum('puntos');
        $puntosCanjeados30 = $transacciones30Dias->where('tipo', 'canje')->sum('puntos');

        $ultimaGanancia = DB::table('transacciones_puntos')
            ->where('id_cliente', $id_cliente)
            ->where('tipo', 'ganancia')
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        $ultimoCanje = DB::table('transacciones_puntos')
            ->where('id_cliente', $id_cliente)
            ->where('tipo', 'canje')
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        $tasaRedencion = $puntosCliente->puntos_totales_ganados > 0
            ? ($puntosCliente->puntos_totales_canjeados / $puntosCliente->puntos_totales_ganados) * 100
            : 0;

        DB::table('cliente_fidelizacion_snapshot')->updateOrInsert(
            ['id_cliente' => $id_cliente],
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

        return [
            'balance' => $puntosCliente->puntos_disponibles,
            'redeemed' => $puntosCliente->puntos_totales_canjeados,
            'saved' => round(($puntosCliente->puntos_totales_canjeados ?? 0) * 0.1, 2),
            'puntosDisponibles' => $puntosCliente->puntos_disponibles,
            'puntosTotalesGanados' => $puntosCliente->puntos_totales_ganados,
            'puntosTotalesCanjeados' => $puntosCliente->puntos_totales_canjeados,
            'fechaUltimaActividad' => $ultimaGanancia,
            'tasaRedencion' => round($tasaRedencion, 2),
            'actividadReciente' => [
                'transacciones_30_dias' => $transacciones30Dias->count(),
                'puntos_ganados_30_dias' => $puntosGanados30,
                'puntos_canjeados_30_dias' => $puntosCanjeados30
            ]
        ];
    }

    private function calcularYGuardarTopProductos($id_cliente)
    {
        // Usar la misma columna que el comando: 'precio' no 'precio_unitario'
        $topProducts = DB::table('detalles_venta as dv')
            ->join('ventas as v', 'dv.id_venta', '=', 'v.id')
            ->join('clientes as c', 'v.id_cliente', '=', 'c.id')  // Agregado para consistencia
            ->join('productos as p', 'dv.id_producto', '=', 'p.id')  // Agregado para consistencia
            ->where('v.id_cliente', $id_cliente)
            ->where('v.estado', '!=', 'anulada')
            ->select(
                'dv.id_producto',
                'p.nombre',  // Obtener nombre directamente
                DB::raw('SUM(dv.cantidad) as total_cantidad'),
                DB::raw('SUM(dv.total) as total_monto'),
                DB::raw('COUNT(DISTINCT v.id) as total_compras'),
                DB::raw('MAX(v.fecha) as ultima_compra'),
                DB::raw('AVG(dv.precio) as precio_promedio')  // Cambiado de precio_unitario a precio
            )
            ->groupBy('dv.id_producto', 'p.nombre')
            ->orderBy('total_cantidad', 'desc')
            ->limit(10)
            ->get();

        // Limpiar productos anteriores del cliente
        DB::table('cliente_productos_top')
            ->where('id_cliente', $id_cliente)
            ->delete();

        // Insertar nuevos top productos
        $ranking = 1;
        $productos = [];

        foreach ($topProducts as $producto) {
            $diasUltimaCompra = $producto->ultima_compra
                ? now()->diffInDays(\Carbon\Carbon::parse($producto->ultima_compra))
                : null;

            DB::table('cliente_productos_top')->insert([
                'id_cliente' => $id_cliente,
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

            // Agregar al array de respuesta
            $productos[] = [
                'rank' => $ranking,
                'emoji' => '📦',
                'name' => $producto->nombre,  // Ya tenemos el nombre
                'purchases' => $producto->total_compras,
                'lastPurchase' => $diasUltimaCompra ? "hace {$diasUltimaCompra} días" : 'Nunca',
                'total' => $producto->total_monto
            ];

            $ranking++;
        }

        return collect($productos);
    }

    private function calcularYGuardarVentasMensuales($id_cliente)
    {
        // Agregar validación de fechas como en el comando
        $ventasMensuales = DB::table('ventas')
            ->where('id_cliente', $id_cliente)
            ->where('estado', '!=', 'anulada')
            ->where('fecha', '>=', now()->subMonths(24))
            ->where('fecha', '>=', '1900-01-01')  // Validación agregada
            ->where('fecha', '<=', now())        // Validación agregada
            ->select(
                DB::raw('YEAR(fecha) as año'),
                DB::raw('MONTH(fecha) as mes'),
                DB::raw('COUNT(*) as cantidad_ventas'),
                DB::raw('SUM(total) as total_ventas'),
                DB::raw('AVG(total) as ticket_promedio')
            )
            ->groupBy(DB::raw('YEAR(fecha), MONTH(fecha)'))
            ->orderBy(DB::raw('YEAR(fecha), MONTH(fecha)'))  // Ordenar correctamente
            ->get();

        if ($ventasMensuales->isEmpty()) {
            return [];
        }

        $maxVenta = $ventasMensuales->max('total_ventas');
        $umbralAlto = $maxVenta * 0.8;

        // Limpiar datos anteriores
        DB::table('cliente_ventas_mensuales')
            ->where('id_cliente', $id_cliente)
            ->delete();

        // Insertar datos mensuales con cálculo mejorado de variación
        $ventasArray = $ventasMensuales->values();

        foreach ($ventasArray as $index => $venta) {
            $variacion = null;
            if ($index > 0) {
                $ventaAnterior = $ventasArray[$index - 1];
                if ($ventaAnterior->total_ventas > 0) {
                    $variacion = (($venta->total_ventas - $ventaAnterior->total_ventas) / $ventaAnterior->total_ventas) * 100;
                }
            }

            // Calcular productos únicos e items totales como en el comando
            $productosUnicos = DB::table('detalles_venta as dv')
                ->join('ventas as v', 'dv.id_venta', '=', 'v.id')
                ->join('productos as p', 'dv.id_producto', '=', 'p.id')
                ->where('v.id_cliente', $id_cliente)
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
                ->where('v.id_cliente', $id_cliente)
                ->where('v.estado', '!=', 'anulada')
                ->whereYear('v.fecha', $venta->año)
                ->whereMonth('v.fecha', $venta->mes)
                ->where('v.fecha', '>=', '1900-01-01')
                ->where('v.fecha', '<=', now())
                ->sum('dv.cantidad');

            DB::table('cliente_ventas_mensuales')->insert([
                'id_cliente' => $id_cliente,
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

        return $this->getVentasMensuales($id_cliente);
    }

    private function construirYGuardarActividadReciente($id_cliente)
    {
        // Limpiar actividades anteriores
        DB::table('cliente_actividad_reciente')
            ->where('id_cliente', $id_cliente)
            ->delete();

        // Obtener últimas ventas con validación de fechas como en el comando
        $ventas = Venta::where('id_cliente', $id_cliente)
            ->where('estado', '!=', 'anulada')
            ->where('fecha', '>=', '1900-01-01')  // Validación agregada
            ->where('fecha', '<=', now())        // Validación agregada
            ->orderBy('fecha', 'desc')
            ->limit(10)
            ->get();

        foreach ($ventas as $venta) {
            DB::table('cliente_actividad_reciente')->insert([
                'id_cliente' => $id_cliente,
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
        $transacciones = TransaccionPuntos::where('id_cliente', $id_cliente)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($transacciones as $transaccion) {
            DB::table('cliente_actividad_reciente')->insert([
                'id_cliente' => $id_cliente,
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

        return $this->getTransacciones($id_cliente);
    }

    private function calcularYGuardarCategoriasPreferidas($id_cliente)
    {
        // Calcular el total gastado por el cliente para porcentajes
        $totalCliente = DB::table('ventas')
            ->join('detalles_venta', 'ventas.id', '=', 'detalles_venta.id_venta')
            ->where('ventas.id_cliente', $id_cliente)
            ->where('ventas.estado', '!=', 'anulada')
            ->sum('detalles_venta.total');

        if ($totalCliente == 0) {
            return collect([]); // Cliente sin compras
        }

        // Obtener estadísticas por categoría
        $categorias = DB::table('ventas as v')
            ->join('detalles_venta as dv', 'v.id', '=', 'dv.id_venta')
            ->join('productos as p', 'dv.id_producto', '=', 'p.id')
            ->leftJoin('categorias as cat', 'p.id_categoria', '=', 'cat.id')
            ->where('v.id_cliente', $id_cliente)
            ->where('v.estado', '!=', 'anulada')
            ->select(
                DB::raw('COALESCE(p.id_categoria, 0) as id_categoria'),
                DB::raw('COALESCE(cat.nombre, "Sin Categoría") as nombre_categoria'),
                DB::raw('COUNT(DISTINCT p.id) as cantidad_productos'),
                DB::raw('COUNT(DISTINCT v.id) as total_compras'),
                DB::raw('SUM(dv.total) as total_gastado')
            )
            ->groupBy('p.id_categoria', 'cat.nombre')
            ->orderBy('total_gastado', 'desc')
            ->get();

        // Limpiar categorías anteriores del cliente
        DB::table('cliente_categorias_preferidas')
            ->where('id_cliente', $id_cliente)
            ->delete();

        // Insertar nuevas categorías con ranking
        $ranking = 1;
        $resultado = [];

        foreach ($categorias as $categoria) {
            $porcentajeGasto = ($categoria->total_gastado / $totalCliente) * 100;

            DB::table('cliente_categorias_preferidas')->insert([
                'id_cliente' => $id_cliente,
                'id_categoria' => $categoria->id_categoria == 0 ? null : $categoria->id_categoria,
                'nombre_categoria' => $categoria->nombre_categoria,
                'cantidad_productos' => $categoria->cantidad_productos,
                'total_compras' => $categoria->total_compras,
                'total_gastado' => $categoria->total_gastado,
                'porcentaje_gasto' => round($porcentajeGasto, 2),
                'ranking' => $ranking,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Agregar al resultado (solo top 5)
            if ($ranking <= 5) {
                $resultado[] = [
                    'rank' => $ranking,
                    'name' => $categoria->nombre_categoria,
                    'percentage' => round($porcentajeGasto, 2),
                    'total' => $categoria->total_gastado,
                    'products' => $categoria->cantidad_productos,
                    'purchases' => $categoria->total_compras,
                    'emoji' => '🏷️'
                ];
            }

            $ranking++;
        }

        return collect($resultado);
    }
}
