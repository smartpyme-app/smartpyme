<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransporteMantenimientoDetallesTable extends Migration {

    public function up()
    {
        Schema::create('transporte_mantenimiento_detalles', function(Blueprint $table)
        {
            $table->increments('id');

            $table->integer('producto_id');
            $table->integer('cantidad');
            $table->decimal('costo', 9,2);
            $table->decimal('total', 9,2);
            $table->string('nota')->nullable();
            $table->integer('mantenimiento_id');
            
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::drop('transporte_mantenimiento_detalles');
    }

}
