<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpresaConfiguracionTable extends Migration
{
    public function up()
    {
        Schema::create('empresa_configuracion', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->string('pais', 3);
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();

            $table->unique(['empresa_id', 'pais', 'modulo']);
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            $table->index(['empresa_id', 'modulo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('empresa_configuracion');
    }
}
