<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_tratamientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_expediente')->constrained('clinica_expedientes');
            $table->foreignId('id_paciente')->constrained('clinica_pacientes');
            $table->unsignedBigInteger('id_consulta')->nullable();
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->string('nombre', 160)->nullable();
            $table->text('descripcion');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('frecuencia', 120)->nullable();
            $table->string('duracion', 120)->nullable();
            $table->text('indicaciones')->nullable();
            $table->string('estado', 20)->default('indicado');
            $table->string('motivo_suspension', 255)->nullable();
            $table->string('motivo_cierre', 255)->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'id_paciente', 'fecha_inicio'], 'clinica_trat_pac_fecha_idx');
            $table->foreign('id_empresa')->references('id')->on('empresas');
            // ponytail: sin FK a consultas — validación en TratamientoService; evita orden rígido de migraciones
        });

        Schema::create('clinica_tratamiento_avances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_tratamiento')->constrained('clinica_tratamientos')->cascadeOnDelete();
            $table->date('fecha');
            $table->text('nota')->nullable();
            $table->boolean('incumplimiento')->default(false);
            $table->unsignedBigInteger('id_usuario');
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_tratamiento_terapias', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_tratamiento')->constrained('clinica_tratamientos')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo', 80);
            $table->text('descripcion')->nullable();
            $table->text('notas_resultado')->nullable();
            $table->unsignedBigInteger('id_usuario');
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_tratamiento_terapias');
        Schema::dropIfExists('clinica_tratamiento_avances');
        Schema::dropIfExists('clinica_tratamientos');
    }
};
