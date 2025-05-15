<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNombreReporteToReporteConfiguracionesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reporte_configuraciones', function (Blueprint $table) {
            $table->text('nombre_reporte')->after('id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reporte_configuraciones', function (Blueprint $table) {
            $table->dropColumn('nombre_reporte');
        });
    }
}
