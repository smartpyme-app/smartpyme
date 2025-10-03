<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAbcClassificationAndTrendsToClienteMetricasRfmTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('cliente_metricas_rfm', function (Blueprint $table) {
            // Clasificación ABC
            $table->string('clasificacion_abc', 50)->nullable()->after('segmento_rfm'); // Clase A, Clase B, Clase C
            
            // Tendencias de consumo
            $table->string('tendencia_consumo', 30)->nullable()->after('clasificacion_abc'); // En Crecimiento, Neutro, En Decrecimiento
            $table->decimal('porcentaje_tendencia', 8, 2)->nullable()->after('tendencia_consumo'); // Porcentaje de cambio
            
            // Gasto año anterior
            $table->decimal('gasto_anio_anterior', 15, 2)->default(0)->after('porcentaje_tendencia');
            
            // Índices para optimización
            $table->index('clasificacion_abc');
            $table->index('tendencia_consumo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('cliente_metricas_rfm', function (Blueprint $table) {
            $table->dropIndex(['clasificacion_abc']);
            $table->dropIndex(['tendencia_consumo']);
            $table->dropColumn([
                'clasificacion_abc',
                'tendencia_consumo', 
                'porcentaje_tendencia',
                'gasto_anio_anterior'
            ]);
        });
    }
}