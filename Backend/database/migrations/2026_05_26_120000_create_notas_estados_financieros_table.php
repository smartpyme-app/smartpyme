<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_estados_financieros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('periodo_actual', 32);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->date('fecha_aprobacion_junta')->nullable();
            $table->boolean('incluir_comparativo')->default(false);
            $table->string('periodo_anterior', 32)->nullable();
            $table->enum('nivel_detalle', ['completo', 'resumido'])->default('completo');
            $table->json('notas_a_incluir');
            $table->json('configuracion')->nullable();
            $table->json('contenido_manual')->nullable();
            $table->json('notas_generadas')->nullable();
            $table->json('completitud')->nullable();
            $table->json('validaciones_cruzadas')->nullable();
            $table->enum('estado', ['borrador', 'emitido'])->default('borrador');
            $table->unsignedBigInteger('id_usuario_creacion')->nullable();
            $table->unsignedBigInteger('id_usuario_emision')->nullable();
            $table->timestamp('fecha_emision')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'fecha_inicio', 'fecha_fin']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_usuario_creacion')->references('id')->on('users');
            $table->foreign('id_usuario_emision')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_estados_financieros');
    }
};
