<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpresaFuncionalidadesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('empresa_funcionalidades', function (Blueprint $table) {
            $table->id();
            $table->integer('id_empresa')->unsigned()->constrained('empresas')->onDelete('cascade');
            $table->foreignId('id_funcionalidad')->constrained('funcionalidades')->onDelete('cascade');
            $table->boolean('activo')->default(1);
            $table->json('configuracion')->nullable();
            $table->timestamps();
            $table->unique(['id_empresa', 'id_funcionalidad'], 'empresa_funcionalidad_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empresa_funcionalidades');
    }
}
