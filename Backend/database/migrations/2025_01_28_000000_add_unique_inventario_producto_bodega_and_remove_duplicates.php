<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUniqueInventarioProductoBodegaAndRemoveDuplicates extends Migration
{
    /**
     * Evita duplicados (producto + bodega): elimina filas duplicadas y añade índice único.
     */
    public function up()
    {
        $tableName = 'inventario';

        if (!Schema::hasTable($tableName)) {
            return;
        }

        // 1) Fusionar duplicados: sumar stock en la fila que se conserva (menor id) y eliminar el resto
        $duplicates = DB::table($tableName)
            ->select('id_producto', 'id_bodega')
            ->groupBy('id_producto', 'id_bodega')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $totalStock = DB::table($tableName)
                ->where('id_producto', $dup->id_producto)
                ->where('id_bodega', $dup->id_bodega)
                ->sum('stock');

            $keepId = DB::table($tableName)
                ->where('id_producto', $dup->id_producto)
                ->where('id_bodega', $dup->id_bodega)
                ->orderBy('id')
                ->value('id');

            DB::table($tableName)
                ->where('id', $keepId)
                ->update(['stock' => $totalStock]);

            DB::table($tableName)
                ->where('id_producto', $dup->id_producto)
                ->where('id_bodega', $dup->id_bodega)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // 2) Sustituir índice por único (eliminar el índice normal si existe)
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_inventarios_producto_bodega');
                } catch (\Throwable $e) {
                    // El índice puede no existir en algunas instalaciones
                }
                $table->unique(['id_producto', 'id_bodega'], 'inventario_producto_bodega_unique');
            });
        }
    }

    public function down()
    {
        $tableName = 'inventario';
        if (!Schema::hasTable($tableName)) {
            return;
        }
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('inventario_producto_bodega_unique');
            $table->index(['id_producto', 'id_bodega'], 'idx_inventarios_producto_bodega');
        });
    }
}
