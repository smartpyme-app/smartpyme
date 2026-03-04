<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConceptosPersonalizadosToPlanillaDetallesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->json('conceptos_personalizados')->nullable()->after('detalle_otras_deducciones');
            $table->string('pais_configuracion', 3)->default('SV')->after('conceptos_personalizados');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('planilla_detalles', function (Blueprint $table) {
            $table->dropColumn('conceptos_personalizados');
            $table->dropColumn('pais_configuracion');
        });
    }
}
