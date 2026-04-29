<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurante_pedidos') && ! Schema::hasColumn('restaurante_pedidos', 'id_bodega')) {
            Schema::table('restaurante_pedidos', function (Blueprint $table) {
                $table->unsignedInteger('id_bodega')->nullable()->after('id_sucursal');
            });
        }

        if (! Schema::hasTable('restaurante_pedido_detalles')) {
            return;
        }

        if (! Schema::hasColumn('restaurante_pedido_detalles', 'lote_id')) {
            Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
                $table->unsignedInteger('lote_id')->nullable()->after('producto_id');
            });
        }

        if (! Schema::hasColumn('restaurante_pedido_detalles', 'meta_inventario')) {
            Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
                $table->json('meta_inventario')->nullable()->after('notas');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restaurante_pedidos') && Schema::hasColumn('restaurante_pedidos', 'id_bodega')) {
            Schema::table('restaurante_pedidos', function (Blueprint $table) {
                $table->dropColumn('id_bodega');
            });
        }

        if (! Schema::hasTable('restaurante_pedido_detalles')) {
            return;
        }

        if (Schema::hasColumn('restaurante_pedido_detalles', 'meta_inventario')) {
            Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
                $table->dropColumn('meta_inventario');
            });
        }

        /* No eliminamos lote_id aquí: puede haber existido en BD antes de esta migración. */
    }
};
