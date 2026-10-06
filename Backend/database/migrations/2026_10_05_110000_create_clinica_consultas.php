<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_consultas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_expediente')->constrained('clinica_expedientes');
            $table->foreignId('id_paciente')->constrained('clinica_pacientes');
            $table->integer('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->date('fecha');
            $table->time('hora')->nullable();
            $table->string('motivo', 255);
            $table->string('estado', 20)->default('borrador');
            $table->text('anamnesis')->nullable();
            $table->text('antecedentes')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('indicaciones')->nullable();
            $table->json('signos_vitales')->nullable();
            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'id_paciente', 'fecha']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_sucursal')->references('id')->on('sucursales');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_consultas');
    }
};
