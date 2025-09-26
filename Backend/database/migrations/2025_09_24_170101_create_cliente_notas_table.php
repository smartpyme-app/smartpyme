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
        Schema::create('cliente_notas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_cliente')->constrained('clientes');
            $table->string('tipo'); // 'preferencias', 'quejas', 'comentarios', 'visita', 'llamada', 'whatsapp', 'email'
            $table->string('titulo');
            $table->text('contenido');
            $table->string('responsable'); // Usuario que creó la nota
            $table->string('prioridad')->default('medium'); // 'low', 'medium', 'high'
            $table->json('metadata')->nullable(); // Datos adicionales como productos mencionados, etc.
            $table->date('fecha_interaccion');
            $table->time('hora_interaccion');
            $table->date('fecha_seguimiento')->nullable();
            $table->boolean('resuelto')->default(false);
            $table->text('resolucion')->nullable();
            $table->timestamps();

            $table->index(['id_cliente', 'tipo']);
            $table->index(['id_cliente', 'fecha_interaccion']);
            $table->index(['responsable', 'fecha_interaccion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_notas');
    }
};
