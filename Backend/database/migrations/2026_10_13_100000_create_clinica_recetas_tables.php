<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_recetas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_expediente')->constrained('clinica_expedientes');
            $table->foreignId('id_paciente')->constrained('clinica_pacientes');
            $table->unsignedBigInteger('id_consulta')->nullable();
            $table->unsignedBigInteger('id_tratamiento')->nullable();
            $table->unsignedInteger('id_sucursal');
            $table->unsignedBigInteger('id_usuario_profesional');
            $table->unsignedBigInteger('id_usuario_registro')->nullable();
            $table->date('fecha');
            $table->string('estado', 20)->default('borrador');
            $table->string('motivo_anulacion', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'id_paciente', 'fecha'], 'clinica_rec_pac_fecha_idx');
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_receta_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_receta')->constrained('clinica_recetas')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->unsignedInteger('id_producto')->nullable();
            $table->string('nombre_medicamento', 255);
            $table->string('dosis', 120);
            $table->string('frecuencia', 120)->nullable();
            $table->string('duracion', 120)->nullable();
            $table->string('via', 80)->nullable();
            $table->text('indicaciones')->nullable();
            $table->decimal('peso_referencia', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_producto')->references('id')->on('productos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_receta_lineas');
        Schema::dropIfExists('clinica_recetas');
    }
};
