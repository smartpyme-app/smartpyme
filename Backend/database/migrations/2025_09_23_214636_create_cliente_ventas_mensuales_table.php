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
        if (!Schema::hasTable('cliente_ventas_mensuales')) {
            Schema::create('cliente_ventas_mensuales', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cliente');
            $table->foreign('id_cliente')->references('id')->on('clientes')->onDelete('cascade');
            $table->year('año');
            $table->tinyInteger('mes'); // 1-12
            
            $table->integer('cantidad_ventas')->default(0);
            $table->decimal('total_ventas', 15, 2)->default(0);
            $table->decimal('ticket_promedio', 15, 2)->default(0);
            $table->integer('productos_unicos')->default(0);
            $table->integer('items_totales')->default(0);
            
            // Para comparaciones
            $table->boolean('es_mes_alto')->default(false); // Si está en top 20% de meses
            $table->decimal('variacion_mes_anterior', 10, 2)->nullable(); // % de cambio
            
            $table->timestamps();
            
            // Índices
            $table->unique(['id_cliente', 'año', 'mes']);
            $table->index('id_cliente');
            $table->index(['año', 'mes']);
            $table->index(['id_cliente', 'año', 'mes']);
            
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_ventas_mensuales');
    }
};