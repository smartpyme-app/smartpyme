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
            $table->string('responsable'); 
            
           
            $table->string('prioridad')->default('medium'); // 'low', 'medium', 'high', 'urgent', etc.
            
           
            $table->string('estado')->default('activo'); 
            // 'activo', 'pendiente', 'en_proceso', 'resuelto', 'archivado', etc.
            
            $table->boolean('requiere_seguimiento')->default(false);
            $table->date('fecha_seguimiento')->nullable();
            $table->text('resolucion')->nullable();
            
           
            $table->date('fecha_interaccion');
            $table->time('hora_interaccion');
            
            $table->json('metadata')->nullable(); 
            
            $table->timestamps();

            $table->index(['id_cliente', 'tipo']);
            $table->index(['id_cliente', 'fecha_interaccion']);
            $table->index(['responsable', 'fecha_interaccion']);
            $table->index(['estado', 'requiere_seguimiento']); 
            $table->index(['tipo', 'estado']); 
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
