<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDivisionCuentaRestauranteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('division_cuenta_restaurante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('restaurante_sesiones_mesa')->onDelete('cascade');
            $table->enum('tipo', ['equitativa', 'por_items'])->default('equitativa');
            $table->unsignedSmallInteger('num_pagadores')->default(1);
            $table->timestamps();

            $table->index('sesion_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('division_cuenta_restaurante');
    }
}
