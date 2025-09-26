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
        Schema::create('cliente_visitas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_cliente')->constrained('clientes');
            $table->string('tipo_visita'); // 'presencial', 'virtual', 'llamada', 'whatsapp', 'email'
            $table->string('titulo');
            $table->text('descripcion');
            $table->string('responsable');
            $table->string('prioridad')->default('medium');
            $table->json('productos_mencionados')->nullable(); // IDs de productos discutidos
            $table->json('servicios_mencionados')->nullable(); // IDs de servicios discutidos
            $table->decimal('valor_potencial', 15, 2)->nullable(); // Valor potencial de la visita
            $table->string('estado')->default('programada'); // 'programada', 'realizada', 'cancelada'
            $table->date('fecha_visita');
            $table->time('hora_visita');
            $table->integer('duracion_minutos')->nullable();
            $table->text('resultados')->nullable();
            $table->text('proximos_pasos')->nullable();
            $table->date('fecha_seguimiento')->nullable();
            $table->boolean('requiere_seguimiento')->default(false);
            $table->timestamps();

            $table->index(['id_cliente', 'fecha_visita']);
            $table->index(['responsable', 'fecha_visita']);
            $table->index(['estado', 'fecha_visita']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliente_visitas');
    }
};
