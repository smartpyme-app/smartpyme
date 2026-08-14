<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaisConfiguracionTable extends Migration
{
    public function up()
    {
        Schema::create('pais_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('pais', 3);
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();

            $table->unique(['pais', 'modulo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('pais_configuracion');
    }
}
