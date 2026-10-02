<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinica_especies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('nombre', 80);
            $table->timestamps();
            $table->unique(['id_empresa', 'nombre']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_razas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_especie')->constrained('clinica_especies');
            $table->string('nombre', 80);
            $table->timestamps();
            $table->unique(['id_especie', 'nombre']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_pacientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->integer('id_sucursal')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('tipo', 20);
            $table->boolean('activo')->default(true);
            $table->string('nombres', 120)->nullable();
            $table->string('apellidos', 120)->nullable();
            $table->string('nombre', 120)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 20)->nullable();
            $table->string('documento', 50)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->text('informacion_relevante')->nullable();
            $table->foreignId('id_especie')->nullable()->constrained('clinica_especies');
            $table->foreignId('id_raza')->nullable()->constrained('clinica_razas');
            $table->string('color', 50)->nullable();
            $table->decimal('peso', 8, 2)->nullable();
            $table->string('microchip', 50)->nullable();
            $table->boolean('esterilizado')->nullable();
            $table->string('identificadores', 255)->nullable();
            $table->timestamps();

            $table->index(['id_empresa', 'activo', 'tipo']);
            $table->unique(['id_empresa', 'documento']);
            $table->unique(['id_empresa', 'microchip']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_sucursal')->references('id')->on('sucursales')->nullOnDelete();
        });

        Schema::create('clinica_expedientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_paciente')->unique()->constrained('clinica_pacientes');
            $table->unsignedInteger('numero');
            $table->date('fecha_apertura');
            $table->string('estado', 20)->default('abierto');
            $table->timestamps();
            $table->unique(['id_empresa', 'numero']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_expediente_secuencias', function (Blueprint $table) {
            $table->unsignedInteger('id_empresa')->primary();
            $table->unsignedInteger('ultimo')->default(0);
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_expediente_secuencias');
        Schema::dropIfExists('clinica_expedientes');
        Schema::dropIfExists('clinica_pacientes');
        Schema::dropIfExists('clinica_razas');
        Schema::dropIfExists('clinica_especies');
    }
};
