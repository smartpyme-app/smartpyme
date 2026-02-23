<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar y crear índices en la tabla kardex
        Schema::table('kardexs', function (Blueprint $table) {
            // Índice compuesto para la consulta principal (whereIn + orderBy)
            if (!$this->indexExists('kardexs', 'idx_kardex_producto_fecha_id')) {
                $table->index(['id_producto', 'fecha', 'id'], 'idx_kardex_producto_fecha_id');
            }
            
            // Índice para filtros de fecha
            if (!$this->indexExists('kardexs', 'idx_kardex_fecha')) {
                $table->index('fecha', 'idx_kardex_fecha');
            }
            
            // Índices para foreign keys
            if (!$this->indexExists('kardexs', 'idx_kardex_inventario')) {
                $table->index('id_inventario', 'idx_kardex_inventario');
            }
            
            if (!$this->indexExists('kardexs', 'idx_kardex_usuario')) {
                $table->index('id_usuario', 'idx_kardex_usuario');
            }
        });
        
        // Índices en la tabla inventario/bodega
        if (Schema::hasTable('inventario')) {
            Schema::table('inventario', function (Blueprint $table) {
                if (Schema::hasColumn('inventario', 'id_sucursal') && 
                    !$this->indexExists('inventario', 'idx_inventario_sucursal')) {
                    $table->index('id_sucursal', 'idx_inventario_sucursal');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kardexs', function (Blueprint $table) {
            if ($this->indexExists('kardexs', 'idx_kardex_producto_fecha_id')) {
                $table->dropIndex('idx_kardex_producto_fecha_id');
            }
            
            if ($this->indexExists('kardexs', 'idx_kardex_fecha')) {
                $table->dropIndex('idx_kardex_fecha');
            }
            
            if ($this->indexExists('kardexs', 'idx_kardex_inventario')) {
                $table->dropIndex('idx_kardex_inventario');
            }
            
            if ($this->indexExists('kardexs', 'idx_kardex_usuario')) {
                $table->dropIndex('idx_kardex_usuario');
            }
        });
        
        if (Schema::hasTable('inventario')) {
            Schema::table('inventario', function (Blueprint $table) {
                if ($this->indexExists('inventario', 'idx_inventario_sucursal')) {
                    $table->dropIndex('idx_inventario_sucursal');
                }
            });
        }
    }

    /**
     * Verifica si un índice existe en una tabla
     * 
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }
};