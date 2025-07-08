<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaldosMensualesTable extends Migration
{
    public function up()
    {
        Schema::create('saldos_mensuales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cuenta');
            $table->string('codigo_cuenta');
            $table->string('nombre_cuenta');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->decimal('debe', 15, 2)->default(0);
            $table->decimal('haber', 15, 2)->default(0);
            $table->decimal('saldo_final', 15, 2)->default(0);
            $table->string('naturaleza'); // Deudor/Acreedor
            $table->enum('estado', ['Abierto', 'Cerrado'])->default('Abierto');
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_usuario_cierre')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['id_empresa', 'year', 'month']);
            $table->index(['id_cuenta', 'year', 'month']);
            $table->unique(['id_cuenta', 'year', 'month', 'id_empresa'], 'unique_saldo_mensual');

            // Relaciones
            $table->foreign('id_cuenta')->references('id')->on('catalogo_cuentas');
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_usuario_cierre')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('saldos_mensuales');
    }
}
