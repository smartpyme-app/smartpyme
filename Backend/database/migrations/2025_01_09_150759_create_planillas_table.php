<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanillasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('planillas')) {
            Schema::create('planillas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->nullable();
                $table->string('tipo_planilla');
                $table->integer('estado');//['Borrador', 'Procesada', 'Pagada', 'Anulada'])->default('Borrador');
                $table->decimal('total_salarios', 10, 2)->default(0);
                $table->decimal('total_deducciones', 10, 2) ->default(0);
                $table->decimal('total_neto', 10, 2)->default(0);
                $table->decimal('total_aportes_patronales', 10, 2)->default(0);
                $table->unsignedInteger('id_empresa');
                $table->unsignedInteger('id_sucursal');
                
                $table->string('mes')->nullable();
                $table->year('anio')->nullable();
                $table->date('fecha_inicio');
                $table->date('fecha_fin');
                $table->timestamps();
                $table->foreign('id_empresa')->references('id')->on('empresas');
                $table->foreign('id_sucursal')->references('id')->on('sucursales');
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
        Schema::dropIfExists('planillas');
    }
}
