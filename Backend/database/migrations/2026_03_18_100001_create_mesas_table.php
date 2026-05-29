<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMesasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('restaurante_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_empresa');
            $table->unsignedInteger('id_sucursal')->nullable();
            $table->string('numero', 20);
            $table->unsignedSmallInteger('capacidad')->default(4);
            $table->string('zona', 50)->nullable();
            $table->enum('estado', ['libre', 'ocupada', 'pendiente_pago', 'reservada'])->default('libre');
            $table->boolean('activo')->default(1);
            $table->unsignedSmallInteger('orden')->default(0)->comment('Orden en el mapa');
            $table->timestamps();

            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
            $table->index(['id_empresa', 'id_sucursal']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('restaurante_mesas');
    }
}
