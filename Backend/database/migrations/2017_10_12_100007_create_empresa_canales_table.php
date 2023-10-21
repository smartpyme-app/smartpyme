<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEmpresaCanalesTable extends Migration {

    public function up()
    {
        Schema::create('empresa_canales', function(Blueprint $table)
        {
            $table->increments('id');

            $table->string('nombre');
            $table->integer('empresa_id');

            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::drop('empresa_canales');
    }

}
