<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('frecuencia_recurrencia', 10)->nullable()->after('recurrente');
            $table->boolean('recurrencia_pausada')->default(false)->after('frecuencia_recurrencia');
            $table->unsignedBigInteger('id_venta_plantilla')->nullable()->after('recurrencia_pausada');
            $table->string('periodo_recurrencia', 7)->nullable()->after('id_venta_plantilla');
            $table->unique(['id_venta_plantilla', 'periodo_recurrencia'], 'venta_recurrencia_periodo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('venta_recurrencia_periodo_unique');
            $table->dropColumn([
                'frecuencia_recurrencia',
                'recurrencia_pausada',
                'id_venta_plantilla',
                'periodo_recurrencia',
            ]);
        });
    }
};
