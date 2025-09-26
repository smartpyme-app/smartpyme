<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClienteMetricasRfmTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('cliente_metricas_rfm')) {
            Schema::create('cliente_metricas_rfm', function (Blueprint $table) {
            $table->id();
            
            $table->integer('id_cliente');
            $table->foreign('id_cliente')
                  ->references('id')
                  ->on('clientes')
                  ->onDelete('cascade');
            
            
            // Recency
            $table->date('fecha_ultima_compra')->nullable();
            $table->integer('dias_ultima_compra')->nullable();
            
            // Frequency
            $table->integer('total_compras')->default(0);
            $table->integer('compras_ultimos_12_meses')->default(0);
            $table->integer('compras_ultimos_6_meses')->default(0);
            $table->integer('compras_ultimos_3_meses')->default(0);
            
            // Monetary
            $table->decimal('total_gastado', 15, 2)->default(0);
            $table->decimal('gasto_ultimos_12_meses', 15, 2)->default(0);
            $table->decimal('gasto_ultimos_6_meses', 15, 2)->default(0);
            $table->decimal('gasto_ultimos_3_meses', 15, 2)->default(0);
            $table->decimal('ticket_promedio', 15, 2)->default(0);
            
            // Scores calculados
            $table->tinyInteger('recency_score')->default(0); // 0-100
            $table->tinyInteger('frequency_score')->default(0); // 0-100
            $table->tinyInteger('monetary_score')->default(0); // 0-100
            $table->tinyInteger('health_score')->default(0); // 0-100
            $table->string('segmento_rfm', 20)->nullable(); // Champions, Loyal, At Risk, etc.
            
            $table->timestamp('fecha_calculo')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('health_score');
            $table->index('segmento_rfm');
            $table->index('fecha_ultima_compra');
            
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_metricas_rfm');
    }
}
