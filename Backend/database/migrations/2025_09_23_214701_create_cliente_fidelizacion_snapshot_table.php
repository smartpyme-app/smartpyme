<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cliente_fidelizacion_snapshot', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cliente');
            $table->foreign('id_cliente')->references('id')->on('clientes')->onDelete('cascade');
            
            $table->integer('puntos_disponibles')->default(0);
            $table->integer('puntos_totales_ganados')->default(0);
            $table->integer('puntos_totales_canjeados')->default(0);
            $table->decimal('valor_puntos_canjeados', 15, 2)->default(0); // En dinero
            
            // Métricas de actividad
            $table->integer('transacciones_ultimos_30_dias')->default(0);
            $table->integer('puntos_ganados_ultimos_30_dias')->default(0);
            $table->integer('puntos_canjeados_ultimos_30_dias')->default(0);
            $table->date('fecha_ultima_ganancia')->nullable();
            $table->date('fecha_ultimo_canje')->nullable();
            
            // Tasa de redención
            $table->decimal('tasa_redencion', 5, 2)->default(0); // % de puntos canjeados
            
            $table->timestamp('fecha_snapshot')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('id_cliente');
            $table->index('puntos_disponibles');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_fidelizacion_snapshot');
    }
};