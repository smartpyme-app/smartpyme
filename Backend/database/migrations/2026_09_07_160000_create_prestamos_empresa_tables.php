<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos_empresa', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->string('tipo_acreedor', 20);
            $table->string('acreedor');
            $table->string('concepto')->nullable();
            $table->boolean('historico')->default(false);
            $table->decimal('monto_original', 12, 2)->nullable();
            $table->decimal('monto', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->boolean('genera_interes')->default(false);
            $table->decimal('tasa_interes', 8, 4)->default(0);
            $table->unsignedSmallInteger('n_cuotas');
            $table->string('frecuencia', 20)->default('mensual');
            $table->date('fecha_desembolso');
            $table->date('fecha_primera_cuota');
            $table->unsignedInteger('id_cuenta_banco')->nullable();
            $table->boolean('generar_asiento_desembolso')->default(true);
            $table->string('clasificacion', 10)->default('corto');
            $table->string('estado', 20)->default('activo');
            $table->timestamps();

            $table->index(['id_empresa', 'estado']);
        });

        Schema::create('prestamo_cuotas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_prestamo');
            $table->unsignedSmallInteger('numero');
            $table->date('fecha_vencimiento');
            $table->decimal('capital', 12, 2);
            $table->decimal('interes', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedBigInteger('id_pago')->nullable();
            $table->timestamps();

            $table->foreign('id_prestamo')->references('id')->on('prestamos_empresa')->onDelete('cascade');
            $table->unique(['id_prestamo', 'numero']);
        });

        Schema::create('prestamo_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_prestamo');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->decimal('capital', 12, 2);
            $table->decimal('interes', 12, 2)->default(0);
            $table->string('metodo')->nullable();
            $table->unsignedInteger('id_usuario')->nullable();
            $table->timestamps();

            $table->foreign('id_prestamo')->references('id')->on('prestamos_empresa')->onDelete('cascade');
        });

        Schema::table('contabilidad_configuracion', function (Blueprint $table) {
            $table->unsignedInteger('id_cuenta_prestamos_corto')->nullable();
            $table->unsignedInteger('id_cuenta_prestamos_largo')->nullable();
            $table->unsignedInteger('id_cuenta_gastos_financieros')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contabilidad_configuracion', function (Blueprint $table) {
            $table->dropColumn([
                'id_cuenta_prestamos_corto',
                'id_cuenta_prestamos_largo',
                'id_cuenta_gastos_financieros',
            ]);
        });
        Schema::dropIfExists('prestamo_pagos');
        Schema::dropIfExists('prestamo_cuotas');
        Schema::dropIfExists('prestamos_empresa');
    }
};
