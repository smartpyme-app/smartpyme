<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        echo "Creando índices para Cliente360...\n";

        // VENTAS - Índices para queries RFM y ventas mensuales
        Schema::table('ventas', function (Blueprint $table) {
            // Índice compuesto principal (clave para performance)
            if (!$this->indexExists('ventas', 'idx_ventas_cliente_estado_fecha')) {
                $table->index(['id_cliente', 'estado', 'fecha'], 'idx_ventas_cliente_estado_fecha');
                echo "✓ Creado índice: idx_ventas_cliente_estado_fecha\n";
            }

            // Índice para rangos de fechas
            if (!$this->indexExists('ventas', 'idx_ventas_fecha')) {
                $table->index('fecha', 'idx_ventas_fecha');
                echo "✓ Creado índice: idx_ventas_fecha\n";
            }
        });

        // DETALLES_VENTA - Índices para JOINs y productos top
        Schema::table('detalles_venta', function (Blueprint $table) {
            // Solo crear si no existen como foreign keys
            if (
                !$this->indexExists('detalles_venta', 'idx_detalles_id_venta') &&
                !$this->indexExists('detalles_venta', 'detalles_venta_id_venta_foreign')
            ) {
                $table->index('id_venta', 'idx_detalles_id_venta');
                echo "✓ Creado índice: idx_detalles_id_venta\n";
            }

            if (
                !$this->indexExists('detalles_venta', 'idx_detalles_id_producto') &&
                !$this->indexExists('detalles_venta', 'detalles_venta_id_producto_foreign')
            ) {
                $table->index('id_producto', 'idx_detalles_id_producto');
                echo "✓ Creado índice: idx_detalles_id_producto\n";
            }

            // Índice compuesto para GROUP BY (venta, producto)
            if (!$this->indexExists('detalles_venta', 'idx_detalles_venta_producto')) {
                $table->index(['id_venta', 'id_producto'], 'idx_detalles_venta_producto');
                echo "✓ Creado índice: idx_detalles_venta_producto\n";
            }
        });

        // VENTAS - Índice adicional para actividad reciente (ORDER BY DESC)
        Schema::table('ventas', function (Blueprint $table) {
            if (!$this->indexExists('ventas', 'idx_ventas_cliente_fecha_desc')) {
                // MySQL no soporta WHERE en índices, usar sin filtro
                DB::statement('CREATE INDEX idx_ventas_cliente_fecha_desc ON ventas (id_cliente, fecha DESC)');
                echo "✓ Creado índice: idx_ventas_cliente_fecha_desc\n";
            }
        });

        // TRANSACCIONES_PUNTOS - Índices para fidelización
        Schema::table('transacciones_puntos', function (Blueprint $table) {
            if (!$this->indexExists('transacciones_puntos', 'idx_transacciones_cliente_tipo')) {
                $table->index(['id_cliente', 'tipo'], 'idx_transacciones_cliente_tipo');
                echo "✓ Creado índice: idx_transacciones_cliente_tipo\n";
            }

            // Para queries de últimos 30 días
            if (!$this->indexExists('transacciones_puntos', 'idx_transacciones_cliente_created')) {
                $table->index(['id_cliente', 'created_at'], 'idx_transacciones_cliente_created');
                echo "✓ Creado índice: idx_transacciones_cliente_created\n";
            }

            // Para última ganancia/canje por tipo
            if (!$this->indexExists('transacciones_puntos', 'idx_transacciones_tipo_created')) {
                $table->index(['tipo', 'created_at'], 'idx_transacciones_tipo_created');
                echo "✓ Creado índice: idx_transacciones_tipo_created\n";
            }

            // Para actividad reciente con ORDER BY DESC
            if (!$this->indexExists('transacciones_puntos', 'idx_transacciones_cliente_created_desc')) {
                DB::statement('CREATE INDEX idx_transacciones_cliente_created_desc ON transacciones_puntos (id_cliente, created_at DESC)');
                echo "✓ Creado índice: idx_transacciones_cliente_created_desc\n";
            }
        });

        // PUNTOS_CLIENTE - Índice para snapshot
        Schema::table('puntos_cliente', function (Blueprint $table) {
            // Solo si no existe como foreign key
            if (
                !$this->indexExists('puntos_cliente', 'idx_puntos_cliente') &&
                !$this->indexExists('puntos_cliente', 'puntos_cliente_id_cliente_foreign')
            ) {
                $table->index('id_cliente', 'idx_puntos_cliente');
                echo "✓ Creado índice: idx_puntos_cliente\n";
            }
        });

        // PRODUCTOS - Índices para categorías y JOINs
        Schema::table('productos', function (Blueprint $table) {
            // Para el JOIN con categorías
            if (
                !$this->indexExists('productos', 'idx_productos_categoria') &&
                !$this->indexExists('productos', 'productos_id_categoria_foreign')
            ) {
                $table->index('id_categoria', 'idx_productos_categoria');
                echo "✓ Creado índice: idx_productos_categoria\n";
            }
        });

        // CATEGORIAS - Índices para nombre y JOINs
        Schema::table('categorias', function (Blueprint $table) {
            // Para búsquedas por nombre (si no existe)
            if (!$this->indexExists('categorias', 'idx_categorias_nombre')) {
                $table->index('nombre', 'idx_categorias_nombre');
                echo "✓ Creado índice: idx_categorias_nombre\n";
            }
        });

        // ÍNDICES ADICIONALES PARA OPTIMIZACIÓN COMPLETA

        // Para productos top masivos (elimina necesidad de JOINs con productos)
        if (!$this->indexExists('detalles_venta', 'idx_detalles_producto_cantidad')) {
            DB::statement('CREATE INDEX idx_detalles_producto_cantidad ON detalles_venta (id_producto, cantidad DESC)');
            echo "✓ Creado índice: idx_detalles_producto_cantidad\n";
        }

        // VENTAS - Índice adicional para ventas mensuales
        Schema::table('ventas', function (Blueprint $table) {
            // Para ventas mensuales (usa índice de fecha normal, MySQL no soporta YEAR/MONTH en índices)
            if (!$this->indexExists('ventas', 'idx_ventas_cliente_fecha_estado')) {
                $table->index(['id_cliente', 'fecha', 'estado'], 'idx_ventas_cliente_fecha_estado');
                echo "✓ Creado índice: idx_ventas_cliente_fecha_estado\n";
            }
        });

        // TRANSACCIONES_PUNTOS - Índice adicional para fidelización
        Schema::table('transacciones_puntos', function (Blueprint $table) {
            // Para fidelización masiva (optimiza LEFT JOINs)
            if (!$this->indexExists('transacciones_puntos', 'idx_transacciones_tipo_cliente')) {
                $table->index(['tipo', 'id_cliente'], 'idx_transacciones_tipo_cliente');
                echo "✓ Creado índice: idx_transacciones_tipo_cliente\n";
            }
        });

        // ÍNDICES PARA LAS TABLAS AGREGADAS DE CLIENTE360

        // CLIENTE_CATEGORIAS_PREFERIDAS - Índices para consultas optimizadas
        Schema::table('cliente_categorias_preferidas', function (Blueprint $table) {
            // Índice principal para consultas por cliente y ranking
            if (!$this->indexExists('cliente_categorias_preferidas', 'idx_cliente_categorias_cliente_ranking')) {
                $table->index(['id_cliente', 'ranking'], 'idx_cliente_categorias_cliente_ranking');
                echo "✓ Creado índice: idx_cliente_categorias_cliente_ranking\n";
            }

            // Índice por cliente solo
            if (!$this->indexExists('cliente_categorias_preferidas', 'idx_cliente_categorias_cliente')) {
                $table->index('id_cliente', 'idx_cliente_categorias_cliente');
                echo "✓ Creado índice: idx_cliente_categorias_cliente\n";
            }
        });

        // CLIENTE_METRICAS_RFM - Índices para consultas del servicio
        Schema::table('cliente_metricas_rfm', function (Blueprint $table) {
            if (!$this->indexExists('cliente_metricas_rfm', 'idx_metricas_rfm_cliente')) {
                $table->index('id_cliente', 'idx_metricas_rfm_cliente');
                echo "✓ Creado índice: idx_metricas_rfm_cliente\n";
            }

            // Para filtros por segmento
            if (!$this->indexExists('cliente_metricas_rfm', 'idx_metricas_rfm_segmento')) {
                $table->index('segmento_rfm', 'idx_metricas_rfm_segmento');
                echo "✓ Creado índice: idx_metricas_rfm_segmento\n";
            }

            // Para validación de frescura
            if (!$this->indexExists('cliente_metricas_rfm', 'idx_metricas_rfm_fecha_calculo')) {
                $table->index('fecha_calculo', 'idx_metricas_rfm_fecha_calculo');
                echo "✓ Creado índice: idx_metricas_rfm_fecha_calculo\n";
            }
        });

        // CLIENTE_PRODUCTOS_TOP - Índices para top productos
        Schema::table('cliente_productos_top', function (Blueprint $table) {
            if (!$this->indexExists('cliente_productos_top', 'idx_productos_top_cliente_ranking')) {
                $table->index(['id_cliente', 'ranking'], 'idx_productos_top_cliente_ranking');
                echo "✓ Creado índice: idx_productos_top_cliente_ranking\n";
            }
        });

        // CLIENTE_VENTAS_MENSUALES - Índices para ventas mensuales
        Schema::table('cliente_ventas_mensuales', function (Blueprint $table) {
            if (!$this->indexExists('cliente_ventas_mensuales', 'idx_ventas_mensuales_cliente_fecha')) {
                $table->index(['id_cliente', 'año', 'mes'], 'idx_ventas_mensuales_cliente_fecha');
                echo "✓ Creado índice: idx_ventas_mensuales_cliente_fecha\n";
            }
        });

        // CLIENTE_FIDELIZACION_SNAPSHOT - Índice para fidelización
        Schema::table('cliente_fidelizacion_snapshot', function (Blueprint $table) {
            if (!$this->indexExists('cliente_fidelizacion_snapshot', 'idx_fidelizacion_cliente')) {
                $table->index('id_cliente', 'idx_fidelizacion_cliente');
                echo "✓ Creado índice: idx_fidelizacion_cliente\n";
            }

            // Para validación de frescura
            if (!$this->indexExists('cliente_fidelizacion_snapshot', 'idx_fidelizacion_fecha_snapshot')) {
                $table->index('fecha_snapshot', 'idx_fidelizacion_fecha_snapshot');
                echo "✓ Creado índice: idx_fidelizacion_fecha_snapshot\n";
            }
        });

        // CLIENTE_ACTIVIDAD_RECIENTE - Índices para actividad
        Schema::table('cliente_actividad_reciente', function (Blueprint $table) {
            if (!$this->indexExists('cliente_actividad_reciente', 'idx_actividad_cliente_fecha')) {
                $table->index(['id_cliente', 'fecha_actividad'], 'idx_actividad_cliente_fecha');
                echo "✓ Creado índice: idx_actividad_cliente_fecha\n";
            }

            // Para ORDER BY DESC
            if (!$this->indexExists('cliente_actividad_reciente', 'idx_actividad_cliente_fecha_desc')) {
                DB::statement('CREATE INDEX idx_actividad_cliente_fecha_desc ON cliente_actividad_reciente (id_cliente, fecha_actividad DESC)');
                echo "✓ Creado índice: idx_actividad_cliente_fecha_desc\n";
            }
        });

        echo "✅ Índices Cliente360 creados exitosamente\n";
    }

    public function down(): void
    {
        echo "Eliminando índices Cliente360...\n";

        Schema::table('ventas', function (Blueprint $table) {
            if ($this->indexExists('ventas', 'idx_ventas_cliente_estado_fecha')) {
                $table->dropIndex('idx_ventas_cliente_estado_fecha');
                echo "✓ Eliminado índice: idx_ventas_cliente_estado_fecha\n";
            }
            if ($this->indexExists('ventas', 'idx_ventas_fecha')) {
                $table->dropIndex('idx_ventas_fecha');
                echo "✓ Eliminado índice: idx_ventas_fecha\n";
            }
            if ($this->indexExists('ventas', 'idx_ventas_cliente_fecha_desc')) {
                DB::statement('DROP INDEX idx_ventas_cliente_fecha_desc ON ventas');
                echo "✓ Eliminado índice: idx_ventas_cliente_fecha_desc\n";
            }
            if ($this->indexExists('ventas', 'idx_ventas_cliente_fecha_estado')) {
                $table->dropIndex('idx_ventas_cliente_fecha_estado');
                echo "✓ Eliminado índice: idx_ventas_cliente_fecha_estado\n";
            }
        });

        Schema::table('detalles_venta', function (Blueprint $table) {
            if ($this->indexExists('detalles_venta', 'idx_detalles_id_venta')) {
                $table->dropIndex('idx_detalles_id_venta');
                echo "✓ Eliminado índice: idx_detalles_id_venta\n";
            }
            if ($this->indexExists('detalles_venta', 'idx_detalles_id_producto')) {
                $table->dropIndex('idx_detalles_id_producto');
                echo "✓ Eliminado índice: idx_detalles_id_producto\n";
            }
            if ($this->indexExists('detalles_venta', 'idx_detalles_venta_producto')) {
                $table->dropIndex('idx_detalles_venta_producto');
                echo "✓ Eliminado índice: idx_detalles_venta_producto\n";
            }
            if ($this->indexExists('detalles_venta', 'idx_detalles_producto_cantidad')) {
                DB::statement('DROP INDEX idx_detalles_producto_cantidad ON detalles_venta');
                echo "✓ Eliminado índice: idx_detalles_producto_cantidad\n";
            }
        });

        Schema::table('transacciones_puntos', function (Blueprint $table) {
            if ($this->indexExists('transacciones_puntos', 'idx_transacciones_cliente_tipo')) {
                $table->dropIndex('idx_transacciones_cliente_tipo');
                echo "✓ Eliminado índice: idx_transacciones_cliente_tipo\n";
            }
            if ($this->indexExists('transacciones_puntos', 'idx_transacciones_cliente_created')) {
                $table->dropIndex('idx_transacciones_cliente_created');
                echo "✓ Eliminado índice: idx_transacciones_cliente_created\n";
            }
            if ($this->indexExists('transacciones_puntos', 'idx_transacciones_tipo_created')) {
                $table->dropIndex('idx_transacciones_tipo_created');
                echo "✓ Eliminado índice: idx_transacciones_tipo_created\n";
            }
            if ($this->indexExists('transacciones_puntos', 'idx_transacciones_cliente_created_desc')) {
                DB::statement('DROP INDEX idx_transacciones_cliente_created_desc ON transacciones_puntos');
                echo "✓ Eliminado índice: idx_transacciones_cliente_created_desc\n";
            }
            if ($this->indexExists('transacciones_puntos', 'idx_transacciones_tipo_cliente')) {
                $table->dropIndex('idx_transacciones_tipo_cliente');
                echo "✓ Eliminado índice: idx_transacciones_tipo_cliente\n";
            }
        });

        Schema::table('puntos_cliente', function (Blueprint $table) {
            if ($this->indexExists('puntos_cliente', 'idx_puntos_cliente')) {
                $table->dropIndex('idx_puntos_cliente');
                echo "✓ Eliminado índice: idx_puntos_cliente\n";
            }
        });

        Schema::table('productos', function (Blueprint $table) {
            if ($this->indexExists('productos', 'idx_productos_categoria')) {
                $table->dropIndex('idx_productos_categoria');
                echo "✓ Eliminado índice: idx_productos_categoria\n";
            }
        });

        Schema::table('categorias', function (Blueprint $table) {
            if ($this->indexExists('categorias', 'idx_categorias_nombre')) {
                $table->dropIndex('idx_categorias_nombre');
                echo "✓ Eliminado índice: idx_categorias_nombre\n";
            }
        });

        // Eliminar índices de tablas agregadas Cliente360 - CON MANEJO DE FOREIGN KEYS
        Schema::table('cliente_categorias_preferidas', function (Blueprint $table) {
            if ($this->indexExists('cliente_categorias_preferidas', 'idx_cliente_categorias_cliente_ranking')) {
                $table->dropIndex('idx_cliente_categorias_cliente_ranking');
                echo "✓ Eliminado índice: idx_cliente_categorias_cliente_ranking\n";
            }
            if ($this->indexExists('cliente_categorias_preferidas', 'idx_cliente_categorias_cliente')) {
                $table->dropIndex('idx_cliente_categorias_cliente');
                echo "✓ Eliminado índice: idx_cliente_categorias_cliente\n";
            }
        });

        // CLIENTE_METRICAS_RFM - Manejo especial por foreign keys
        Schema::table('cliente_metricas_rfm', function (Blueprint $table) {
            // Solo eliminar los índices que NO son foreign keys
            if ($this->indexExists('cliente_metricas_rfm', 'idx_metricas_rfm_segmento')) {
                $table->dropIndex('idx_metricas_rfm_segmento');
                echo "✓ Eliminado índice: idx_metricas_rfm_segmento\n";
            }
            if ($this->indexExists('cliente_metricas_rfm', 'idx_metricas_rfm_fecha_calculo')) {
                $table->dropIndex('idx_metricas_rfm_fecha_calculo');
                echo "✓ Eliminado índice: idx_metricas_rfm_fecha_calculo\n";
            }

            // NO eliminar idx_metricas_rfm_cliente porque es usado por foreign key
            echo "⚠️ Omitiendo eliminación de idx_metricas_rfm_cliente (usado por foreign key)\n";
        });

        Schema::table('cliente_productos_top', function (Blueprint $table) {
            if ($this->indexExists('cliente_productos_top', 'idx_productos_top_cliente_ranking')) {
                $table->dropIndex('idx_productos_top_cliente_ranking');
                echo "✓ Eliminado índice: idx_productos_top_cliente_ranking\n";
            }
        });

        Schema::table('cliente_ventas_mensuales', function (Blueprint $table) {
            if ($this->indexExists('cliente_ventas_mensuales', 'idx_ventas_mensuales_cliente_fecha')) {
                $table->dropIndex('idx_ventas_mensuales_cliente_fecha');
                echo "✓ Eliminado índice: idx_ventas_mensuales_cliente_fecha\n";
            }
        });

        Schema::table('cliente_fidelizacion_snapshot', function (Blueprint $table) {
            if ($this->indexExists('cliente_fidelizacion_snapshot', 'idx_fidelizacion_fecha_snapshot')) {
                $table->dropIndex('idx_fidelizacion_fecha_snapshot');
                echo "✓ Eliminado índice: idx_fidelizacion_fecha_snapshot\n";
            }

            // NO eliminar idx_fidelizacion_cliente porque es usado por foreign key
            echo "⚠️ Omitiendo eliminación de idx_fidelizacion_cliente (usado por foreign key)\n";
        });

        Schema::table('cliente_actividad_reciente', function (Blueprint $table) {
            if ($this->indexExists('cliente_actividad_reciente', 'idx_actividad_cliente_fecha')) {
                $table->dropIndex('idx_actividad_cliente_fecha');
                echo "✓ Eliminado índice: idx_actividad_cliente_fecha\n";
            }
            if ($this->indexExists('cliente_actividad_reciente', 'idx_actividad_cliente_fecha_desc')) {
                DB::statement('DROP INDEX idx_actividad_cliente_fecha_desc ON cliente_actividad_reciente');
                echo "✓ Eliminado índice: idx_actividad_cliente_fecha_desc\n";
            }
        });

        echo "✅ Índices Cliente360 eliminados (con manejo de foreign keys)\n";
    }

    /**
     * Verifica si un índice existe en la tabla
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $dbName = $connection->getDatabaseName();

            $exists = DB::select(
                "SELECT COUNT(*) as count 
                 FROM information_schema.statistics 
                 WHERE table_schema = ? 
                 AND table_name = ? 
                 AND index_name = ?",
                [$dbName, $table, $index]
            );

            return $exists[0]->count > 0;
        } catch (\Exception $e) {
            // Si hay error consultando, asumir que no existe
            return false;
        }
    }
};
