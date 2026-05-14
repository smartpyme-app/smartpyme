<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - id_empresa: alineado con otras tablas que referencian empresas.id (p. ej. planillas, authorizations).
     * - id_partida_*: partidas.id suele ser BIGINT UNSIGNED; INT UNSIGNED provoca SQLSTATE 3780.
     */
    public function up(): void
    {
        Schema::create('ejercicios_fiscales', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedSmallInteger('anio_referencia');
            $table->string('estado', 32)->default('abierto');
            $table->unsignedBigInteger('id_partida_cierre')->nullable();
            $table->unsignedBigInteger('id_partida_reversa')->nullable()->comment('Si reapertura por reversa');
            $table->unsignedBigInteger('id_usuario_cierre')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();

            $table->unique(['id_empresa', 'anio_referencia']);
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->foreign('id_partida_cierre')->references('id')->on('partidas')->nullOnDelete();
            $table->foreign('id_partida_reversa')->references('id')->on('partidas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicios_fiscales');
    }
};
