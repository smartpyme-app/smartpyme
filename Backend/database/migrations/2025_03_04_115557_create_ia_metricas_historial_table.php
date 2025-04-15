<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIaMetricasHistorialTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ia_metricas_historial', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_metrica', ['empresa', 'sucursal']);
            $table->unsignedInteger('id_metrica');
            $table->timestamp('fecha_actualizacion')->useCurrent();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('notas')->nullable();
            
            // Índices
            $table->index(['tipo_metrica', 'id_metrica'], 'idx_tipo_metrica');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ia_metricas_historial');
    }
}