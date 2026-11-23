<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_vacuna_catalogo', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('nombre', 160);
            $table->string('tipo', 20)->default('vacuna');
            $table->string('tipo_paciente', 20)->nullable();
            $table->unsignedBigInteger('id_especie')->nullable();
            $table->unsignedSmallInteger('intervalo_dias')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_vacuna_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_expediente')->constrained('clinica_expedientes');
            $table->foreignId('id_paciente')->constrained('clinica_pacientes');
            $table->unsignedBigInteger('id_catalogo')->nullable();
            $table->string('nombre_vacuna', 160);
            $table->string('tipo', 20)->default('vacuna');
            $table->date('fecha_aplicacion');
            $table->string('dosis', 120)->nullable();
            $table->unsignedInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedInteger('id_producto')->nullable();
            $table->unsignedBigInteger('id_lote')->nullable();
            $table->string('lote_numero', 80)->nullable();
            $table->date('lote_vencimiento')->nullable();
            $table->string('fabricante', 120)->nullable();
            $table->date('proxima_fecha')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('activa');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'id_paciente', 'fecha_aplicacion'], 'clinica_vac_apl_idx');
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_catalogo')->references('id')->on('clinica_vacuna_catalogo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_vacuna_aplicaciones');
        Schema::dropIfExists('clinica_vacuna_catalogo');
    }
};
