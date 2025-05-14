<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpresaClientesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('empresa_clientes', function (Blueprint $table) {
            $table->id();
            //int unsigned
            // $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedInteger('id_empresa')->nullable();
            $table->char('id_client', 36)->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->boolean('estado')->nullable()->default(1);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('id_empresa')->references('id')->on('empresas');

            $table->foreign('id_user')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('empresa_clientes');
    }
}
