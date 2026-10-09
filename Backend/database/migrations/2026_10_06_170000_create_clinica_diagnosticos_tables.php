<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_diagnostico_catalogo', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('codigo', 32);
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['id_empresa', 'codigo'], 'clinica_diag_cat_emp_cod_uq');
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_expediente')->constrained('clinica_expedientes');
            $table->foreignId('id_paciente')->constrained('clinica_pacientes');
            $table->unsignedBigInteger('id_consulta')->nullable();
            $table->unsignedInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->unsignedBigInteger('id_diagnostico_anterior')->nullable();
            $table->string('codigo', 32)->nullable();
            $table->text('descripcion');
            $table->string('rol', 16)->default('secundario');
            $table->date('fecha');
            $table->string('estado', 16)->default('activo');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'id_paciente', 'fecha'], 'clinica_diag_pac_fecha_idx');
            $table->index(['id_consulta', 'rol', 'estado'], 'clinica_diag_cons_rol_idx');
            $table->foreign('id_empresa')->references('id')->on('empresas');
            // ponytail: sin FK a consultas — validación en DiagnosticoService
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_diagnosticos');
        Schema::dropIfExists('clinica_diagnostico_catalogo');
    }
};
