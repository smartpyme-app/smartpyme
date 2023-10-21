<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransporteMantenimientosTable extends Migration {

    public function up()
    {
        Schema::create('transporte_mantenimientos', function(Blueprint $table)
        {
            $table->increments('id');

            $table->date('fecha');
            $table->string('estado');
            $table->string('tipo')->default('Correctivo');
            $table->integer('flota_id');
            $table->decimal('total', 9,2);
            $table->string('nota')->nullable();
            $table->integer('bodega_id')->nullable();
            $table->integer('usuario_id');
            $table->integer('sucursal_id');
            
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::drop('transporte_mantenimientos');
    }

}
