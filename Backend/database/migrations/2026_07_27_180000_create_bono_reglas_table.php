<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBonoReglasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bono_reglas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->string('nombre');
            $table->string('tipo', 32); // meta_fija|escalonado
            $table->string('ventana', 32)->default('mensual');
            $table->json('config'); // meta_fija: {meta: 40000, bono: 100}; escalonado: {tramos:[{meta, bono},...]}
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bono_reglas');
    }
}
