<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComisionCategoriaConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comision_categoria_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_categoria');
            $table->decimal('porcentaje', 8, 4)->default(0);
            $table->timestamps();
            $table->unique(['id_empresa', 'id_categoria']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comision_categoria_config');
    }
}
