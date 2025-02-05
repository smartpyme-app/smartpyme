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
            $table->unsignedInteger('empresa_id');
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('planes');
            $table->foreignId('usuario_id')->constrained('users');
            
            // Campos básicos
            $table->string('tipo_plan');
            $table->string('estado')
                  ->default(null);
            $table->decimal('monto', 10, 2);
            
            // Campos de n1co
            $table->string('id_pago')->nullable(); // PaymentId de n1co
            $table->string('id_orden')->nullable(); // OrderId de n1co
            $table->string('n1co_authorization_code')->nullable();
            $table->json('n1co_metadata')->nullable();
            
            // Campos de facturación
            $table->boolean('requiere_factura')->default(false);
            $table->string('nit')->nullable();
            $table->string('nombre_factura')->nullable();
            $table->string('direccion_factura')->nullable();
            
            // Campos de fechas
            $table->timestamp('fecha_ultimo_pago')->nullable();
            $table->timestamp('fecha_proximo_pago')->nullable();
            $table->timestamp('fin_periodo_prueba')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            
            // Campos adicionales
            $table->string('estado_ultimo_pago')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->integer('intentos_cobro')->default(0);
            $table->timestamp('ultimo_intento_cobro')->nullable();
            $table->json('historial_pagos')->nullable();
            
            $table->timestamps();
            $table->index(['empresa_id', 'estado']);
            $table->index(['id_pago', 'id_orden']);
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
