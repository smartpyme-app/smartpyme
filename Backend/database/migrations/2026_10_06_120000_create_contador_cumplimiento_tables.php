<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contador_empresa_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->string('slug', 64);
            $table->string('ruta', 512);
            $table->string('nombre_archivo', 255);
            $table->string('mime', 128)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->date('vence_en')->nullable();
            $table->unsignedBigInteger('id_usuario_carga')->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'slug'], 'contador_empresa_documentos_empresa_slug');
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
    }
};
