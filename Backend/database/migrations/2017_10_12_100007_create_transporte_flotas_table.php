<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransporteFlotasTable extends Migration {

    public function up()
    {
        Schema::create('transporte_flotas', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('img')->nullable();
            $table->string('propietario');
            $table->string('placa')->unique()->nullable();
            $table->string('tipo')->default('Cabezal');// Cabezal, Remolque;
            $table->string('vin')->nullable();
            $table->string('num_chasis')->nullable();
            $table->string('num_motor')->nullable();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('capacidad')->nullable();
            $table->string('anio')->nullable();
            $table->string('color')->nullable();
            $table->string('kilometraje')->nullable();
            $table->string('tipo_combustible')->default('Diesel');// Gasolina, Diesel;
            $table->string('nota')->nullable();
            $table->date('ultimo_mantenimiento')->nullable();
            $table->date('proximo_mantenimiento')->nullable();
            $table->date('vencimiento_tarjeta')->nullable();
            $table->date('vencimiento_garantia')->nullable();
            $table->date('vencimiento_poliza')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('usuario_id');
            $table->integer('sucursal_id');
            
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::drop('transporte_flotas');
    }

}
