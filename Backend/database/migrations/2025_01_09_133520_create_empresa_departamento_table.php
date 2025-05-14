<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpresaDepartamentoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
//    public function up()
//    {
//        if (!Schema::hasTable('empresa_departamento')) {
//            Schema::create('empresa_departamento', function (Blueprint $table) {
//                $table->id();
//                $table->unsignedInteger('empresa_id');
//                $table->unsignedBigInteger('departamento_id');
//                $table->boolean('estado')->default(true);
//                $table->timestamps();
//
//                $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
//                $table->foreign('departamento_id')->references('id')->on('departamentos_de_empresa')->onDelete('cascade');
//            });
//        }
//    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empresa_departamento');
    }
}
