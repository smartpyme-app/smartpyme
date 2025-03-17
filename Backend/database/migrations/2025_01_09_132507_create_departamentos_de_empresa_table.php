<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartamentosDeEmpresaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('departamentos_empresa')) {
            Schema::create('departamentos_empresa', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->integer('estado')->default(1);
                $table->integer('id_sucursal')->unsigned();
                $table->integer('id_empresa')->unsigned();
                $table->foreign('id_sucursal')
                    ->references('id')
                    ->on('sucursales')
                    ->onDelete('cascade');
                $table->foreign('id_empresa')
                    ->references('id')
                    ->on('empresas')
                    ->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();
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
        Schema::dropIfExists('departamentos_empresa');
    }
}
