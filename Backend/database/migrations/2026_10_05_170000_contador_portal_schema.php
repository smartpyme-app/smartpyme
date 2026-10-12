<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contador_empresa_accesos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario_contador');
            $table->unsignedInteger('id_empresa');
            $table->string('estado', 20)->default('activo');
            $table->json('permisos')->nullable();
            $table->unsignedBigInteger('invitado_por_user_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['id_usuario_contador', 'id_empresa'], 'contador_empresa_accesos_unique');
            $table->foreign('id_usuario_contador')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('invitado_por_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('contador_empresa_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('slug', 64);
            $table->string('titulo', 255)->nullable();
            $table->string('ruta', 512);
            $table->string('nombre_archivo', 255);
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->date('vence_en')->nullable();
            $table->unsignedBigInteger('id_usuario_carga')->nullable();
            $table->timestamps();

            $table->index('id_empresa', 'contador_empresa_documentos_id_empresa_idx');
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario_carga')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('contador_obligaciones_fiscales', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('codigo', 16);
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('anio');
            $table->timestamp('presentado_en');
            $table->unsignedBigInteger('id_usuario_presento')->nullable();
            $table->timestamps();

            $table->unique(
                ['id_empresa', 'codigo', 'mes', 'anio'],
                'contador_obligaciones_fiscales_unique'
            );
            $table->foreign('id_empresa')->references('id')->on('empresas')->cascadeOnDelete();
            $table->foreign('id_usuario_presento')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contador_obligaciones_fiscales');
        Schema::dropIfExists('contador_empresa_documentos');
        Schema::dropIfExists('contador_empresa_accesos');
    }
};
