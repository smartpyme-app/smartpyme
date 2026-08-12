<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuscripcionBajasTable extends Migration
{
    public function up()
    {
        Schema::create('suscripcion_bajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('suscripcion_id');
            $table->unsignedInteger('empresa_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('motivo', 40); // cancelacion_voluntaria | falta_pago | inactividad
            $table->timestamp('fecha_baja');
            $table->string('tipo_plan', 40)->nullable(); // Mensual | Trimestral | Anual
            $table->decimal('monto', 10, 2)->default(0);
            $table->string('plan_nombre')->nullable();
            $table->string('empresa_nombre')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps();

            $table->index(['fecha_baja']);
            $table->index(['motivo', 'fecha_baja']);
            $table->index(['empresa_id', 'fecha_baja']);
            // Idempotencia por día se aplica en RegistrarSuscripcionBaja (whereDate).
            $table->index(['suscripcion_id', 'motivo', 'fecha_baja'], 'suscripcion_bajas_evento_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('suscripcion_bajas');
    }
}
