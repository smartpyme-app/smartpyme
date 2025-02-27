<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpresaCargoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('empresa_cargo')) {
            Schema::create('empresa_cargo', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedBigInteger('cargo_id');
                $table->boolean('estado')->default(true);
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
                $table->foreign('cargo_id')->references('id')->on('cargos_de_empresa')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empresa_cargo');
    }
}
