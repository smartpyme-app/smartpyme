<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpresaConfiguracionPlanillaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('empresa_configuracion_planilla', function (Blueprint $table) {
            $table->id();
            $table->integer('empresa_id')->unsigned();
            $table->string('cod_pais', 3)->default('SV');
            $table->json('configuracion');
            $table->date('fecha_vigencia_desde')->nullable();
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();

            // Foreign key constraints  
            $table->foreign('empresa_id')->references('id')->on('empresas')->onDelete('cascade');
            
            // Indexes
            $table->index('empresa_id');
            $table->index('cod_pais');
            $table->index(['empresa_id', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('empresa_configuracion_planilla');
    }
}
