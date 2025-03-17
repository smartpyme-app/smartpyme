<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCargosDeEmpresaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cargos_de_empresa')) {
            Schema::create('cargos_de_empresa', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100);
                $table->string('descripcion')->nullable();
                $table->decimal('salario_base', 10, 2)->default(0);
                $table->boolean('activo')->default(true);
                $table->integer('estado')->default(1);
                $table->unsignedBigInteger('id_departamento');
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
                $table->foreign('id_departamento')
                    ->references('id')
                    ->on('departamentos_empresa')
                    ->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->index(['id_sucursal', 'activo']);
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
        Schema::dropIfExists('cargos_de_empresa');
    }
}
