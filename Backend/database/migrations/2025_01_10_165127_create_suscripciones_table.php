<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuscripcionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('planes')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('cascade'); // Usuario que realizó la suscripción
            $table->string('tipo_plan')->nullable();
            $table->string('estado')->default('inactivo'); // activo, inactivo, cancelado, pendiente etc
            $table->decimal('monto', 10, 2)->nullable();
            $table->string('id_pago')->nullable();
            $table->string('id_orden')->nullable();
            $table->timestamp('fecha_ultimo_pago')->nullable();
            $table->timestamp('fecha_proximo_pago')->nullable();
            $table->timestamp('fin_periodo_prueba')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['empresa_id', 'estado']);
            $table->index('plan_id');
            $table->index('usuario_id');
            $table->index('id_pago');
            $table->index('fecha_proximo_pago');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('suscripciones');
    }
}
