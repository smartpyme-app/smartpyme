<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificacionesTable extends Migration
{

    public function up()
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->increments('id');
            
            $table->string('titulo');
            $table->text('descripcion');
            $table->string('tipo');
            $table->string('categoria');
            $table->string('prioridad');
            $table->boolean('leido');
            $table->string('referencia');
            $table->string('referencia_id');
            $table->integer('empresa_id');
            $table->integer('sucursal_id');
            $table->timestamps();

        });
    }
    
    public function down()
    {
        Schema::dropIfExists('notificaciones');
    }
}
