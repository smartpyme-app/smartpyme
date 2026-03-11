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
        Schema::create('cliente_actividad_reciente', function (Blueprint $table) {
            $table->id();
            $table->integer('id_cliente');
            $table->foreign('id_cliente')->references('id')->on('clientes')->onDelete('cascade');


            $table->string('tipo_actividad', 20); // venta, puntos_ganados, puntos_canjeados
            $table->unsignedBigInteger('id_referencia')->nullable(); // ID de venta o transacción
            
            $table->string('titulo', 200);
            $table->text('descripcion')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->integer('puntos')->nullable();
            $table->string('icono', 10)->default('📦');
            $table->string('estado', 20)->default('completado');
            
            $table->timestamp('fecha_actividad');
            $table->timestamps();
            
            // Índices
            $table->index(['id_cliente', 'fecha_actividad']);
            $table->index('id_cliente');
            $table->index('tipo_actividad');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_actividad_reciente');
    }
};