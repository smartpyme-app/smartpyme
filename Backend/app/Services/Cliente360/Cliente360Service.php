<?php

namespace App\Services\Cliente360;

use App\Models\Ventas\Clientes\Cliente;
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

        // Usar tablas agregadas - SIN FALLBACKS PESADOS
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
     * Obtener métricas de ventas desde tabla agregada (SIN FALLBACK PESADO)
     */
    private function getVentasMetrics($id_cliente)
    {
        $metricas = DB::table('cliente_metricas_rfm')
            ->where('id_cliente', $id_cliente)
            ->first();

        if (!$metricas) {
            return $this->getEmptyMetrics();
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
     * Obtener datos de fidelización desde snapshot (SIN FALLBACK PESADO)
     */
    private function getFidelizacionData($id_cliente)
    {
        $snapshot = DB::table('cliente_fidelizacion_snapshot')
            ->where('id_cliente', $id_cliente)
            ->first();

        if (!$snapshot) {
            return $this->getEmptyFidelizacion();
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
     * Obtener transacciones desde caché (SIN FALLBACK PESADO)
     */
    private function getTransacciones($id_cliente)
    {
        $actividades = DB::table('cliente_actividad_reciente')
            ->where('id_cliente', $id_cliente)
            ->orderBy('fecha_actividad', 'desc')
            ->limit(10)
            ->get();

        if ($actividades->isEmpty()) {
            return collect([]); // Retorna colección vacía
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
     * Obtener top productos desde tabla agregada (SIN FALLBACK PESADO)
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
            return collect([]); // Retorna colección vacía
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
     * Obtener ventas mensuales desde tabla agregada (SIN FALLBACK PESADO)
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
            return $this->getEmptyVentasMensuales(); // Retorna 12 meses con datos en 0
        }

        $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
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

    /**
     * Obtener categorías preferidas desde tabla agregada (SIN FALLBACK PESADO)
     */
    private function getCategoriasPreferidas($id_cliente)
    {
        $categorias = DB::table('cliente_categorias_preferidas')
            ->where('id_cliente', $id_cliente)
            ->orderBy('ranking', 'asc')
            ->limit(5)
            ->get();

        if ($categorias->isEmpty()) {
            return collect([]); // Retorna colección vacía
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
    // MÉTODOS PARA RETORNAR DATOS VACÍOS (SIN CÁLCULOS PESADOS)
    // ============================================

    private function getEmptyMetrics()
    {
        return [
            'clv' => 0,
            'averagePurchase' => 0,
            'healthScore' => 0,
            'recency' => null,
            'frequency' => 0,
            'monetary' => 0,
            'totalVentas' => 0,
            'cantidadVentas' => 0,
            'segmento' => 'New'
        ];
    }

    private function getEmptyFidelizacion()
    {
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

    private function getEmptyVentasMensuales()
    {
        $meses = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
        ];

        $ventasPorMes = [];

        for ($i = 11; $i >= 0; $i--) {
            $fecha = now()->subMonths($i);
            $mes = $fecha->month;

            $ventasPorMes[] = [
                'month' => $meses[$mes],
                'amount' => 0,
                'height' => 0,
                'high' => false
            ];
        }

        return $ventasPorMes;
    }
}