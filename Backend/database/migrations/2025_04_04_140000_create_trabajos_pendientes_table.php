<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrabajosPendientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trabajos_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->text('parametros');
            $table->enum('estado', ['pendiente', 'procesando', 'completado', 'fallido']);
            $table->timestamp('fecha_creacion');
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->text('resultado')->nullable();
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_empresa');
            $table->timestamps();

            $table->index(['estado', 'tipo']);
            $table->index('id_empresa');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trabajos_pendientes');
    }
}
