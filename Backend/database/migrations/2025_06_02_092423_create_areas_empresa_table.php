<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAreasEmpresaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('areas_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->tinyInteger('estado')->default(1);
            
            $table->unsignedBigInteger('id_departamento');
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_departamento')->references('id')->on('departamentos_empresa')->onDelete('cascade');
            $table->index(['id_departamento']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('areas_empresa');
    }
}
