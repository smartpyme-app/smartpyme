<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credito_contratos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_cliente');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->string('tipo', 20);
            $table->decimal('monto', 12, 2);
            $table->unsignedSmallInteger('n_cuotas');
            $table->date('fecha_inicio');
            $table->string('periodicidad', 20)->default('mensual');
            $table->decimal('tasa_interes', 8, 4)->default(0);
            $table->decimal('tasa_mora', 8, 4)->default(0);
            $table->string('concepto')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            $table->index(['id_empresa', 'id_cliente']);
        });

        Schema::create('credito_cuotas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_contrato');
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_vencimiento');
            $table->decimal('monto', 12, 2);
            $table->string('estado', 20)->default('programada');
            $table->unsignedInteger('id_venta')->nullable();
            $table->timestamps();

            $table->foreign('id_contrato')->references('id')->on('credito_contratos')->onDelete('cascade');
            $table->unique(['id_contrato', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credito_cuotas');
        Schema::dropIfExists('credito_contratos');
    }
};
