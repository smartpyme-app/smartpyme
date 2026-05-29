<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSesionesMesaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('restaurante_sesiones_mesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesa_id')->constrained('restaurante_mesas')->onDelete('cascade');
            $table->unsignedBigInteger('usuario_id')->comment('Mesero asignado');
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_sucursal')->nullable();
            $table->unsignedSmallInteger('num_comensales')->default(1);
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['abierta', 'pre_cuenta', 'cerrada'])->default('abierta');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->index(['id_empresa', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('restaurante_sesiones_mesa');
    }
}
