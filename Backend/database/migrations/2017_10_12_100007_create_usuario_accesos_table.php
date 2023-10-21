<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsuarioAccesosTable extends Migration
{

    public function up()
    {
        Schema::create('usuario_accesos', function (Blueprint $table) {
            $table->increments('id');
            
            $table->datetime('fecha');
            $table->integer('usuario_id');
            $table->timestamps();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('usuario_accesos');
    }
}
