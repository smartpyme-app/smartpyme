<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductoSucursalesTable extends Migration {

    public function up()
    {
        Schema::create('producto_sucursales', function(Blueprint $table)
        {
            $table->increments('id');

            $table->integer('producto_id');
            $table->boolean('activo')->default(1);
            $table->integer('sucursal_id');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('producto_sucursales');
    }

}
