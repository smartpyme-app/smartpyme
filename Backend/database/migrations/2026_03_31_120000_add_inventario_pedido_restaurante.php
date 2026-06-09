<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurante_pedidos', function (Blueprint $table) {
            $table->timestamp('inventario_descontado_at')->nullable()->after('total');
            $table->unsignedInteger('id_bodega_inventario')->nullable()->after('inventario_descontado_at');
        });

        Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
            $table->unsignedInteger('lote_id')->nullable()->after('notas');
            $table->foreign('lote_id')->references('id')->on('lotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurante_pedido_detalles', function (Blueprint $table) {
            $table->dropForeign(['lote_id']);
            $table->dropColumn('lote_id');
        });

        Schema::table('restaurante_pedidos', function (Blueprint $table) {
            $table->dropColumn(['inventario_descontado_at', 'id_bodega_inventario']);
        });
    }
};
