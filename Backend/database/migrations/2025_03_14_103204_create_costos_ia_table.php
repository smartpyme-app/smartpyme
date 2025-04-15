<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCostosIATable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('costos_ia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->integer('id_empresa')->unsigned();
            $table->string('modelo')->nullable();
            $table->integer('tokens_entrada')->default(0);
            $table->integer('tokens_salida')->default(0);
            $table->decimal('costo_estimado', 10, 6)->default(0);
            $table->text('consulta')->nullable();
            $table->text('respuesta')->nullable();
            $table->timestamps();

            $table->foreign('id_usuario')->references('id')->on('users')->onDelete('set null');
            $table->foreign('id_empresa')->references('id')->on('empresas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('costos_ia');
    }
}