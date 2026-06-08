<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandas_restaurante', function (Blueprint $table) {
            if (! Schema::hasColumn('comandas_restaurante', 'eliminacion_item_enviado')) {
                $table->boolean('eliminacion_item_enviado')
                    ->nullable()
                    ->after('destino')
                    ->comment('Solo destino=eliminacion: ítem ya estaba en cocina/barra');
            }
            if (! Schema::hasColumn('comandas_restaurante', 'motivo_eliminacion_codigo')) {
                $table->string('motivo_eliminacion_codigo', 50)->nullable()->after('eliminacion_item_enviado');
            }
            if (! Schema::hasColumn('comandas_restaurante', 'motivo_eliminacion_detalle')) {
                $table->text('motivo_eliminacion_detalle')->nullable()->after('motivo_eliminacion_codigo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comandas_restaurante', function (Blueprint $table) {
            foreach (['motivo_eliminacion_detalle', 'motivo_eliminacion_codigo', 'eliminacion_item_enviado'] as $col) {
                if (Schema::hasColumn('comandas_restaurante', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
