<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinica_pacientes', function (Blueprint $table) {
            $table->boolean('alta_cerrada')->default(false)->after('activo');
        });

        Schema::create('clinica_responsables', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('nombre', 160);
            $table->string('documento', 50)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->timestamps();
            $table->foreign('id_empresa')->references('id')->on('empresas');
        });

        Schema::create('clinica_paciente_responsables', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->foreignId('id_paciente')->constrained('clinica_pacientes');
            $table->integer('id_cliente')->nullable();
            $table->foreignId('id_responsable')->nullable()->constrained('clinica_responsables')->nullOnDelete();
            $table->string('rol', 30);
            $table->boolean('es_principal')->default(false);
            $table->boolean('es_el_paciente')->default(false);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();
            $table->index(['id_paciente', 'vigente_hasta']);
            $table->index(['id_empresa', 'id_cliente']);
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_cliente')->references('id')->on('clientes')->nullOnDelete();
        });

        Schema::create('clinica_profesionales', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedBigInteger('id_usuario');
            $table->string('cargo', 80)->nullable();
            $table->string('especialidad', 80)->nullable();
            $table->string('colegiatura', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique('id_usuario');
            $table->foreign('id_empresa')->references('id')->on('empresas');
            $table->foreign('id_usuario')->references('id')->on('users');
        });

        Schema::create('clinica_profesional_sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_profesional')->constrained('clinica_profesionales')->cascadeOnDelete();
            $table->integer('id_sucursal');
            $table->unique(['id_profesional', 'id_sucursal']);
            $table->foreign('id_sucursal')->references('id')->on('sucursales')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinica_profesional_sucursales');
        Schema::dropIfExists('clinica_profesionales');
        Schema::dropIfExists('clinica_paciente_responsables');
        Schema::dropIfExists('clinica_responsables');
        Schema::table('clinica_pacientes', function (Blueprint $table) {
            $table->dropColumn('alta_cerrada');
        });
    }
};
