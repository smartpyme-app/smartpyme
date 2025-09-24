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
            if (!$this->indexExists('detalles_venta', 'idx_detalles_id_venta') && 
                !$this->indexExists('detalles_venta', 'detalles_venta_id_venta_foreign')) {
                $table->index('id_venta', 'idx_detalles_id_venta');
                echo "✓ Creado índice: idx_detalles_id_venta\n";
            }
            
            if (!$this->indexExists('detalles_venta', 'idx_detalles_id_producto') &&
                !$this->indexExists('detalles_venta', 'detalles_venta_id_producto_foreign')) {
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
            if (!$this->indexExists('puntos_cliente', 'idx_puntos_cliente') &&
                !$this->indexExists('puntos_cliente', 'puntos_cliente_id_cliente_foreign')) {
                $table->index('id_cliente', 'idx_puntos_cliente');
                echo "✓ Creado índice: idx_puntos_cliente\n";
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
        
        echo "✅ Índices Cliente360 eliminados\n";
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