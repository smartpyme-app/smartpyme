<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGastosCategoriasTable extends Migration
{

    public function up()
    {
        Schema::create('gastos_categorias', function (Blueprint $table) {
            $table->increments('id');
            
            $table->string('nombre');
            $table->integer('empresa_id');

            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('gastos_categorias');
    }
}
