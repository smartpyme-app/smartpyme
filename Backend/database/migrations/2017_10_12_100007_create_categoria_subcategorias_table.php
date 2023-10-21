<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCategoriaSubcategoriasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categoria_subcategorias', function (Blueprint $table) {
            $table->increments('id');

            $table->string('nombre');
            $table->string('img')->nullable();
            $table->text('descripcion')->nullable();
            $table->integer('categoria_id')->unsigned()->index();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('categoria_subcategorias');
    }
}
